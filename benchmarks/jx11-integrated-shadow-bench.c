/* Integrated JX11 awake-window shadow benchmark.
 *
 * Measures the production-shaped WindowBag-register path:
 *   semantic baseline: event kind -> switch -> construct reaction identities
 *   integrated:        cached event reaction -> dirty byte + direct hot refs
 *
 * XCB/Cairo are deliberately excluded. This isolates dispatch/state-bookkeeping
 * cost and verifies identical reaction identities and dirty masks.
 *
 * Build (from repo root):
 *   cc -O2 -std=c11 -Wall -Wextra -Werror -Ihost/linux \
 *      -o jx11-integrated-shadow-bench \
 *      benchmarks/jx11-integrated-shadow-bench.c host/linux/jx11-window-hot.c
 */
#define _POSIX_C_SOURCE 200809L
#include <inttypes.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <time.h>
#include "jx11-window-hot.h"

#define MAX_WINDOWS 256u
#define EVENT_KINDS 5u

typedef struct {
    jx11_window_hot hot;
    uint8_t dirty;
} bench_window;

static bench_window hot_windows[MAX_WINDOWS];
static volatile uint64_t sink_u64;

static uint64_t now_ns(void) {
    struct timespec ts;
    if (clock_gettime(CLOCK_MONOTONIC, &ts) != 0) return 0;
    return (uint64_t)ts.tv_sec * 1000000000ull + (uint64_t)ts.tv_nsec;
}

static uint32_t prng_next(uint32_t *state) {
    uint32_t x = *state;
    x ^= x << 13;
    x ^= x >> 17;
    x ^= x << 5;
    *state = x;
    return x;
}

static inline uint32_t token(jx11_window_ref r) {
    return ((uint32_t)r.reg << 16) | (uint32_t)r.ref;
}

static uint64_t legacy_dispatch(uint8_t reg, uint8_t slot, uint8_t kind, uint8_t *dirty) {
    uint64_t sum = 0;
    switch (kind) {
        case JX11_EVENT_STATE_OPEN:
        case JX11_EVENT_STATE_CLOSE:
            *dirty |= (uint8_t)((1u << JX11_SHADOW_STATE) | (1u << JX11_SHADOW_TASKBAR));
            sum += token(jx11_window_ref_make(reg, slot, JX11_SHADOW_STATE));
            sum += token(jx11_window_ref_make(reg, slot, JX11_SHADOW_TASKBAR));
            break;
        case JX11_EVENT_TITLE:
            *dirty |= (uint8_t)((1u << JX11_SHADOW_TITLE) | (1u << JX11_SHADOW_TASKBAR));
            sum += token(jx11_window_ref_make(reg, slot, JX11_SHADOW_TITLE));
            sum += token(jx11_window_ref_make(reg, slot, JX11_SHADOW_TASKBAR));
            break;
        case JX11_EVENT_FOCUS:
            *dirty |= (uint8_t)((1u << JX11_SHADOW_FOCUS) | (1u << JX11_SHADOW_TASKBAR));
            sum += token(jx11_window_ref_make(reg, slot, JX11_SHADOW_FOCUS));
            sum += token(jx11_window_ref_make(reg, slot, JX11_SHADOW_TASKBAR));
            break;
        case JX11_EVENT_GEOMETRY:
            *dirty |= (uint8_t)(1u << JX11_SHADOW_GEOMETRY);
            sum += token(jx11_window_ref_make(reg, slot, JX11_SHADOW_GEOMETRY));
            break;
        default:
            break;
    }
    return sum;
}

static inline uint64_t integrated_dispatch(bench_window *w, uint8_t kind) {
    const jx11_window_reaction *reaction = jx11_window_hot_reaction(&w->hot, kind);
    if (!reaction) return 0;
    w->dirty |= reaction->mask;
    uint64_t sum = token(reaction->first);
    if (reaction->count == 2u) sum += token(reaction->second);
    return sum;
}

static double ns_per(uint64_t elapsed, uint64_t count) {
    return count ? (double)elapsed / (double)count : 0.0;
}

