/* JX11 hot-path benchmark.
 *
 * Measures CPU-side lookup/dispatch mechanics without requiring an X server.
 * Render coalescing reports exact paint-count reduction separately; it does not
 * pretend a synthetic counter has Cairo/X11's rendering cost.
 *
 * Build: cc -O3 -std=c11 -Wall -Wextra -Werror -o jx11-hotpath-bench benchmarks/jx11-hotpath-bench.c
 * Run:   ./jx11-hotpath-bench [events] [windows] [burst]
 */
#define _POSIX_C_SOURCE 200809L
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>
#include <inttypes.h>

#define MAX_WINDOWS 256u
#define INDEX_SIZE 1024u
#define EMPTY_XID 0u

typedef struct {
    uint32_t xid;
    uint32_t slot;
} index_entry;

typedef struct {
    uint32_t xid;
    uint32_t value;
} window_row;

static index_entry xid_index[INDEX_SIZE];
static window_row rows[MAX_WINDOWS];
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

static void index_clear(void) {
    memset(xid_index, 0, sizeof xid_index);
}

static void index_put(uint32_t xid, uint32_t slot) {
    uint32_t pos = mix32(xid) & (INDEX_SIZE - 1u);
    for (uint32_t n = 0; n < INDEX_SIZE; ++n) {
        index_entry *e = &xid_index[pos];
        if (e->xid == EMPTY_XID || e->xid == xid) {
            e->xid = xid;
            e->slot = slot;
            return;
        }
        pos = (pos + 1u) & (INDEX_SIZE - 1u);
    }
    fputs("jx11-bench: hash index full\n", stderr);
    exit(2);
}

static int linear_find(uint32_t xid, uint32_t count) {
    for (uint32_t i = 0; i < count; ++i) {
        if (rows[i].xid == xid) return (int)i;
    }
    return -1;
}

static int indexed_find(uint32_t xid) {
    uint32_t pos = mix32(xid) & (INDEX_SIZE - 1u);
    for (uint32_t n = 0; n < INDEX_SIZE; ++n) {
        const index_entry *e = &xid_index[pos];
        if (e->xid == xid) return (int)e->slot;
        if (e->xid == EMPTY_XID) return -1;
        pos = (pos + 1u) & (INDEX_SIZE - 1u);
    }
    return -1;
}

static double ns_per(uint64_t elapsed, uint64_t n) {
    return n ? (double)elapsed / (double)n : 0.0;
}

static double speedup(uint64_t old_ns, uint64_t new_ns) {
    return new_ns ? (double)old_ns / (double)new_ns : 0.0;
}

