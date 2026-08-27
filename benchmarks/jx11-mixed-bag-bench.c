/* JX11 mixed-bag benchmark.
 *
 * Compares complete CPU-side event pipelines on the same deterministic stream:
 *
 * baseline:
 *   XID -> linear scan -> semantic event switch -> construct hot refs
 *       -> mutate dense state -> repaint immediately when taskbar is dirty
 *
 * mixed hot path:
 *   XID -> hash index -> precomputed WindowBag reaction -> mutate dense state
 *       -> OR dirty mask -> repaint once per event burst
 *
 * XCB/Cairo painting itself is intentionally excluded; repaint counts are
 * reported separately. This benchmark measures the bookkeeping/dispatch work
 * surrounding those paints and validates equivalent state/reaction checksums.
 *
 * Build (repo root):
 *   cc -O2 -std=c11 -Wall -Wextra -Werror -Ihost/linux \
 *      -o jx11-mixed-bag-bench benchmarks/jx11-mixed-bag-bench.c \
 *      host/linux/jx11-window-hot.c
 *
 * Run:
 *   ./jx11-mixed-bag-bench [events] [windows] [burst]
 */
#define _POSIX_C_SOURCE 200809L
#include <inttypes.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>
#include "jx11-window-hot.h"

#define MAX_WINDOWS 256u
#define INDEX_SIZE 1024u
#define EVENT_KINDS 5u
#define DIRTY_TASKBAR (1u << JX11_SHADOW_TASKBAR)

typedef struct {
    uint32_t xid;
    uint32_t value;
    jx11_window_hot hot;
    uint8_t dirty;
} bench_window;

typedef struct {
    uint32_t xid;
    uint16_t slot;
} index_entry;

typedef struct {
    uint32_t xid;
    uint8_t kind;
} bench_event;

static bench_window rows[MAX_WINDOWS];
static index_entry xid_index[INDEX_SIZE];
static volatile uint64_t sink_u64;

static uint64_t now_ns(void) {
    struct timespec ts;
    if (clock_gettime(CLOCK_MONOTONIC, &ts) != 0) return 0;
    return (uint64_t)ts.tv_sec * 1000000000ull + (uint64_t)ts.tv_nsec;
}

