#define _POSIX_C_SOURCE 200809L
#include "jxl_container_runtime.h"

#include <inttypes.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>

enum {
    OP_GET = 0x47,
    OP_PUT = 0x48,
    SEL_UNUSED = 0x7f,
};

typedef enum Layout {
    LAYOUT_SPLIT = 0,
    LAYOUT_VECTOR = 1,
} Layout;

typedef enum Workload {
    WORK_ORDERED_APPEND_GET = 0,
    WORK_RANDOM_UPDATE_GET = 1,
    WORK_DESCENDING_INSERT_GET = 2,
    WORK_SHUFFLED_INSERT_GET = 3,
} Workload;

typedef struct RunResult {
    double ms;
    uint64_t checksum;
    int ok;
} RunResult;

typedef struct Stats {
    double median_ms;
    double min_ms;
    double p95_ms;
    double mops_s;
    double ns_op;
    uint64_t checksum;
} Stats;

static double now_ms(void)
{
    struct timespec ts;
    if (clock_gettime(CLOCK_MONOTONIC, &ts) != 0) {
        perror("clock_gettime");
        exit(2);
    }
    return (double)ts.tv_sec * 1000.0 + (double)ts.tv_nsec / 1000000.0;
}

static int cmp_double(const void *a, const void *b)
{
    const double da = *(const double *)a;
    const double db = *(const double *)b;
    return (da > db) - (da < db);
}

static uint64_t rng_next(uint64_t *state)
{
    uint64_t x = *state;
    x ^= x >> 12;
    x ^= x << 25;
    x ^= x >> 27;
    *state = x;
    return x * UINT64_C(2685821657736338717);
}

static void fill_order(uint64_t *order, uint64_t n, Workload workload)
{
    for (uint64_t i = 0; i < n; ++i) order[i] = i;
    if (workload == WORK_DESCENDING_INSERT_GET) {
        for (uint64_t i = 0; i < n / 2; ++i) {
            const uint64_t j = n - 1 - i;
            const uint64_t t = order[i];
            order[i] = order[j];
            order[j] = t;
        }
        return;
    }
    if (workload == WORK_SHUFFLED_INSERT_GET || workload == WORK_RANDOM_UPDATE_GET) {
        uint64_t state = UINT64_C(0x9e3779b97f4a7c15);
        for (uint64_t i = n; i > 1; --i) {
            const uint64_t j = rng_next(&state) % i;
            const uint64_t t = order[i - 1];
            order[i - 1] = order[j];
            order[j] = t;
        }
    }
}

static void make_inst(uint8_t out[6], uint8_t opcode, uint16_t binding_id,
                      uint8_t src0, uint8_t src1, uint8_t dst)
{
    out[0] = opcode;
    out[1] = (uint8_t)(0x80u | (binding_id & 0x7fu));
    out[2] = (uint8_t)(0x80u | ((binding_id >> 7) & 0x7fu));
    out[3] = (uint8_t)(0x80u | (src0 & 0x7fu));
    out[4] = (uint8_t)(0x80u | (src1 & 0x7fu));
    out[5] = (uint8_t)(0x80u | (dst & 0x7fu));
}

static int exec_inst(const uint8_t inst[6], JxJxlContainerBinding *bindings,
                     uint64_t binding_count, uint64_t window8[8])
{
    const uint8_t *next = jx_jxl_container_execute(inst, bindings, window8, binding_count);
    return next == inst + JX_JXL_CONTAINER_INSTRUCTION_BYTES;
}

static void init_binding(JxJxlContainerBinding *b, void *fn, uint64_t *base,
                         uint64_t *cursor, uint64_t *count, uint64_t capacity,
                         uint64_t *generation, uint64_t *flags, void *aux)
{
    memset(b, 0, sizeof(*b));
    b->native_fn = fn;
    b->base = base;
    b->head = cursor;
    b->tail = count;
    b->capacity = capacity;
    b->generation = generation;
    b->flags = flags;
    b->aux = aux;
}