int main(int argc, char **argv) {
    uint64_t events = argc > 1 ? strtoull(argv[1], NULL, 10) : 1000000ull;
    uint32_t windows = argc > 2 ? (uint32_t)strtoul(argv[2], NULL, 10) : 256u;
    uint32_t burst = argc > 3 ? (uint32_t)strtoul(argv[3], NULL, 10) : 16u;
    if (events < 1 || windows < 1 || windows > MAX_WINDOWS || burst < 1 || burst > 65536u) {
        fputs("usage: jx11-hotpath-bench [events>=1] [windows=1..256] [burst>=1]\n", stderr);
        return 2;
    }

    index_clear();
    for (uint32_t i = 0; i < windows; ++i) {
        rows[i].xid = 0x100000u + i * 4099u + 1u;
        rows[i].value = i;
        index_put(rows[i].xid, i);
    }

    uint32_t *event_slots = malloc((size_t)events * sizeof *event_slots);
    if (!event_slots) {
        fputs("jx11-bench: event allocation failed\n", stderr);
        return 2;
    }
    uint32_t rng = 0x4a583131u; /* 'JX11' */
    for (uint64_t i = 0; i < events; ++i) event_slots[i] = prng_next(&rng) % windows;

    uint64_t checksum_linear = 0;
    uint64_t t0 = now_ns();
    for (uint64_t i = 0; i < events; ++i) {
        uint32_t xid = rows[event_slots[i]].xid;
        int slot = linear_find(xid, windows);
        checksum_linear += (uint64_t)(slot + 1);
    }
    uint64_t linear_ns = now_ns() - t0;

    uint64_t checksum_index = 0;
    t0 = now_ns();
    for (uint64_t i = 0; i < events; ++i) {
        uint32_t xid = rows[event_slots[i]].xid;
        int slot = indexed_find(xid);
        checksum_index += (uint64_t)(slot + 1);
    }
    uint64_t indexed_ns = now_ns() - t0;

    if (checksum_linear != checksum_index) {
        fputs("jx11-bench: lookup implementations disagree\n", stderr);
        free(event_slots);
        return 3;
    }
    sink_u64 = checksum_index;

    /* Dirty/repaint accounting. Half of events are modeled as visual changes;
     * the rest are state-only events. This is deterministic and intentionally
     * conservative: a burst paints once if any event in it dirtied the UI. */
    uint64_t naive_paints = 0;
    uint64_t burst_paints = 0;
    uint64_t dirty_events = 0;
    uint64_t burst_count = 0;

    t0 = now_ns();
    for (uint64_t i = 0; i < events; ++i) {
        if ((event_slots[i] & 1u) == 0u) ++naive_paints;
    }
    uint64_t naive_bookkeeping_ns = now_ns() - t0;

    t0 = now_ns();
    for (uint64_t base = 0; base < events; base += burst) {
        uint64_t end = base + burst;
        if (end > events) end = events;
        uint32_t dirty = 0;
        ++burst_count;
        for (uint64_t i = base; i < end; ++i) {
            if ((event_slots[i] & 1u) == 0u) {
                dirty = 1;
                ++dirty_events;
            }
        }
        if (dirty) ++burst_paints;
    }
    uint64_t coalesced_bookkeeping_ns = now_ns() - t0;

    if (dirty_events != naive_paints) {
        fputs("jx11-bench: coalescing accounting mismatch\n", stderr);
        free(event_slots);
        return 3;
    }

    /* Combined dispatch storm: resolve every event and mutate one dense slot.
     * Indexed path mirrors current JX11's intended interactive structure. */
    uint64_t combined_linear_sum = 0;
    t0 = now_ns();
    for (uint64_t i = 0; i < events; ++i) {
        int slot = linear_find(rows[event_slots[i]].xid, windows);
        rows[(uint32_t)slot].value += 1u;
        combined_linear_sum += rows[(uint32_t)slot].value;
    }
    uint64_t combined_linear_ns = now_ns() - t0;

    for (uint32_t i = 0; i < windows; ++i) rows[i].value = i;
    uint64_t combined_index_sum = 0;
    t0 = now_ns();
    for (uint64_t base = 0; base < events; base += burst) {
        uint64_t end = base + burst;
        if (end > events) end = events;
        uint32_t dirty = 0;
        for (uint64_t i = base; i < end; ++i) {
            int slot = indexed_find(rows[event_slots[i]].xid);
            rows[(uint32_t)slot].value += 1u;
            combined_index_sum += rows[(uint32_t)slot].value;
            dirty |= (event_slots[i] & 1u) == 0u;
        }
        sink_u64 ^= dirty;
    }
    uint64_t combined_index_ns = now_ns() - t0;

    if (combined_linear_sum != combined_index_sum) {
        fputs("jx11-bench: combined implementations disagree\n", stderr);
        free(event_slots);
        return 3;
    }

    double paint_reduction = naive_paints ? 100.0 * (1.0 - (double)burst_paints / (double)naive_paints) : 0.0;

    printf("JX11 hot-path benchmark\n");
    printf("events=%" PRIu64 " windows=%u burst=%u\n", events, windows, burst);
    printf("lookup linear:  %.3f ms (%.2f ns/event)\n", linear_ns / 1e6, ns_per(linear_ns, events));
    printf("lookup indexed: %.3f ms (%.2f ns/event)\n", indexed_ns / 1e6, ns_per(indexed_ns, events));
    printf("lookup speedup: %.2fx\n", speedup(linear_ns, indexed_ns));
    printf("naive dirty bookkeeping: %.3f ms, paints=%" PRIu64 "\n", naive_bookkeeping_ns / 1e6, naive_paints);
    printf("burst dirty bookkeeping: %.3f ms, bursts=%" PRIu64 ", paints=%" PRIu64 "\n", coalesced_bookkeeping_ns / 1e6, burst_count, burst_paints);
    printf("paint reduction: %.2f%% (%" PRIu64 " -> %" PRIu64 ")\n", paint_reduction, naive_paints, burst_paints);
    printf("combined linear dispatch: %.3f ms\n", combined_linear_ns / 1e6);
    printf("combined indexed/batched dispatch: %.3f ms\n", combined_index_ns / 1e6);
    printf("combined CPU speedup: %.2fx\n", speedup(combined_linear_ns, combined_index_ns));

    FILE *json = fopen("jx11-hotpath-benchmark.json", "w");
    if (json) {
        fprintf(json,
            "{\n"
            "  \"events\": %" PRIu64 ",\n"
            "  \"windows\": %u,\n"
            "  \"burst\": %u,\n"
            "  \"linear_lookup_ns\": %" PRIu64 ",\n"
            "  \"indexed_lookup_ns\": %" PRIu64 ",\n"
            "  \"lookup_speedup\": %.6f,\n"
            "  \"naive_paints\": %" PRIu64 ",\n"
            "  \"coalesced_paints\": %" PRIu64 ",\n"
            "  \"paint_reduction_percent\": %.6f,\n"
            "  \"combined_linear_ns\": %" PRIu64 ",\n"
            "  \"combined_indexed_batched_ns\": %" PRIu64 ",\n"
            "  \"combined_cpu_speedup\": %.6f\n"
            "}\n",
            events, windows, burst, linear_ns, indexed_ns,
            speedup(linear_ns, indexed_ns), naive_paints, burst_paints,
            paint_reduction, combined_linear_ns, combined_index_ns,
            speedup(combined_linear_ns, combined_index_ns));
        fclose(json);
    }

    free(event_slots);
    return 0;
}
