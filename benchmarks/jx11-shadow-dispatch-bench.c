/* JX11 prelinked shadow-dispatch benchmark.
 *
 * Compares two equivalent million-event CPU paths:
 *   legacy: event kind -> semantic switch -> construct W:[slot:shadow] refs
 *   prelinked: event kind + slot -> cached reaction pair -> consume refs
 *
 * This intentionally excludes XCB/Cairo cost. It measures only the dispatch
 * work the register/shadow cache is designed to remove from the awake loop.
 *
 * Build:
 *   cc -O3 -std=c11 -Wall -Wextra -Werror -o jx11-shadow-dispatch-bench \
 *      benchmarks/jx11-shadow-dispatch-bench.c
 * Run:
 *   ./jx11-shadow-dispatch-bench [events] [windows]
 */
#define _POSIX_C_SOURCE 200809L
#include <inttypes.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <time.h>

#define MAX_WINDOWS 256u
#define EVENT_KINDS 4u

enum {
    SHADOW_STATE = 0u,
    SHADOW_TASKBAR = 1u,
    SHADOW_TITLE = 2u,
    SHADOW_FOCUS = 3u,
    SHADOW_GEOMETRY = 4u
};

enum {
    EVENT_TITLE = 0u,
    EVENT_FOCUS = 1u,
    EVENT_GEOMETRY = 2u,
    EVENT_STATE = 3u
};

typedef struct {
    uint32_t first;
    uint32_t second;
    uint8_t count;
} reaction_pair;

static reaction_pair prelinked[MAX_WINDOWS][EVENT_KINDS];
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

/* 24-bit awake identity: register byte + packed [slot:shadow] uint16. */
static inline uint32_t token(uint8_t reg, uint8_t slot, uint8_t shadow) {
    return ((uint32_t)reg << 16) | ((uint32_t)slot << 8) | (uint32_t)shadow;
}

static reaction_pair legacy_resolve(uint8_t reg, uint8_t slot, uint8_t kind) {
    reaction_pair r;
    r.second = 0u;
    switch (kind) {
        case EVENT_TITLE:
            r.first = token(reg, slot, SHADOW_TITLE);
            r.second = token(reg, slot, SHADOW_TASKBAR);
            r.count = 2u;
            break;
        case EVENT_FOCUS:
            r.first = token(reg, slot, SHADOW_FOCUS);
            r.second = token(reg, slot, SHADOW_TASKBAR);
            r.count = 2u;
            break;
        case EVENT_GEOMETRY:
            r.first = token(reg, slot, SHADOW_GEOMETRY);
            r.count = 1u;
            break;
        default:
            r.first = token(reg, slot, SHADOW_STATE);
            r.count = 1u;
            break;
    }
    return r;
}

static void build_prelinked(uint8_t reg, uint32_t windows) {
    for (uint32_t slot = 0; slot < windows; ++slot) {
        prelinked[slot][EVENT_TITLE].first = token(reg, (uint8_t)slot, SHADOW_TITLE);
        prelinked[slot][EVENT_TITLE].second = token(reg, (uint8_t)slot, SHADOW_TASKBAR);
        prelinked[slot][EVENT_TITLE].count = 2u;

        prelinked[slot][EVENT_FOCUS].first = token(reg, (uint8_t)slot, SHADOW_FOCUS);
        prelinked[slot][EVENT_FOCUS].second = token(reg, (uint8_t)slot, SHADOW_TASKBAR);
        prelinked[slot][EVENT_FOCUS].count = 2u;

        prelinked[slot][EVENT_GEOMETRY].first = token(reg, (uint8_t)slot, SHADOW_GEOMETRY);
        prelinked[slot][EVENT_GEOMETRY].second = 0u;
        prelinked[slot][EVENT_GEOMETRY].count = 1u;

        prelinked[slot][EVENT_STATE].first = token(reg, (uint8_t)slot, SHADOW_STATE);
        prelinked[slot][EVENT_STATE].second = 0u;
        prelinked[slot][EVENT_STATE].count = 1u;
    }
}