static int verify_layout(Layout layout)
{
    uint64_t split_keys[8] = {0}, split_values[8] = {0};
    uint64_t vector_entries[16] = {0};
    uint64_t cursor = 0, count = 0, generation = 0, flags = 0, w[8] = {0};
    JxJxlContainerBinding b[2];
    uint8_t put[6], get[6];

    if (layout == LAYOUT_VECTOR) {
        init_binding(&b[0], (void *)jx_map_vector_put_u64, vector_entries,
                     &cursor, &count, 8, &generation, &flags, NULL);
        init_binding(&b[1], (void *)jx_map_vector_get_u64, vector_entries,
                     &cursor, &count, 8, &generation, &flags, NULL);
    } else {
        init_binding(&b[0], (void *)jx_map_put_u64, split_keys,
                     &cursor, &count, 8, &generation, &flags, split_values);
        init_binding(&b[1], (void *)jx_map_get_u64, split_keys,
                     &cursor, &count, 8, &generation, &flags, split_values);
    }
    make_inst(put, OP_PUT, 0, 0, 1, SEL_UNUSED);
    make_inst(get, OP_GET, 1, 0, SEL_UNUSED, 2);

    w[0] = 4; w[1] = 40; if (!exec_inst(put, b, 2, w)) return 0;
    w[0] = 2; w[1] = 20; if (!exec_inst(put, b, 2, w)) return 0;
    w[0] = 4; w[1] = 99; if (!exec_inst(put, b, 2, w)) return 0;
    if (count != 2) return 0;

    if (layout == LAYOUT_VECTOR) {
        if (vector_entries[0] != 2 || vector_entries[1] != 20 ||
            vector_entries[2] != 4 || vector_entries[3] != 99) return 0;
    } else {
        if (split_keys[0] != 2 || split_keys[1] != 4 ||
            split_values[0] != 20 || split_values[1] != 99) return 0;
    }

    w[0] = 2; if (!exec_inst(get, b, 2, w) || w[2] != 20) return 0;
    w[0] = 4; if (!exec_inst(get, b, 2, w) || w[2] != 99) return 0;
    return 1;
}

static RunResult run_workload(Layout layout, Workload workload, uint64_t total_ops)
{
    const uint64_t n = total_ops / 2;
    const uint64_t cap = n ? n : 1;
    if (cap > (uint64_t)(SIZE_MAX / (2u * sizeof(uint64_t)))) return (RunResult){0, 0, 0};

    uint64_t *base = NULL;
    uint64_t *aux = NULL;
    if (layout == LAYOUT_VECTOR) {
        base = calloc((size_t)(cap * 2u), sizeof(uint64_t));
    } else {
        base = calloc((size_t)cap, sizeof(uint64_t));
        aux = calloc((size_t)cap, sizeof(uint64_t));
    }
    uint64_t *order = malloc((size_t)cap * sizeof(uint64_t));
    if (!base || (layout == LAYOUT_SPLIT && !aux) || !order) {
        free(order); free(aux); free(base);
        return (RunResult){0, 0, 0};
    }
    fill_order(order, n, workload);

    uint64_t cursor = 0, count = 0, generation = 0, flags = 0, w[8] = {0};
    JxJxlContainerBinding b[2];
    uint8_t put[6], get[6];
    void *put_fn = layout == LAYOUT_VECTOR ? (void *)jx_map_vector_put_u64 : (void *)jx_map_put_u64;
    void *get_fn = layout == LAYOUT_VECTOR ? (void *)jx_map_vector_get_u64 : (void *)jx_map_get_u64;
    init_binding(&b[0], put_fn, base, &cursor, &count, cap, &generation, &flags, aux);
    init_binding(&b[1], get_fn, base, &cursor, &count, cap, &generation, &flags, aux);
    make_inst(put, OP_PUT, 0, 0, 1, SEL_UNUSED);
    make_inst(get, OP_GET, 1, 0, SEL_UNUSED, 2);

    if (workload == WORK_RANDOM_UPDATE_GET) {
        count = n;
        if (layout == LAYOUT_VECTOR) {
            for (uint64_t i = 0; i < n; ++i) {
                base[i * 2u] = i;
                base[i * 2u + 1u] = i;
            }
        } else {
            for (uint64_t i = 0; i < n; ++i) {
                base[i] = i;
                aux[i] = i;
            }
        }
    }

    uint64_t checksum = 0;
    int ok = 1;
    const double t0 = now_ms();
    for (uint64_t i = 0; i < n; ++i) {
        const uint64_t key = order[i];
        w[0] = key;
        w[1] = workload == WORK_RANDOM_UPDATE_GET ? (key ^ UINT64_C(0xa5a5a5a55a5a5a5a)) : key;
        if (!exec_inst(put, b, 2, w)) { ok = 0; break; }
    }
    if (ok) {
        for (uint64_t i = 0; i < n; ++i) {
            w[0] = order[i];
            if (!exec_inst(get, b, 2, w)) { ok = 0; break; }
            checksum ^= w[2] + UINT64_C(0x9e3779b97f4a7c15) + i;
        }
    }
    const double elapsed = now_ms() - t0;

    free(order); free(aux); free(base);
    return (RunResult){elapsed, checksum, ok};
}

