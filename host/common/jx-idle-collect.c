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

void jx_idle_note_deque_init(jx_idle_note_deque *deque) {
    if (!deque) return;
    memset(deque, 0, sizeof *deque);
    deque->version = JX_IDLE_COLLECT_VERSION;
    atomic_init(&deque->lock, 0u);
    atomic_init(&deque->collect_pending, 0u);
    atomic_init(&deque->head, 0u);
    atomic_init(&deque->tail, 0u);
}

int jx_idle_note_publish(jx_idle_note_deque *deque,
                         uint32_t program_id,
                         uint64_t epoch,
                         uint8_t has_data) {
    if (!deque || deque->version != JX_IDLE_COLLECT_VERSION || !program_id || has_data > 1u)
        return -1;

    lock_deque(deque);
    uint32_t head = atomic_load_explicit(&deque->head, memory_order_relaxed);
    uint32_t tail = atomic_load_explicit(&deque->tail, memory_order_relaxed);
    uint32_t next = (tail + 1u) % JX_IDLE_NOTE_CAPACITY;
    if (next == head) {
        unlock_deque(deque);
        return -2;
    }
    jx_idle_note *note = &deque->notes[tail];
    note->program_id = program_id;
    note->epoch = epoch;
    note->has_data = has_data;
    memset(note->reserved, 0, sizeof note->reserved);
    atomic_store_explicit(&deque->tail, next, memory_order_release);
    unlock_deque(deque);

    if (!has_data) return 0;

    uint32_t expected = 0u;
    if (atomic_compare_exchange_strong_explicit(&deque->collect_pending,
                                                &expected,
                                                1u,
                                                memory_order_acq_rel,
                                                memory_order_acquire))
        return 2;
    return 1;
}

int jx_idle_collect_is_pending(const jx_idle_note_deque *deque) {
    if (!deque || deque->version != JX_IDLE_COLLECT_VERSION) return 0;
    return atomic_load_explicit(&deque->collect_pending, memory_order_acquire) != 0u;
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

    /* Claim the armed edge first. A producer that publishes a 1 after this
     * exchange observes 0 and re-arms the next collection sweep. */
    if (atomic_exchange_explicit(&deque->collect_pending, 0u, memory_order_acq_rel) == 0u)
        return 0;

    jx_idle_note batch[JX_IDLE_NOTE_CAPACITY];
    size_t count = 0u;

    lock_deque(deque);
    uint32_t head = atomic_load_explicit(&deque->head, memory_order_relaxed);
    uint32_t tail = atomic_load_explicit(&deque->tail, memory_order_acquire);
    while (head != tail && count < JX_IDLE_NOTE_CAPACITY) {
        batch[count++] = deque->notes[head];
        head = (head + 1u) % JX_IDLE_NOTE_CAPACITY;
    }
    atomic_store_explicit(&deque->head, head, memory_order_release);
    unlock_deque(deque);

    int collected = 0;
    for (size_t i = 0; i < count; ++i) {
        if (!batch[i].has_data) continue;
        int rc = collect(&batch[i], context);
        if (rc < 0) return rc;
        ++collected;
    }
    return collected;
}
