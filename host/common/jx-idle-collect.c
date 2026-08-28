#include "jx-idle-collect.h"
#include "jx-idle-bus.h"
#include <string.h>

static void lock_deque(jx_idle_note_deque *d) {
    uint32_t expected;
    for (;;) {
        expected = 0u;
        if (atomic_compare_exchange_weak_explicit(&d->lock, &expected, 1u,
                                                  memory_order_acquire,
                                                  memory_order_relaxed)) return;
    }
}

static void unlock_deque(jx_idle_note_deque *d) {
    atomic_store_explicit(&d->lock, 0u, memory_order_release);
}

static uint32_t ring_count(uint32_t head, uint32_t tail) {
    return tail >= head ? tail - head : (JX_IDLE_NOTE_CAPACITY - head) + tail;
}

void jx_idle_note_deque_init(jx_idle_note_deque *d) {
    if (!d) return;
    memset(d, 0, sizeof *d);
    d->version = JX_IDLE_COLLECT_VERSION;
    atomic_init(&d->lock, 0u);
    atomic_init(&d->collect_pending, 0u);
    atomic_init(&d->mode, JX_IDLE_NOTE_MODE_ACK);
    atomic_init(&d->ack_head, 0u);
    atomic_init(&d->ack_tail, 0u);
    atomic_init(&d->data_head, 0u);
    atomic_init(&d->data_tail, 0u);
    atomic_init(&d->expected_answers, 0u);
    atomic_init(&d->answered, 0u);
    atomic_init(&d->data_answers, 0u);
    atomic_init(&d->finalized, 0u);
    atomic_init(&d->epoch, 0u);
}

int jx_idle_note_begin_epoch(jx_idle_note_deque *d, uint64_t epoch, uint32_t expected_answers) {
    if (!d || d->version != JX_IDLE_COLLECT_VERSION || !epoch) return -1;
    if (!jx_idle_epoch_is_complete(d)) return -2;
    if (jx_idle_collect_is_pending(d)) return -3;

    lock_deque(d);
    atomic_store_explicit(&d->ack_head, 0u, memory_order_relaxed);
    atomic_store_explicit(&d->ack_tail, 0u, memory_order_relaxed);
    atomic_store_explicit(&d->data_head, 0u, memory_order_relaxed);
    atomic_store_explicit(&d->data_tail, 0u, memory_order_release);
    unlock_deque(d);

    atomic_store_explicit(&d->mode, JX_IDLE_NOTE_MODE_ACK, memory_order_release);
    atomic_store_explicit(&d->epoch, epoch, memory_order_release);
    atomic_store_explicit(&d->expected_answers, expected_answers, memory_order_release);
    atomic_store_explicit(&d->answered, 0u, memory_order_release);
    atomic_store_explicit(&d->data_answers, 0u, memory_order_release);
    atomic_store_explicit(&d->finalized, 0u, memory_order_release);
    return 0;
}

int jx_idle_note_publish(jx_idle_note_deque *d, uint32_t program_id, uint64_t epoch, uint8_t has_data) {
    if (!d || d->version != JX_IDLE_COLLECT_VERSION || !program_id || has_data > 1u) return -1;
    if (epoch != atomic_load_explicit(&d->epoch, memory_order_acquire)) return -3;
    if (atomic_load_explicit(&d->finalized, memory_order_acquire)) return -4;

    int armed = 0;
    if (has_data) {
        uint32_t ack_mode = JX_IDLE_NOTE_MODE_ACK;
        if (atomic_compare_exchange_strong_explicit(&d->mode, &ack_mode, JX_IDLE_NOTE_MODE_DATA,
                                                    memory_order_acq_rel, memory_order_acquire)) {
            uint32_t pending = 0u;
            if (atomic_compare_exchange_strong_explicit(&d->collect_pending, &pending, 1u,
                                                        memory_order_acq_rel, memory_order_acquire)) armed = 1;
        }
    }

    lock_deque(d);
    uint32_t head = atomic_load_explicit(&d->ack_head, memory_order_relaxed);
    uint32_t tail = atomic_load_explicit(&d->ack_tail, memory_order_relaxed);
    uint32_t next = (tail + 1u) % JX_IDLE_NOTE_CAPACITY;
    if (next == head) { unlock_deque(d); return -2; }

    uint32_t ack_ordinal = ring_count(head, tail);
    jx_idle_note *ack = &d->ack_notes[tail];
    ack->program_id = program_id;
    ack->ack_ordinal = ack_ordinal;
    ack->one_ordinal = JX_IDLE_ORDINAL_NONE;
    ack->reserved32 = 0u;
    ack->epoch = epoch;
    ack->has_data = has_data;
    memset(ack->reserved, 0, sizeof ack->reserved);
    atomic_store_explicit(&d->ack_tail, next, memory_order_release);
    unlock_deque(d);

    if (has_data) atomic_fetch_add_explicit(&d->data_answers, 1u, memory_order_acq_rel);
    atomic_fetch_add_explicit(&d->answered, 1u, memory_order_acq_rel);
    return has_data ? (armed ? 2 : 1) : 0;
}