static Stats bench(Layout layout, Workload workload, uint64_t ops, int reps, int warmups)
{
    for (int i = 0; i < warmups; ++i) {
        const RunResult r = run_workload(layout, workload, ops);
        if (!r.ok) { fprintf(stderr, "Map layout warmup failed\n"); exit(3); }
    }
    double *times = calloc((size_t)reps, sizeof(double));
    if (!times) exit(4);
    uint64_t checksum = 0;
    for (int i = 0; i < reps; ++i) {
        const RunResult r = run_workload(layout, workload, ops);
        if (!r.ok) { fprintf(stderr, "Map layout benchmark failed\n"); exit(5); }
        times[i] = r.ms;
        if (i == 0) checksum = r.checksum;
        else if (checksum != r.checksum) {
            fprintf(stderr, "Map layout checksum changed between repetitions\n");
            exit(6);
        }
    }
    qsort(times, (size_t)reps, sizeof(double), cmp_double);
    const double median = (reps & 1) ? times[reps / 2] : (times[reps / 2 - 1] + times[reps / 2]) / 2.0;
    int p95i = (int)((reps * 95 + 99) / 100) - 1;
    if (p95i < 0) p95i = 0;
    if (p95i >= reps) p95i = reps - 1;
    Stats s = {median, times[0], times[p95i], 0, 0, checksum};
    const double seconds = median / 1000.0;
    s.mops_s = seconds > 0 ? ((double)ops / seconds) / 1e6 : 0;
    s.ns_op = ops ? median * 1e6 / (double)ops : 0;
    free(times);
    return s;
}

static void print_stats(Stats s)
{
    printf("{\"median_ms\":%.9f,\"min_ms\":%.9f,\"p95_ms\":%.9f,\"mops_s\":%.9f,\"ns_op\":%.9f,\"checksum\":%" PRIu64 "}",
           s.median_ms, s.min_ms, s.p95_ms, s.mops_s, s.ns_op, s.checksum);
}

static void print_workload(const char *name, Workload workload, uint64_t ops, int reps, int warmups, int comma)
{
    const Stats split = bench(LAYOUT_SPLIT, workload, ops, reps, warmups);
    const Stats vector = bench(LAYOUT_VECTOR, workload, ops, reps, warmups);
    if (split.checksum != vector.checksum) {
        fprintf(stderr, "Map layouts disagree on checksum for %s\n", name);
        exit(8);
    }
    printf("\"%s\":{\"ops\":%" PRIu64 ",\"split\":", name, ops);
    print_stats(split);
    printf(",\"vector\":");
    print_stats(vector);
    printf(",\"vector_over_split\":%.9f,\"faster\":\"%s\"}%s",
           split.median_ms > 0 ? vector.median_ms / split.median_ms : 0,
           vector.median_ms < split.median_ms ? "vector" : (vector.median_ms > split.median_ms ? "split" : "tie"),
           comma ? "," : "");
}

int main(int argc, char **argv)
{
    uint64_t standard_ops = argc > 1 ? strtoull(argv[1], NULL, 10) : UINT64_C(1000000);
    uint64_t shift_ops = argc > 2 ? strtoull(argv[2], NULL, 10) : UINT64_C(20000);
    int reps = argc > 3 ? atoi(argv[3]) : 7;
    int warmups = argc > 4 ? atoi(argv[4]) : 1;
    if (standard_ops < 2 || shift_ops < 2 || (standard_ops & 1u) || (shift_ops & 1u) || reps < 1 || warmups < 0) {
        fprintf(stderr, "usage: %s EVEN_STANDARD_OPS EVEN_SHIFT_OPS [REPS] [WARMUPS]\n", argv[0]);
        return 2;
    }
    if (!verify_layout(LAYOUT_SPLIT) || !verify_layout(LAYOUT_VECTOR)) {
        fprintf(stderr, "Map layout verification failed\n");
        return 7;
    }

    printf("{\"suite\":\"jxl-map-layout-ab/1\",\"path\":\"prepared-6-byte-executor\",\"standard_ops\":%" PRIu64 ",\"shift_ops\":%" PRIu64 ",\"reps\":%d,\"warmups\":%d,\"workloads\":{",
           standard_ops, shift_ops, reps, warmups);
    print_workload("ordered_append_get", WORK_ORDERED_APPEND_GET, standard_ops, reps, warmups, 1);
    print_workload("random_update_get", WORK_RANDOM_UPDATE_GET, standard_ops, reps, warmups, 1);
    print_workload("descending_insert_get", WORK_DESCENDING_INSERT_GET, shift_ops, reps, warmups, 1);
    print_workload("shuffled_insert_get", WORK_SHUFFLED_INSERT_GET, shift_ops, reps, warmups, 0);
    printf("}}\n");
    return 0;
}
