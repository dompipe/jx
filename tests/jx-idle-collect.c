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

    /* Zero notes are still written into the deque but never arm bus #2. */
    assert(jx_idle_note_publish(&deque, 1u, 1u, 0u) == 0);
    assert(jx_idle_note_publish(&deque, 2u, 1u, 0u) == 0);
    assert(!jx_idle_collect_is_pending(&deque));

    /* The first one is the edge: 0 -> 1 arms collection immediately. */
    assert(jx_idle_note_publish(&deque, 3u, 1u, 1u) == 2);
    assert(jx_idle_collect_is_pending(&deque));
    /* Later ones join the already-armed sweep rather than starting another. */
    assert(jx_idle_note_publish(&deque, 4u, 1u, 1u) == 1);

    collect_probe probe = {0};
    assert(jx_idle_collect_run(&deque, collect_note, &probe) == 2);
    assert(probe.count == 2u);
    assert(probe.id_sum == 7u);
    assert(probe.epoch_sum == 2u);
    assert(!jx_idle_collect_is_pending(&deque));
    assert(jx_idle_collect_run(&deque, collect_note, &probe) == 0);

    /* Concurrent producers: exactly one may win the atomic arm edge. */
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

    collect_probe concurrent = {0};
    assert(jx_idle_collect_run(&deque, collect_note, &concurrent) == PRODUCERS);
    assert(concurrent.count == PRODUCERS);
    assert(concurrent.epoch_sum == (uint64_t)PRODUCERS * 77u);
    assert(!jx_idle_collect_is_pending(&deque));

    /* Once drained, the next data producer owns a fresh 0 -> 1 edge. */
    assert(jx_idle_note_publish(&deque, 999u, 78u, 1u) == 2);
    assert(jx_idle_collect_run(&deque, collect_note, &concurrent) == 1);

    puts("jx-idle-collect: first atomic 1 arms second bus; deque collection ok");
    return 0;
}
