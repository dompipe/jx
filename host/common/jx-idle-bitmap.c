#include "jx-idle-bitmap.h"
#include <string.h>

static uint32_t popcount64(uint64_t x) {
#if defined(__GNUC__) || defined(__clang__)
    return (uint32_t)__builtin_popcountll((unsigned long long)x);
#else
    uint32_t count = 0u;
    while (x) { x &= x - 1u; ++count; }
    return count;
#endif
}

void jx_idle_bitmap_init(jx_idle_bitmap *bitmap) {
    if (!bitmap) return;
    memset(bitmap, 0, sizeof *bitmap);
    bitmap->version = JX_IDLE_BITMAP_VERSION;
    atomic_init(&bitmap->epoch, 0u);
    atomic_init(&bitmap->program_count, 0u);
    atomic_init(&bitmap->collect_pending, 0u);
    for (size_t i = 0; i < JX_IDLE_BITMAP_WORDS; ++i) {
        atomic_init(&bitmap->answered[i], 0u);
        atomic_init(&bitmap->data[i], 0u);
    }
}

int jx_idle_bitmap_begin(jx_idle_bitmap *bitmap,
                         uint64_t epoch,
                         uint32_t program_count) {
    if (!bitmap || bitmap->version != JX_IDLE_BITMAP_VERSION || !epoch ||
        program_count > JX_IDLE_BITMAP_MAX_PROGRAMS) return -1;
    if (atomic_load_explicit(&bitmap->collect_pending, memory_order_acquire)) return -2;

    memset(bitmap->expected, 0, sizeof bitmap->expected);
    uint32_t full_words = program_count / JX_IDLE_BITMAP_WORD_BITS;
    uint32_t remainder = program_count % JX_IDLE_BITMAP_WORD_BITS;
    for (uint32_t i = 0; i < full_words; ++i) bitmap->expected[i] = UINT64_MAX;
    if (remainder) bitmap->expected[full_words] = (UINT64_C(1) << remainder) - 1u;

    for (size_t i = 0; i < JX_IDLE_BITMAP_WORDS; ++i) {
        atomic_store_explicit(&bitmap->answered[i], 0u, memory_order_relaxed);
        atomic_store_explicit(&bitmap->data[i], 0u, memory_order_relaxed);
    }
    atomic_store_explicit(&bitmap->program_count, program_count, memory_order_release);
    atomic_store_explicit(&bitmap->epoch, epoch, memory_order_release);
    return 0;
}

int jx_idle_bitmap_reply(jx_idle_bitmap *bitmap,
                         uint64_t epoch,
                         uint32_t program_ordinal,
                         uint8_t has_data) {
    if (!bitmap || bitmap->version != JX_IDLE_BITMAP_VERSION || has_data > 1u)
        return -1;
    if (epoch != atomic_load_explicit(&bitmap->epoch, memory_order_acquire)) return -2;
    uint32_t count = atomic_load_explicit(&bitmap->program_count, memory_order_acquire);
    if (program_ordinal >= count) return -3;

    uint32_t word = program_ordinal / JX_IDLE_BITMAP_WORD_BITS;
    uint32_t bit = program_ordinal % JX_IDLE_BITMAP_WORD_BITS;
    uint64_t mask = UINT64_C(1) << bit;

    uint64_t prior_answered = atomic_fetch_or_explicit(&bitmap->answered[word], mask,
                                                       memory_order_acq_rel);
    if (prior_answered & mask) return -4;

    if (!has_data) return 0;

    atomic_fetch_or_explicit(&bitmap->data[word], mask, memory_order_release);
    uint32_t expected = 0u;
    if (atomic_compare_exchange_strong_explicit(&bitmap->collect_pending,
                                                &expected, 1u,
                                                memory_order_acq_rel,
                                                memory_order_acquire))
        return 2;
    return 1;
}

int jx_idle_bitmap_complete(const jx_idle_bitmap *bitmap) {
    if (!bitmap || bitmap->version != JX_IDLE_BITMAP_VERSION) return 0;
    for (size_t i = 0; i < JX_IDLE_BITMAP_WORDS; ++i) {
        uint64_t answered = atomic_load_explicit(&bitmap->answered[i], memory_order_acquire);
        if ((answered & bitmap->expected[i]) != bitmap->expected[i]) return 0;
    }
    return 1;
}

int jx_idle_bitmap_collect_pending(const jx_idle_bitmap *bitmap) {
    if (!bitmap || bitmap->version != JX_IDLE_BITMAP_VERSION) return 0;
    return atomic_load_explicit(&bitmap->collect_pending, memory_order_acquire) != 0u;
}

uint32_t jx_idle_bitmap_data_count(const jx_idle_bitmap *bitmap) {
    if (!bitmap || bitmap->version != JX_IDLE_BITMAP_VERSION) return 0u;
    uint32_t total = 0u;
    for (size_t i = 0; i < JX_IDLE_BITMAP_WORDS; ++i)
        total += popcount64(atomic_load_explicit(&bitmap->data[i], memory_order_acquire));
    return total;
}

int jx_idle_bitmap_snapshot(const jx_idle_bitmap *bitmap,
                            uint64_t data_out[JX_IDLE_BITMAP_WORDS],
                            uint64_t answered_out[JX_IDLE_BITMAP_WORDS],
                            uint64_t *epoch_out,
                            uint32_t *program_count_out) {
    if (!bitmap || bitmap->version != JX_IDLE_BITMAP_VERSION) return -1;
    for (size_t i = 0; i < JX_IDLE_BITMAP_WORDS; ++i) {
        if (data_out) data_out[i] = atomic_load_explicit(&bitmap->data[i], memory_order_acquire);
        if (answered_out) answered_out[i] = atomic_load_explicit(&bitmap->answered[i], memory_order_acquire);
    }
    if (epoch_out) *epoch_out = atomic_load_explicit(&bitmap->epoch, memory_order_acquire);
    if (program_count_out) *program_count_out = atomic_load_explicit(&bitmap->program_count, memory_order_acquire);
    return 0;
}

int jx_idle_bitmap_collect(jx_idle_bitmap *bitmap,
                           jx_idle_bitmap_collect_fn collect,
                           void *context) {
    if (!bitmap || bitmap->version != JX_IDLE_BITMAP_VERSION || !collect) return -1;
    if (!jx_idle_bitmap_complete(bitmap)) return 0;
    if (atomic_exchange_explicit(&bitmap->collect_pending, 0u, memory_order_acq_rel) == 0u)
        return 0;

    uint64_t epoch = atomic_load_explicit(&bitmap->epoch, memory_order_acquire);
    uint32_t program_count = atomic_load_explicit(&bitmap->program_count, memory_order_acquire);
    uint32_t one_ordinal = 0u;
    int collected = 0;

    for (uint32_t ordinal = 0u; ordinal < program_count; ++ordinal) {
        uint32_t word = ordinal / JX_IDLE_BITMAP_WORD_BITS;
        uint32_t bit = ordinal % JX_IDLE_BITMAP_WORD_BITS;
        uint64_t mask = UINT64_C(1) << bit;
        uint64_t data_word = atomic_load_explicit(&bitmap->data[word], memory_order_acquire);
        if (!(data_word & mask)) continue;
        int rc = collect(ordinal, one_ordinal, epoch, context);
        if (rc < 0) return rc;
        ++one_ordinal;
        ++collected;
    }
    return collected;
}