int main(int argc, char **argv) {
    uint64_t events = argc > 1 ? strtoull(argv[1], NULL, 10) : 1000000ull;
    uint32_t windows = argc > 2 ? (uint32_t)strtoul(argv[2], NULL, 10) : 256u;
    if (events < 1 || windows < 1 || windows > MAX_WINDOWS) {
        fputs("usage: jx11-integrated-shadow-bench [events>=1] [windows=1..256]\n", stderr);
        return 2;
    }

    uint8_t *slots = malloc((size_t)events);
    uint8_t *kinds = malloc((size_t)events);
    uint8_t legacy_dirty[MAX_WINDOWS] = {0};
    if (!slots || !kinds) {
        free(slots); free(kinds);
        fputs("jx11-integrated-shadow-bench: allocation failed\n", stderr);
        return 2;
    }

    const uint8_t reg = 0u;
    for (uint32_t slot = 0; slot < windows; ++slot) {
        hot_windows[slot].hot = jx11_window_hot_make(reg, (uint8_t)slot);
        hot_windows[slot].dirty = 0u;
    }

    uint32_t rng = 0x4a583131u; /* JX11 */
    for (uint64_t i = 0; i < events; ++i) {
        slots[i] = (uint8_t)(prng_next(&rng) % windows);
        kinds[i] = (uint8_t)(1u + (prng_next(&rng) % EVENT_KINDS));
    }

    uint64_t legacy_sum = 0;
    uint64_t t0 = now_ns();
    for (uint64_t i = 0; i < events; ++i) {
        uint8_t slot = slots[i];
        legacy_sum += legacy_dispatch(reg, slot, kinds[i], &legacy_dirty[slot]);
    }
    uint64_t legacy_ns = now_ns() - t0;

    uint64_t integrated_sum = 0;
    t0 = now_ns();
    for (uint64_t i = 0; i < events; ++i) {
        integrated_sum += integrated_dispatch(&hot_windows[slots[i]], kinds[i]);
    }
    uint64_t integrated_ns = now_ns() - t0;

    if (legacy_sum != integrated_sum) {
        fprintf(stderr, "jx11-integrated-shadow-bench: checksum mismatch (%" PRIu64 " != %" PRIu64 ")\n",
                legacy_sum, integrated_sum);
        free(slots); free(kinds);
        return 3;
    }
    for (uint32_t slot = 0; slot < windows; ++slot) {
        if (legacy_dirty[slot] != hot_windows[slot].dirty) {
            fprintf(stderr, "jx11-integrated-shadow-bench: dirty mask mismatch at slot %u\n", slot);
            free(slots); free(kinds);
            return 4;
        }
    }
    sink_u64 = integrated_sum;

    double speedup = integrated_ns ? (double)legacy_ns / (double)integrated_ns : 0.0;
    double reduction = legacy_ns ? 100.0 * (1.0 - (double)integrated_ns / (double)legacy_ns) : 0.0;

    printf("JX11 integrated shadow benchmark\n");
    printf("events=%" PRIu64 " windows=%u register=W%u\n", events, windows, reg);
    printf("legacy semantic path: %.3f ms (%.2f ns/event)\n", legacy_ns / 1e6, ns_per(legacy_ns, events));
    printf("integrated hot path: %.3f ms (%.2f ns/event)\n", integrated_ns / 1e6, ns_per(integrated_ns, events));
    printf("integrated speedup: %.2fx\n", speedup);
    printf("dispatch CPU reduction: %.2f%%\n", reduction);
    printf("checksum: %" PRIu64 "\n", integrated_sum);

    FILE *json = fopen("jx11-integrated-shadow-benchmark.json", "w");
    if (json) {
        fprintf(json,
            "{\n"
            "  \"events\": %" PRIu64 ",\n"
            "  \"windows\": %u,\n"
            "  \"legacy_ns\": %" PRIu64 ",\n"
            "  \"integrated_ns\": %" PRIu64 ",\n"
            "  \"speedup\": %.6f,\n"
            "  \"cpu_reduction_percent\": %.6f,\n"
            "  \"checksum\": %" PRIu64 "\n"
            "}\n",
            events, windows, legacy_ns, integrated_ns, speedup, reduction, integrated_sum);
        fclose(json);
    }

    free(slots); free(kinds);
    return 0;
}
