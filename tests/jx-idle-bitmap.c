#include "../host/common/jx-idle-bitmap.h"
#include <assert.h>
#include <pthread.h>
#include <stdatomic.h>
#include <stdio.h>
#include <string.h>

#define PROGRAMS 8u

typedef struct {
    jx_idle_bitmap *bitmap;
    uint32_t ordinal;
    uint8_t has_data;
    _Atomic unsigned *armers;
} reply_arg;

typedef struct {
    uint32_t program[PROGRAMS];
    uint32_t one[PROGRAMS];
    unsigned count;
} collect_probe;

static void *reply_thread(void *opaque) {
    reply_arg *arg = (reply_arg *)opaque;
    int rc = jx_idle_bitmap_reply(arg->bitmap, 9u, arg->ordinal, arg->has_data);
    assert(rc >= 0);
    if (rc == 2) atomic_fetch_add_explicit(arg->armers, 1u, memory_order_relaxed);
    return NULL;
}

static int collect_one(uint32_t program_ordinal,
                       uint32_t one_ordinal,
                       uint64_t epoch,
                       void *opaque) {
    collect_probe *probe = (collect_probe *)opaque;
    assert(epoch == 9u);
    assert(probe->count < PROGRAMS);
    probe->program[probe->count] = program_ordinal;
    probe->one[probe->count] = one_ordinal;
    ++probe->count;
    return 0;
}

int main(void) {
    jx_idle_bitmap bitmap;
    jx_idle_bitmap_init(&bitmap);
    assert(bitmap.version == JX_IDLE_BITMAP_VERSION);
    assert(jx_idle_bitmap_begin(&bitmap, 9u, PROGRAMS) == 0);

    /* Program-order response bitstring: 01011001 (bits 1,3,4,7 are ones).
     * Threads deliberately arrive in a different order. */
    const uint8_t bits[PROGRAMS] = {0u,1u,0u,1u,1u,0u,0u,1u};
    const uint32_t arrival[PROGRAMS] = {7u,2u,4u,0u,6u,1u,5u,3u};
    pthread_t threads[PROGRAMS];
    reply_arg args[PROGRAMS];
    _Atomic unsigned armers;
    atomic_init(&armers, 0u);

    for (unsigned i = 0; i < PROGRAMS; ++i) {
        uint32_t ordinal = arrival[i];
        args[i].bitmap = &bitmap;
        args[i].ordinal = ordinal;
        args[i].has_data = bits[ordinal];
        args[i].armers = &armers;
        assert(pthread_create(&threads[i], NULL, reply_thread, &args[i]) == 0);
    }
    for (unsigned i = 0; i < PROGRAMS; ++i)
        assert(pthread_join(threads[i], NULL) == 0);

    assert(atomic_load_explicit(&armers, memory_order_relaxed) == 1u);
    assert(jx_idle_bitmap_complete(&bitmap));
    assert(jx_idle_bitmap_collect_pending(&bitmap));
    assert(jx_idle_bitmap_data_count(&bitmap) == 4u);

    uint64_t data[JX_IDLE_BITMAP_WORDS] = {0};
    uint64_t answered[JX_IDLE_BITMAP_WORDS] = {0};
    uint64_t epoch = 0u;
    uint32_t count = 0u;
    assert(jx_idle_bitmap_snapshot(&bitmap, data, answered, &epoch, &count) == 0);
    assert(epoch == 9u && count == PROGRAMS);
    assert((answered[0] & UINT64_C(0xff)) == UINT64_C(0xff));
    assert((data[0] & UINT64_C(0xff)) == UINT64_C(0x9a)); /* bit0 first: 01011001 */

    collect_probe probe = {0};
    assert(jx_idle_bitmap_collect(&bitmap, collect_one, &probe) == 4);
    assert(probe.count == 4u);
    const uint32_t expected_programs[4] = {1u,3u,4u,7u};
    for (uint32_t i = 0; i < 4u; ++i) {
        assert(probe.program[i] == expected_programs[i]);
        assert(probe.one[i] == i);
    }
    assert(!jx_idle_bitmap_collect_pending(&bitmap));

    /* A zero is still a real reply: until all four ANSWERED bits arrive the
     * second bus cannot collect, even if a 1 has already armed it. */
    assert(jx_idle_bitmap_begin(&bitmap, 10u, 4u) == 0);
    assert(jx_idle_bitmap_reply(&bitmap, 10u, 1u, 1u) == 2);
    assert(jx_idle_bitmap_reply(&bitmap, 10u, 0u, 0u) == 0);
    assert(!jx_idle_bitmap_complete(&bitmap));
    assert(jx_idle_bitmap_collect(&bitmap, collect_one, &probe) == 0);
    assert(jx_idle_bitmap_reply(&bitmap, 10u, 2u, 0u) == 0);
    assert(jx_idle_bitmap_reply(&bitmap, 10u, 3u, 0u) == 0);
    assert(jx_idle_bitmap_complete(&bitmap));

    puts("jx-idle-bitmap: ordered atomic 0/1 bus string + stable one ordinals ok");
    return 0;
}
