#include "jx-idle-collect.h"
#include "jx-idle-bus.h"
#include <string.h>

static void lock_deque(jx_idle_note_deque *deque) {
    uint32_t expected;
    for (;;) {
        expected = 0u;
        if (atomic_compare_exchange_weak_explicit(&deque->lock,
                                                  &expected,
                                                  1u,
                                                  memory_order_acquire,
                                                  memory_order_relaxed))
            return;
    }
}

static void unlock_deque(jx_idle_note_deque *deque) {
    atomic_store_explicit(&deque->lock, 0u, memory_order_release);
}

static uint32_t ring_count(uint32_t head, uint32_t tail) {
    return tail >= head ? tail - head : (JX_IDLE_NOTE_CAPACITY - head) + tail;
}

void jx_idle_note_deque_init(jx_idle_note_deque *deque) {
    if (!deque) return;
    memset(deque, 0, sizeof *deque);
    deque->version = JX_IDLE_COLLECT_VERSION;
    atomic_init(&deque->lock, 0u);
    atomic_init(&deque->collect_pending, 0u);
    atomic_init(&deque->mode, JX_IDLE_NOTE_MODE_ACK);
    atomic_init(&deque->ack_head, 0u);
    atomic_init(&deque->ack_tail, 0u);
    atomic_init(&deque->data_head, 0u);
    atomic_init(&deque->data_tail, 0u);
    atomic_init(&deque->expected_answers, 0u);
    atomic_init(&deque->answered, 0u);
    atomic_init(&deque->data_answers, 0u);
    atomic_init(&deque->finalized, 0u);
    atomic_init(&deque->epoch, 0u);
}

int jx_idle_note_begin_epoch(jx_idle_note_deque *deque,
                             uint64_t epoch,
                             uint32_t expected_answers) {
    if (!deque || deque->version != JX_IDLE_COLLECT_VERSION || !epoch) return -1;
    if (!jx_idle_epoch_is_complete(deque)) return -2;
    if (jx_idle_collect_is_pending(deque)) return -3;

    lock_deque(deque);
    atomic_store_explicit(&deque->ack_head, 0u, memory_order_relaxed);
    atomic_store_explicit(&deque->ack_tail, 0u, memory_order_relaxed);
    atomic_store_explicit(&deque->data_head, 0u, memory_order_relaxed);
    atomic_store_explicit(&deque->data_tail, 0u, memory_order_release);
    unlock_deque(deque);

    atomic_store_explicit(&deque->mode, JX_IDLE_NOTE_MODE_ACK, memory_order_release);
    atomic_store_explicit(&deque->epoch, epoch, memory_order_release);
    atomic_store_explicit(&deque->expected_answers, expected_answers, memory_order_release);
    atomic_store_explicit(&deque->answered, 0u, memory_order_release);
    atomic_store_explicit(&deque->data_answers, 0u, memory_order_release);
    atomic_store_explicit(&deque->finalized, 0u, memory_order_release);
    return 0;
}

int jx_idle_note_publish(jx_idle_note_deque *deque,
                         uint32_t program_id,
                         uint64_t epoch,
                         uint8_t has_data) {
    if (!deque || deque->version != JX_IDLE_COLLECT_VERSION || !program_id || has_data > 1u)
        return -1;
    if (epoch != atomic_load_explicit(&deque->epoch, memory_order_acquire)) return -3;
    if (atomic_load_explicit(&deque->finalized, memory_order_acquire)) return -4;

    int armed = 0;
    if (has_data) {
        uint32_t expected_mode = JX_IDLE_NOTE_MODE_ACK;
        if (atomic_compare_exchange_strong_explicit(&deque->mode,
                                                    &expected_mode,
                                                    JX_IDLE_NOTE_MODE_DATA,
                                                    memory_order_acq_rel,
                                                    memory_order_acquire)) {
            uint32_t expected_pending = 0u;
            if (atomic_compare_exchange_strong_explicit(&deque->collect_pending,
                                                        &expected_pending,
                                                        1u,
                                                        memory_order_acq_rel,
                                                        memory_order_acquire))
                armed = 1;
        }
    }

    lock_deque(deque);
    uint32_t head = atomic_load_explicit(&deque->ack_head, memory_order_relaxed);
    uint32_t tail = atomic_load_explicit(&deque->ack_tail, memory_order_relaxed);
    uint32_t next = (tail + 1u) % JX_IDLE_NOTE_CAPACITY;
    if (next == head) {
        unlock_deque(deque);
        return -2;
    }
    uint32_t ack_ordinal = atomic_load_explicit(&deque->answered, memory_order_relaxed);
    jx_idle_note *ack = &deque->ack_notes[tail];
    ack->program_id = program_id;
    ack->ack_ordinal = ack_ordinal;
    ack->one_ordinal = JX_IDLE_ORDINAL_NONE;
    ack->reserved32 = 0u;
    ack->epoch = epoch;
    ack->has_data = has_data;
    memset(ack->reserved, 0, sizeof ack->reserved);
    atomic_store_explicit(&deque->ack_tail, next, memory_order_release);
    unlock_deque(deque);

    if (has_data)
        atomic_fetch_add_explicit(&deque->data_answers, 1u, memory_order_acq_rel);
    atomic_fetch_add_explicit(&deque->answered, 1u, memory_order_acq_rel);
    return has_data ? (armed ? 2 : 1) : 0;
}

