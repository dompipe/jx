#include "../host/common/jx-idle-collect.h"
#include <assert.h>
#include <pthread.h>
#include <stdatomic.h>
#include <stdio.h>
#include <string.h>

#define PRODUCERS 32

typedef struct {
    jx_idle_note_deque *deque;
    uint32_t program_id;
    _Atomic unsigned *armers;
} producer_arg;

typedef struct {
    unsigned count;
    uint32_t ids[JX_IDLE_NOTE_CAPACITY];
    uint32_t ack_ordinals[JX_IDLE_NOTE_CAPACITY];
    uint32_t one_ordinals[JX_IDLE_NOTE_CAPACITY];
} collect_probe;

static void *produce_one(void *opaque) {
    producer_arg *arg = (producer_arg *)opaque;
    int rc = jx_idle_note_publish(arg->deque, arg->program_id, 77u, 1u);
    assert(rc == 1 || rc == 2);
    if (rc == 2) atomic_fetch_add_explicit(arg->armers, 1u, memory_order_relaxed);
    return NULL;
}

static int collect_note(const jx_idle_note *note, void *opaque) {
    collect_probe *probe = (collect_probe *)opaque;
    assert(note->has_data == 1u);
    unsigned at = probe->count++;
    probe->ids[at] = note->program_id;
    probe->ack_ordinals[at] = note->ack_ordinal;
    probe->one_ordinals[at] = note->one_ordinal;
    return 0;
}

int main(void) {
    jx_idle_note_deque deque;
    jx_idle_note_deque_init(&deque);
    assert(deque.version == JX_IDLE_COLLECT_VERSION);

    uint8_t call[JX_IDLE_COLLECT_CALL_BYTES] = {0};
    const uint8_t expected_call[JX_IDLE_COLLECT_CALL_BYTES] = {0x7f, 0x00, 0x02};
    assert(jx_idle_collect_encode(call) == 0);
    assert(memcmp(call, expected_call, sizeof expected_call) == 0);

    /* Bus #1 returns the authoritative bit sequence 0,1,0,1,1. */
    assert(jx_idle_note_begin_epoch(&deque, 1u, 5u) == 0);
    assert(jx_idle_note_publish(&deque, 10u, 1u, 0u) == 0);
    assert(jx_idle_note_publish(&deque, 11u, 1u, 1u) == 2);
    assert(jx_idle_note_publish(&deque, 12u, 1u, 0u) == 0);
    assert(jx_idle_note_publish(&deque, 13u, 1u, 1u) == 1);
    assert(jx_idle_collect_run(&deque, collect_note, &(collect_probe){0}) == 0);
    assert(jx_idle_note_publish(&deque, 14u, 1u, 1u) == 1);
    assert(jx_idle_epoch_is_complete(&deque));
    assert(jx_idle_epoch_ack_count(&deque) == 5u);

    const uint8_t expected_bits[5] = {0u, 1u, 0u, 1u, 1u};
    for (uint32_t i = 0; i < 5u; ++i) {
        jx_idle_note ack;
        assert(jx_idle_epoch_ack_at(&deque, i, &ack) == 0);
        assert(ack.ack_ordinal == i);
        assert(ack.has_data == expected_bits[i]);
    }

    assert(jx_idle_note_finalize_epoch(&deque) == 3);
    assert(jx_idle_epoch_data_count(&deque) == 3u);

    collect_probe ordered = {0};
    assert(jx_idle_collect_run(&deque, collect_note, &ordered) == 3);
    assert(ordered.count == 3u);
    assert(ordered.ids[0] == 11u && ordered.ack_ordinals[0] == 1u && ordered.one_ordinals[0] == 0u);
    assert(ordered.ids[1] == 13u && ordered.ack_ordinals[1] == 3u && ordered.one_ordinals[1] == 1u);
    assert(ordered.ids[2] == 14u && ordered.ack_ordinals[2] == 4u && ordered.one_ordinals[2] == 2u);

    /* All zeroes still complete the wake sweep but never arm bus #2. */
    assert(jx_idle_note_begin_epoch(&deque, 2u, 3u) == 0);
    assert(jx_idle_note_publish(&deque, 20u, 2u, 0u) == 0);
    assert(jx_idle_note_publish(&deque, 21u, 2u, 0u) == 0);
    assert(jx_idle_note_publish(&deque, 22u, 2u, 0u) == 0);
    assert(jx_idle_epoch_is_complete(&deque));
    assert(jx_idle_epoch_ack_count(&deque) == 3u);
    assert(jx_idle_epoch_data_answers(&deque) == 0u);
    assert(!jx_idle_collect_is_pending(&deque));

    /* Concurrent ones: one atomic armer, one completed batch. Arrival order is
     * whatever bus #1 records under the shared lock; bus #2 must preserve it. */
    assert(jx_idle_note_begin_epoch(&deque, 77u, PRODUCERS) == 0);
    pthread_t threads[PRODUCERS];
    producer_arg args[PRODUCERS];
    _Atomic unsigned armers;
    atomic_init(&armers, 0u);
    for (unsigned i = 0; i < PRODUCERS; ++i) {
        args[i].deque = &deque;
        args[i].program_id = 100u + i;
        args[i].armers = &armers;
        assert(pthread_create(&threads[i], NULL, produce_one, &args[i]) == 0);
    }
    for (unsigned i = 0; i < PRODUCERS; ++i)
        assert(pthread_join(threads[i], NULL) == 0);

    assert(atomic_load_explicit(&armers, memory_order_relaxed) == 1u);
    assert(jx_idle_epoch_is_complete(&deque));
    assert(jx_idle_note_finalize_epoch(&deque) == PRODUCERS);

    collect_probe concurrent = {0};
    assert(jx_idle_collect_run(&deque, collect_note, &concurrent) == PRODUCERS);
    for (uint32_t i = 0; i < PRODUCERS; ++i) {
        jx_idle_note ack;
        assert(jx_idle_epoch_ack_at(&deque, i, &ack) == 0);
        assert(ack.has_data == 1u);
        assert(concurrent.ids[i] == ack.program_id);
        assert(concurrent.ack_ordinals[i] == i);
        assert(concurrent.one_ordinals[i] == i);
    }

    puts("jx-idle-collect: ACK bit order freezes all 1 ordinals for bus #2");
    return 0;
}
