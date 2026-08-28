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
    uint64_t id_sum;
    uint64_t epoch_sum;
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
    ++probe->count;
    probe->id_sum += note->program_id;
    probe->epoch_sum += note->epoch;
    return 0;
}

int main(void) {
    jx_idle_note_deque deque;
    jx_idle_note_deque_init(&deque);
    assert(deque.version == JX_IDLE_COLLECT_VERSION);
    assert(!jx_idle_collect_is_pending(&deque));

    uint8_t call[JX_IDLE_COLLECT_CALL_BYTES] = {0};
    const uint8_t expected[JX_IDLE_COLLECT_CALL_BYTES] = {0x7f, 0x00, 0x02};
    assert(jx_idle_collect_encode(call) == 0);
    assert(memcmp(call, expected, sizeof expected) == 0);
    assert(jx_idle_collect_is_call(call, sizeof call));
    assert(!jx_idle_collect_is_call(call, 2u));

    /* Four programs must all answer. Zeroes count, but never enter DATA list. */
    assert(jx_idle_note_begin_epoch(&deque, 1u, 4u) == 0);
    assert(jx_idle_note_publish(&deque, 1u, 1u, 0u) == 0);
    assert(jx_idle_note_publish(&deque, 2u, 1u, 0u) == 0);
    assert(jx_idle_epoch_answered(&deque) == 2u);
    assert(jx_idle_epoch_data_answers(&deque) == 0u);
    assert(!jx_idle_collect_is_pending(&deque));

    /* First 1 flips ACK -> DATA and arms bus #2 immediately. */
    assert(jx_idle_note_publish(&deque, 3u, 1u, 1u) == 2);
    assert(jx_idle_collect_is_pending(&deque));
    /* But collection waits because one program has not answered yet. */
    collect_probe probe = {0};
    assert(jx_idle_collect_run(&deque, collect_note, &probe) == 0);
    assert(probe.count == 0u);

    /* Last answer is also 1. Both ones must be drained together. */
    assert(jx_idle_note_publish(&deque, 4u, 1u, 1u) == 1);
    assert(jx_idle_epoch_is_complete(&deque));
    assert(jx_idle_epoch_data_answers(&deque) == 2u);
    assert(jx_idle_collect_run(&deque, collect_note, &probe) == 2);
    assert(probe.count == 2u);
    assert(probe.id_sum == 7u);
    assert(probe.epoch_sum == 2u);
    assert(!jx_idle_collect_is_pending(&deque));

    /* All-zero epoch completes without ever arming the second bus. */
    assert(jx_idle_note_begin_epoch(&deque, 2u, 3u) == 0);
    assert(jx_idle_note_publish(&deque, 10u, 2u, 0u) == 0);
    assert(jx_idle_note_publish(&deque, 11u, 2u, 0u) == 0);
    assert(jx_idle_note_publish(&deque, 12u, 2u, 0u) == 0);
    assert(jx_idle_epoch_is_complete(&deque));
    assert(jx_idle_epoch_data_answers(&deque) == 0u);
    assert(!jx_idle_collect_is_pending(&deque));

    /* Concurrent data producers: exactly one flips the atomic mode/arm edge,
     * but all 32 one-notes are collected as one completed epoch batch. */
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
    assert(jx_idle_collect_is_pending(&deque));
    assert(jx_idle_epoch_is_complete(&deque));
    assert(jx_idle_epoch_data_answers(&deque) == PRODUCERS);

    collect_probe concurrent = {0};
    assert(jx_idle_collect_run(&deque, collect_note, &concurrent) == PRODUCERS);
    assert(concurrent.count == PRODUCERS);
    assert(concurrent.epoch_sum == (uint64_t)PRODUCERS * 77u);
    assert(!jx_idle_collect_is_pending(&deque));

    puts("jx-idle-collect: atomic list flip + all ones collected in one batch");
    return 0;
}