int jx_idle_note_finalize_epoch(jx_idle_note_deque *d) {
    if (!d || d->version != JX_IDLE_COLLECT_VERSION) return -1;
    if (!jx_idle_epoch_is_complete(d)) return 0;

    uint32_t fresh = 0u;
    if (!atomic_compare_exchange_strong_explicit(&d->finalized, &fresh, 1u,
                                                 memory_order_acq_rel, memory_order_acquire))
        return (int)jx_idle_epoch_data_count(d);

    lock_deque(d);
    uint32_t ah = atomic_load_explicit(&d->ack_head, memory_order_relaxed);
    uint32_t at = atomic_load_explicit(&d->ack_tail, memory_order_acquire);
    uint32_t dt = 0u;
    uint32_t one = 0u;
    for (uint32_t p = ah; p != at; p = (p + 1u) % JX_IDLE_NOTE_CAPACITY) {
        jx_idle_note *ack = &d->ack_notes[p];
        if (!ack->has_data) continue;
        if (dt >= JX_IDLE_NOTE_CAPACITY - 1u) { unlock_deque(d); return -2; }
        ack->one_ordinal = one;
        d->data_notes[dt] = *ack;
        d->data_notes[dt].one_ordinal = one;
        ++dt;
        ++one;
    }
    atomic_store_explicit(&d->data_head, 0u, memory_order_relaxed);
    atomic_store_explicit(&d->data_tail, dt, memory_order_release);
    unlock_deque(d);
    return (int)dt;
}

int jx_idle_collect_is_pending(const jx_idle_note_deque *d) {
    return d && d->version == JX_IDLE_COLLECT_VERSION &&
           atomic_load_explicit(&d->collect_pending, memory_order_acquire) != 0u;
}

int jx_idle_epoch_is_complete(const jx_idle_note_deque *d) {
    if (!d || d->version != JX_IDLE_COLLECT_VERSION) return 0;
    uint32_t expected = atomic_load_explicit(&d->expected_answers, memory_order_acquire);
    return expected == 0u || atomic_load_explicit(&d->answered, memory_order_acquire) >= expected;
}

uint32_t jx_idle_epoch_answered(const jx_idle_note_deque *d) {
    return (!d || d->version != JX_IDLE_COLLECT_VERSION) ? 0u :
           atomic_load_explicit(&d->answered, memory_order_acquire);
}

uint32_t jx_idle_epoch_data_answers(const jx_idle_note_deque *d) {
    return (!d || d->version != JX_IDLE_COLLECT_VERSION) ? 0u :
           atomic_load_explicit(&d->data_answers, memory_order_acquire);
}

uint32_t jx_idle_epoch_ack_count(const jx_idle_note_deque *d) {
    if (!d || d->version != JX_IDLE_COLLECT_VERSION) return 0u;
    return ring_count(atomic_load_explicit(&d->ack_head, memory_order_acquire),
                      atomic_load_explicit(&d->ack_tail, memory_order_acquire));
}

uint32_t jx_idle_epoch_data_count(const jx_idle_note_deque *d) {
    if (!d || d->version != JX_IDLE_COLLECT_VERSION) return 0u;
    return ring_count(atomic_load_explicit(&d->data_head, memory_order_acquire),
                      atomic_load_explicit(&d->data_tail, memory_order_acquire));
}

int jx_idle_epoch_ack_at(const jx_idle_note_deque *d, uint32_t ordinal, jx_idle_note *out) {
    if (!d || d->version != JX_IDLE_COLLECT_VERSION || !out) return -1;
    uint32_t count = jx_idle_epoch_ack_count(d);
    if (ordinal >= count) return -2;
    uint32_t head = atomic_load_explicit(&d->ack_head, memory_order_acquire);
    *out = d->ack_notes[(head + ordinal) % JX_IDLE_NOTE_CAPACITY];
    return 0;
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
           code[0] == JX_IDLE_CALL_SYSTEM && code[1] == JX_IDLE_CALL_BUS &&
           code[2] == JX_IDLE_CALL_COLLECT;
}

int jx_idle_collect_run(jx_idle_note_deque *d, jx_idle_collect_fn collect, void *context) {
    if (!d || d->version != JX_IDLE_COLLECT_VERSION || !collect) return -1;
    if (!jx_idle_collect_is_pending(d) || !jx_idle_epoch_is_complete(d)) return 0;
    int final_count = jx_idle_note_finalize_epoch(d);
    if (final_count < 0) return final_count;
    if (atomic_exchange_explicit(&d->collect_pending, 0u, memory_order_acq_rel) == 0u) return 0;

    jx_idle_note batch[JX_IDLE_NOTE_CAPACITY];
    size_t count = 0u;
    lock_deque(d);
    uint32_t head = atomic_load_explicit(&d->data_head, memory_order_relaxed);
    uint32_t tail = atomic_load_explicit(&d->data_tail, memory_order_acquire);
    while (head != tail && count < JX_IDLE_NOTE_CAPACITY) {
        batch[count++] = d->data_notes[head];
        head = (head + 1u) % JX_IDLE_NOTE_CAPACITY;
    }
    atomic_store_explicit(&d->data_head, head, memory_order_release);
    unlock_deque(d);

    for (size_t i = 0; i < count; ++i) {
        if (batch[i].one_ordinal != (uint32_t)i) return -3;
        int rc = collect(&batch[i], context);
        if (rc < 0) return rc;
    }
    atomic_store_explicit(&d->mode, JX_IDLE_NOTE_MODE_ACK, memory_order_release);
    return (int)count;
}