int jx_idle_note_finalize_epoch(jx_idle_note_deque *deque) {
    if (!deque || deque->version != JX_IDLE_COLLECT_VERSION) return -1;
    if (!jx_idle_epoch_is_complete(deque)) return 0;

    uint32_t expected_finalized = 0u;
    if (!atomic_compare_exchange_strong_explicit(&deque->finalized,
                                                 &expected_finalized,
                                                 1u,
                                                 memory_order_acq_rel,
                                                 memory_order_acquire))
        return (int)jx_idle_epoch_data_count(deque);

    lock_deque(deque);
    uint32_t ack_head = atomic_load_explicit(&deque->ack_head, memory_order_relaxed);
    uint32_t ack_tail = atomic_load_explicit(&deque->ack_tail, memory_order_acquire);
    uint32_t data_tail = 0u;
    uint32_t one_ordinal = 0u;

    for (uint32_t at = ack_head; at != ack_tail; at = (at + 1u) % JX_IDLE_NOTE_CAPACITY) {
        jx_idle_note *ack = &deque->ack_notes[at];
        if (!ack->has_data) continue;
        if (data_tail >= JX_IDLE_NOTE_CAPACITY - 1u) {
            unlock_deque(deque);
            return -2;
        }
        ack->one_ordinal = one_ordinal;
        jx_idle_note *data = &deque->data_notes[data_tail++];
        *data = *ack;
        data->one_ordinal = one_ordinal++;
    }

    atomic_store_explicit(&deque->data_head, 0u, memory_order_relaxed);
    atomic_store_explicit(&deque->data_tail, data_tail, memory_order_release);
    unlock_deque(deque);
    return (int)data_tail;
}

int jx_idle_collect_is_pending(const jx_idle_note_deque *deque) {
    if (!deque || deque->version != JX_IDLE_COLLECT_VERSION) return 0;
    return atomic_load_explicit(&deque->collect_pending, memory_order_acquire) != 0u;
}

int jx_idle_epoch_is_complete(const jx_idle_note_deque *deque) {
    if (!deque || deque->version != JX_IDLE_COLLECT_VERSION) return 0;
    uint32_t expected = atomic_load_explicit(&deque->expected_answers, memory_order_acquire);
    return expected == 0u || atomic_load_explicit(&deque->answered, memory_order_acquire) >= expected;
}

uint32_t jx_idle_epoch_answered(const jx_idle_note_deque *deque) {
    if (!deque || deque->version != JX_IDLE_COLLECT_VERSION) return 0u;
    return atomic_load_explicit(&deque->answered, memory_order_acquire);
}

uint32_t jx_idle_epoch_data_answers(const jx_idle_note_deque *deque) {
    if (!deque || deque->version != JX_IDLE_COLLECT_VERSION) return 0u;
    return atomic_load_explicit(&deque->data_answers, memory_order_acquire);
}

uint32_t jx_idle_epoch_ack_count(const jx_idle_note_deque *deque) {
    if (!deque || deque->version != JX_IDLE_COLLECT_VERSION) return 0u;
    uint32_t head = atomic_load_explicit(&deque->ack_head, memory_order_acquire);
    uint32_t tail = atomic_load_explicit(&deque->ack_tail, memory_order_acquire);
    return ring_count(head, tail);
}

uint32_t jx_idle_epoch_data_count(const jx_idle_note_deque *deque) {
    if (!deque || deque->version != JX_IDLE_COLLECT_VERSION) return 0u;
    uint32_t head = atomic_load_explicit(&deque->data_head, memory_order_acquire);
    uint32_t tail = atomic_load_explicit(&deque->data_tail, memory_order_acquire);
    return ring_count(head, tail);
}

int jx_idle_collect_encode(uint8_t out[JX_IDLE_COLLECT_CALL_BYTES]) {
    if (!out) return -1;
    out[0] = JX_IDLE_CALL_SYSTEM;
    out[1] = JX_IDLE_CALL_BUS;
    out[2] = JX_IDLE_CALL_COLLECT;
    return 0;
}

int jx_idle_collect_is_call(const uint8_t *code, size_t length) {
    return code && length >= JX_IDLE_COLLECT_CALL_BYTES &&
           code[0] == JX_IDLE_CALL_SYSTEM &&
           code[1] == JX_IDLE_CALL_BUS &&
           code[2] == JX_IDLE_CALL_COLLECT;
}

int jx_idle_collect_run(jx_idle_note_deque *deque,
                        jx_idle_collect_fn collect,
                        void *context) {
    if (!deque || deque->version != JX_IDLE_COLLECT_VERSION || !collect) return -1;
    if (!jx_idle_collect_is_pending(deque)) return 0;
    if (!jx_idle_epoch_is_complete(deque)) return 0;

    int final_count = jx_idle_note_finalize_epoch(deque);
    if (final_count < 0) return final_count;
    if (atomic_exchange_explicit(&deque->collect_pending, 0u, memory_order_acq_rel) == 0u)
        return 0;

    int collected = 0;
    lock_deque(deque);
    uint32_t head = atomic_load_explicit(&deque->data_head, memory_order_relaxed);
    uint32_t tail = atomic_load_explicit(&deque->data_tail, memory_order_acquire);
    jx_idle_note batch[JX_IDLE_NOTE_CAPACITY];
    size_t count = 0u;
    while (head != tail && count < JX_IDLE_NOTE_CAPACITY) {
        batch[count++] = deque->data_notes[head];
        head = (head + 1u) % JX_IDLE_NOTE_CAPACITY;
    }
    atomic_store_explicit(&deque->data_head, head, memory_order_release);
    unlock_deque(deque);

    for (size_t i = 0; i < count; ++i) {
        int rc = collect(&batch[i], context);
        if (rc < 0) return rc;
        ++collected;
    }
    atomic_store_explicit(&deque->mode, JX_IDLE_NOTE_MODE_ACK, memory_order_release);
    return collected;
}