static inline uint64_t consume_pair(reaction_pair r) {
    uint64_t sum = r.first;
    if (r.count == 2u) sum += r.second;
    return sum;
}

static double ns_per(uint64_t elapsed, uint64_t count) {
    return count ? (double)elapsed / (double)count : 0.0;
}

int main(int argc, char **argv) {
    uint64_t events = argc > 1 ? strtoull(argv[1], NULL, 10) : 1000000ull;
    uint32_t windows = argc > 2 ? (uint32_t)strtoul(argv[2], NULL, 10) : 256u;
    if (events < 1 || windows < 1 || windows > MAX_WINDOWS) {
        fputs("usage: jx11-shadow-dispatch-bench [events>=1] [windows=1..256]\n", stderr);
        return 2;
    }

    uint8_t *slots = malloc((size_t)events);
    uint8_t *kinds = malloc((size_t)events);
    if (!slots || !kinds) {
        free(slots); free(kinds);
        fputs("jx11-shadow-bench: allocation failed\n", stderr);
        return 2;
    }

    uint32_t rng = 0x53484457u; /* SHDW */
    for (uint64_t i = 0; i < events; ++i) {
        slots[i] = (uint8_t)(prng_next(&rng) % windows);
        kinds[i] = (uint8_t)(prng_next(&rng) & (EVENT_KINDS - 1u));
    }

    const uint8_t reg = 0u; /* W0 */
    build_prelinked(reg, windows);

    uint64_t legacy_sum = 0;
    uint64_t t0 = now_ns();
    for (uint64_t i = 0; i < events; ++i) {
        legacy_sum += consume_pair(legacy_resolve(reg, slots[i], kinds[i]));
    }
    uint64_t legacy_ns = now_ns() - t0;

    uint64_t prelinked_sum = 0;
    t0 = now_ns();
    for (uint64_t i = 0; i < events; ++i) {
        reaction_pair r = prelinked[slots[i]][kinds[i]];
        prelinked_sum += consume_pair(r);
    }
    uint64_t prelinked_ns = now_ns() - t0;

    if (legacy_sum != prelinked_sum) {
        fprintf(stderr, "jx11-shadow-bench: dispatch mismatch (%" PRIu64 " != %" PRIu64 ")\n",
                legacy_sum, prelinked_sum);
        free(slots); free(kinds);
        return 3;
    }
    sink_u64 = prelinked_sum;

    double gain = prelinked_ns ? (double)legacy_ns / (double)prelinked_ns : 0.0;
    double saved = legacy_ns ? 100.0 * (1.0 - (double)prelinked_ns / (double)legacy_ns) : 0.0;

    printf("JX11 shadow-dispatch benchmark\n");
    printf("events=%" PRIu64 " windows=%u register=W%u\n", events, windows, reg);
    printf("legacy semantic dispatch: %.3f ms (%.2f ns/event)\n",
           legacy_ns / 1e6, ns_per(legacy_ns, events));
    printf("prelinked shadow dispatch: %.3f ms (%.2f ns/event)\n",
           prelinked_ns / 1e6, ns_per(prelinked_ns, events));
    printf("shadow dispatch speedup: %.2fx\n", gain);
    printf("dispatch CPU reduction: %.2f%%\n", saved);
    printf("checksum: %" PRIu64 "\n", prelinked_sum);

    FILE *json = fopen("jx11-shadow-dispatch-benchmark.json", "w");
    if (json) {
        fprintf(json,
            "{\n"
            "  \"events\": %" PRIu64 ",\n"
            "  \"windows\": %u,\n"
            "  \"legacy_ns\": %" PRIu64 ",\n"
            "  \"prelinked_ns\": %" PRIu64 ",\n"
            "  \"speedup\": %.6f,\n"
            "  \"cpu_reduction_percent\": %.6f,\n"
            "  \"checksum\": %" PRIu64 "\n"
            "}\n",
            events, windows, legacy_ns, prelinked_ns, gain, saved, prelinked_sum);
        fclose(json);
    }

    free(slots); free(kinds);
    return 0;
}