static uint32_t mix32(uint32_t x) {
    x ^= x >> 16;
    x *= 0x7feb352du;
    x ^= x >> 15;
    x *= 0x846ca68bu;
    x ^= x >> 16;
    return x;
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

static void index_clear(void) { memset(xid_index, 0, sizeof xid_index); }

static void index_put(uint32_t xid, uint16_t slot) {
    uint32_t at = mix32(xid) & (INDEX_SIZE - 1u);
    for (uint32_t probe = 0; probe < INDEX_SIZE; ++probe) {
        index_entry *e = &xid_index[(at + probe) & (INDEX_SIZE - 1u)];
        if (e->xid == 0u || e->xid == xid) {
            e->xid = xid;
            e->slot = slot;
            return;
        }
    }
    fputs("jx11-mixed-bag-bench: XID index exhausted\n", stderr);
    exit(2);
}

static int linear_find(uint32_t xid, uint32_t windows) {
    for (uint32_t i = 0; i < windows; ++i) if (rows[i].xid == xid) return (int)i;
    return -1;
}

static int indexed_find(uint32_t xid) {
    uint32_t at = mix32(xid) & (INDEX_SIZE - 1u);
    for (uint32_t probe = 0; probe < INDEX_SIZE; ++probe) {
        const index_entry *e = &xid_index[(at + probe) & (INDEX_SIZE - 1u)];
        if (e->xid == xid) return (int)e->slot;
        if (e->xid == 0u) return -1;
    }
    return -1;
}

static uint64_t semantic_dispatch(uint8_t reg, uint8_t slot, uint8_t kind, uint8_t *dirty) {
    uint64_t sum = 0;
    switch (kind) {
        case JX11_EVENT_STATE_OPEN:
        case JX11_EVENT_STATE_CLOSE:
            *dirty |= (uint8_t)((1u << JX11_SHADOW_STATE) | DIRTY_TASKBAR);
            sum += token(jx11_window_ref_make(reg, slot, JX11_SHADOW_STATE));
            sum += token(jx11_window_ref_make(reg, slot, JX11_SHADOW_TASKBAR));
            break;
        case JX11_EVENT_TITLE:
            *dirty |= (uint8_t)((1u << JX11_SHADOW_TITLE) | DIRTY_TASKBAR);
            sum += token(jx11_window_ref_make(reg, slot, JX11_SHADOW_TITLE));
            sum += token(jx11_window_ref_make(reg, slot, JX11_SHADOW_TASKBAR));
            break;
        case JX11_EVENT_FOCUS:
            *dirty |= (uint8_t)((1u << JX11_SHADOW_FOCUS) | DIRTY_TASKBAR);
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

static inline uint64_t cached_dispatch(bench_window *w, uint8_t kind) {
    const jx11_window_reaction *reaction = jx11_window_hot_reaction(&w->hot, kind);
    if (!reaction) return 0;
    w->dirty |= reaction->mask;
    uint64_t sum = token(reaction->first);
    if (reaction->count == 2u) sum += token(reaction->second);
    return sum;
}

static void reset_values(uint32_t windows) {
    for (uint32_t i = 0; i < windows; ++i) {
        rows[i].value = i;
        rows[i].dirty = 0u;
    }
}

static double ns_per(uint64_t elapsed, uint64_t events) {
    return events ? (double)elapsed / (double)events : 0.0;
}

int main(int argc, char **argv) {
    uint64_t events = argc > 1 ? strtoull(argv[1], NULL, 10) : 1000000ull;
    uint32_t windows = argc > 2 ? (uint32_t)strtoul(argv[2], NULL, 10) : 256u;
    uint32_t burst = argc > 3 ? (uint32_t)strtoul(argv[3], NULL, 10) : 16u;
    if (events < 1 || windows < 1 || windows > MAX_WINDOWS || burst < 1 || burst > 65536u) {
        fputs("usage: jx11-mixed-bag-bench [events>=1] [windows=1..256] [burst>=1]\n", stderr);
        return 2;
    }

    bench_event *stream = malloc((size_t)events * sizeof *stream);
    if (!stream) {
        fputs("jx11-mixed-bag-bench: event allocation failed\n", stderr);
        return 2;
    }

    const uint8_t reg = 0u;
    index_clear();
    for (uint32_t i = 0; i < windows; ++i) {
        rows[i].xid = 0x100001u + i * 4099u;
        rows[i].hot = jx11_window_hot_make(reg, (uint8_t)i);
        rows[i].value = i;
        rows[i].dirty = 0u;
        index_put(rows[i].xid, (uint16_t)i);
    }

    uint32_t rng = 0x4d495845u; /* MIXE */
    for (uint64_t i = 0; i < events; ++i) {
        uint32_t slot = prng_next(&rng) % windows;
        stream[i].xid = rows[slot].xid;
        stream[i].kind = (uint8_t)(1u + (prng_next(&rng) % EVENT_KINDS));
    }

    uint8_t baseline_dirty[MAX_WINDOWS] = {0};
    uint64_t baseline_sum = 0;
    uint64_t baseline_paints = 0;
    uint64_t t0 = now_ns();
    for (uint64_t i = 0; i < events; ++i) {
        int slot = linear_find(stream[i].xid, windows);
        if (slot < 0) { free(stream); return 3; }
        rows[(uint32_t)slot].value += 1u;
        uint8_t event_dirty = 0u;
        baseline_sum += (uint64_t)rows[(uint32_t)slot].value;
        baseline_sum += semantic_dispatch(reg, (uint8_t)slot, stream[i].kind, &event_dirty);
        baseline_dirty[(uint32_t)slot] |= event_dirty;
        if (event_dirty & DIRTY_TASKBAR) ++baseline_paints;
    }
    uint64_t baseline_ns = now_ns() - t0;

    reset_values(windows);
    uint64_t mixed_sum = 0;
    uint64_t mixed_paints = 0;
    uint64_t bursts = 0;
    t0 = now_ns();
    for (uint64_t base = 0; base < events; base += burst) {
        uint64_t end = base + burst;
        if (end > events) end = events;
        uint8_t batch_dirty = 0u;
        ++bursts;
        for (uint64_t i = base; i < end; ++i) {
            int slot = indexed_find(stream[i].xid);
            if (slot < 0) { free(stream); return 3; }
            bench_window *w = &rows[(uint32_t)slot];
            w->value += 1u;
            uint8_t before = w->dirty;
            mixed_sum += (uint64_t)w->value;
            mixed_sum += cached_dispatch(w, stream[i].kind);
            batch_dirty |= (uint8_t)(w->dirty ^ before);
        }
        if (batch_dirty & DIRTY_TASKBAR) ++mixed_paints;
    }
    uint64_t mixed_ns = now_ns() - t0;

    if (baseline_sum != mixed_sum) {
        fprintf(stderr, "jx11-mixed-bag-bench: checksum mismatch (%" PRIu64 " != %" PRIu64 ")\n",
                baseline_sum, mixed_sum);
        free(stream);
        return 4;
    }
    for (uint32_t i = 0; i < windows; ++i) {
        if (baseline_dirty[i] != rows[i].dirty) {
            fprintf(stderr, "jx11-mixed-bag-bench: dirty mismatch at slot %u\n", i);
            free(stream);
            return 5;
        }
    }

    sink_u64 = mixed_sum ^ mixed_paints;
    double speedup = mixed_ns ? (double)baseline_ns / (double)mixed_ns : 0.0;
    double cpu_reduction = baseline_ns ? 100.0 * (1.0 - (double)mixed_ns / (double)baseline_ns) : 0.0;
    double paint_reduction = baseline_paints ? 100.0 * (1.0 - (double)mixed_paints / (double)baseline_paints) : 0.0;

    printf("JX11 mixed-bag benchmark\n");
    printf("events=%" PRIu64 " windows=%u burst=%u bursts=%" PRIu64 "\n", events, windows, burst, bursts);
    printf("baseline full pipeline: %.3f ms (%.2f ns/event)\n", baseline_ns / 1e6, ns_per(baseline_ns, events));
    printf("mixed hot pipeline:    %.3f ms (%.2f ns/event)\n", mixed_ns / 1e6, ns_per(mixed_ns, events));
    printf("mixed CPU speedup: %.2fx\n", speedup);
    printf("mixed CPU reduction: %.2f%%\n", cpu_reduction);
    printf("taskbar paint requests: %" PRIu64 " -> %" PRIu64 " (%.2f%% reduction)\n",
           baseline_paints, mixed_paints, paint_reduction);
    printf("checksum: %" PRIu64 "\n", mixed_sum);

    char json_name[128];
    snprintf(json_name, sizeof json_name, "jx11-mixed-bag-benchmark-b%u.json", burst);
    FILE *json = fopen(json_name, "w");
    if (json) {
        fprintf(json,
            "{\n"
            "  \"events\": %" PRIu64 ",\n"
            "  \"windows\": %u,\n"
            "  \"burst\": %u,\n"
            "  \"baseline_ns\": %" PRIu64 ",\n"
            "  \"mixed_ns\": %" PRIu64 ",\n"
            "  \"cpu_speedup\": %.6f,\n"
            "  \"cpu_reduction_percent\": %.6f,\n"
            "  \"baseline_paints\": %" PRIu64 ",\n"
            "  \"mixed_paints\": %" PRIu64 ",\n"
            "  \"paint_reduction_percent\": %.6f,\n"
            "  \"checksum\": %" PRIu64 "\n"
            "}\n",
            events, windows, burst, baseline_ns, mixed_ns, speedup, cpu_reduction,
            baseline_paints, mixed_paints, paint_reduction, mixed_sum);
        fclose(json);
    }

    free(stream);
    return 0;
}
