#include "jxl_container_admission.h"

#include <errno.h>
#include <inttypes.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#define MAX_TEST_BAGS 8u

typedef struct Buffer {
    uint8_t *data;
    size_t size;
} Buffer;

typedef struct BagState {
    uint64_t handle;
    uint8_t discipline;
    uint64_t capacity;
    uint64_t *base;
    uint64_t head;
    uint64_t tail;
    uint64_t generation;
    uint64_t flags;
    uint64_t count;
} BagState;

typedef struct ResolverContext {
    BagState states[MAX_TEST_BAGS];
    size_t count;
} ResolverContext;

int jx_jxl_prepared_execute(
    const uint8_t *begin,
    const uint8_t *end,
    JxJxlContainerBinding *bindings,
    uint64_t window8[8],
    uint64_t binding_count
);

static uint16_t u16le(const uint8_t *p)
{
    return (uint16_t)p[0] | ((uint16_t)p[1] << 8);
}

static uint32_t u32le(const uint8_t *p)
{
    return (uint32_t)p[0]
        | ((uint32_t)p[1] << 8)
        | ((uint32_t)p[2] << 16)
        | ((uint32_t)p[3] << 24);
}

static uint64_t u64le(const uint8_t *p)
{
    return (uint64_t)u32le(p) | ((uint64_t)u32le(p + 4) << 32);
}

static Buffer read_file(const char *path)
{
    Buffer out = {0};
    FILE *fp = fopen(path, "rb");
    if (fp == NULL) {
        fprintf(stderr, "cannot open %s: %s\n", path, strerror(errno));
        return out;
    }
    if (fseek(fp, 0, SEEK_END) != 0) {
        fclose(fp);
        return out;
    }
    const long end = ftell(fp);
    if (end < 0 || fseek(fp, 0, SEEK_SET) != 0) {
        fclose(fp);
        return out;
    }
    out.size = (size_t)end;
    out.data = (uint8_t *)malloc(out.size == 0 ? 1u : out.size);
    if (out.data == NULL) {
        fclose(fp);
        out.size = 0;
        return out;
    }
    if (out.size != 0 && fread(out.data, 1, out.size, fp) != out.size) {
        free(out.data);
        out.data = NULL;
        out.size = 0;
    }
    fclose(fp);
    return out;
}

static void free_buffer(Buffer *buffer)
{
    free(buffer->data);
    buffer->data = NULL;
    buffer->size = 0;
}

static BagState *bag_by_handle(ResolverContext *ctx, uint64_t handle)
{
    for (size_t i = 0; i < ctx->count; i++) {
        if (ctx->states[i].handle == handle) return &ctx->states[i];
    }
    return NULL;
}

static int resolve_bag(
    const JxJxlContainerBindingSpec *spec,
    JxJxlContainerBinding *runtime,
    void *context
)
{
    ResolverContext *ctx = (ResolverContext *)context;
    BagState *state = bag_by_handle(ctx, spec->bag_handle);

    if (state == NULL) {
        if (ctx->count >= MAX_TEST_BAGS) return 0;
        state = &ctx->states[ctx->count++];
        memset(state, 0, sizeof(*state));
        state->handle = spec->bag_handle;
        state->discipline = spec->discipline;
        state->capacity = spec->capacity;

        size_t words = (size_t)(spec->capacity == 0 ? 16u : spec->capacity);
        if (spec->discipline == 6 || spec->discipline == 7) words *= 3u;
        state->base = (uint64_t *)calloc(words, sizeof(uint64_t));
        if (state->base == NULL) return 0;
    } else if (state->discipline != spec->discipline || state->capacity != spec->capacity) {
        return 0;
    }

    runtime->base = state->base;
    runtime->head = &state->head;
    runtime->tail = &state->tail;
    runtime->generation = &state->generation;
    runtime->flags = &state->flags;
    runtime->aux = (spec->discipline == 6 || spec->discipline == 7) ? (void *)&state->count : NULL;
    runtime->aux2 = NULL;
    return 1;
}

static void free_states(ResolverContext *ctx)
{
    for (size_t i = 0; i < ctx->count; i++) free(ctx->states[i].base);
    ctx->count = 0;
}

int main(int argc, char **argv)
{
    if (argc != 4) {
        fprintf(stderr, "usage: %s program.jxl bindings.jxcb registers.jxrw\n", argv[0]);
        return 2;
    }

    Buffer code = read_file(argv[1]);
    Buffer serialized = read_file(argv[2]);
    Buffer register_file = read_file(argv[3]);
    if (code.data == NULL || serialized.data == NULL || register_file.data == NULL) {
        free_buffer(&code);
        free_buffer(&serialized);
        free_buffer(&register_file);
        return 2;
    }
    if (code.size == 0 || code.size % 6u != 0 || register_file.size != 64u) {
        fprintf(stderr, "invalid prepared JXL/register artifact shape\n");
        free_buffer(&code);
        free_buffer(&serialized);
        free_buffer(&register_file);
        return 2;
    }
    if (serialized.size < 12u || memcmp(serialized.data, "JXCBIND1", 8) != 0) {
        fprintf(stderr, "invalid binding artifact header\n");
        free_buffer(&code);
        free_buffer(&serialized);
        free_buffer(&register_file);
        return 2;
    }

    size_t core_ops = 0;
    size_t container_ops = 0;
    for (size_t pc = 0; pc < code.size; pc += 6u) {
        const uint8_t op = code.data[pc];
        if (op >= 0x20 && op <= 0x37) core_ops++;
        else if (op >= 0x40 && op <= 0x50) container_ops++;
        else {
            fprintf(stderr, "unexpected opcode 0x%02x at %zu\n", op, pc);
            free_buffer(&code);
            free_buffer(&serialized);
            free_buffer(&register_file);
            return 2;
        }
    }
    if (core_ops == 0 || container_ops == 0) {
        fprintf(stderr, "fixture did not exercise both prepared opcode bands\n");
        free_buffer(&code);
        free_buffer(&serialized);
        free_buffer(&register_file);
        return 2;
    }

    const uint16_t serialized_count = u16le(serialized.data + 10);
    JxJxlContainerBinding *bindings = (JxJxlContainerBinding *)calloc(
        serialized_count == 0 ? 1u : serialized_count,
        sizeof(*bindings)
    );
    if (bindings == NULL) {
        free_buffer(&code);
        free_buffer(&serialized);
        free_buffer(&register_file);
        return 2;
    }

    ResolverContext resolver = {0};
    size_t binding_count = 0;
    const int admit = jx_jxl_container_admit(
        serialized.data,
        serialized.size,
        bindings,
        serialized_count,
        resolve_bag,
        &resolver,
        &binding_count
    );
    if (admit != JX_JXL_ADMIT_OK) {
        fprintf(stderr, "binding admission failed: %d\n", admit);
        free(bindings);
        free_states(&resolver);
        free_buffer(&code);
        free_buffer(&serialized);
        free_buffer(&register_file);
        return 1;
    }

    uint64_t window[8] = {0};
    for (size_t i = 0; i < 8; i++) window[i] = u64le(register_file.data + i * 8u);

    const int status = jx_jxl_prepared_execute(
        code.data,
        code.data + code.size,
        bindings,
        window,
        binding_count
    );
    if (status != 0) {
        fprintf(stderr, "prepared native execution failed: %d\n", status);
        free(bindings);
        free_states(&resolver);
        free_buffer(&code);
        free_buffer(&serialized);
        free_buffer(&register_file);
        return 1;
    }

    BagState *jobs = bag_by_handle(&resolver, 20);
    BagState *state = bag_by_handle(&resolver, 21);
    int ok = 1;
    ok = ok && jobs != NULL && jobs->head == 4 && jobs->tail == 4;
    ok = ok && jobs->generation == 1 && jobs->flags == 0;
    ok = ok && state != NULL && state->base[0] == 6 && state->flags == JX_BAG_DIRTY;
    ok = ok && window[0] == 0; /* i */
    ok = ok && window[1] == 6; /* sum */
    /* R2 backs loop-local x but is also a dead scratch at the loop condition.
     * On the final false condition it is legally clobbered to zero, so its
     * post-loop value is not part of the source-visible contract. */
    ok = ok && window[3] == 6; /* out */

    if (!ok) {
        fprintf(stderr,
            "prepared verification failed: regs=[%" PRIu64 ",%" PRIu64 ",%" PRIu64 ",%" PRIu64 "] "
            "queue(h=%" PRIu64 ",t=%" PRIu64 ",g=%" PRIu64 ",f=%" PRIu64 ") "
            "record(total=%" PRIu64 ",f=%" PRIu64 ")\n",
            window[0], window[1], window[2], window[3],
            jobs ? jobs->head : UINT64_MAX,
            jobs ? jobs->tail : UINT64_MAX,
            jobs ? jobs->generation : UINT64_MAX,
            jobs ? jobs->flags : UINT64_MAX,
            state ? state->base[0] : UINT64_MAX,
            state ? state->flags : UINT64_MAX
        );
    } else {
        printf(
            "prepared native program: ok (%zu bytes, %zu core ops, %zu container ops, sum=%" PRIu64 ", generation=%" PRIu64 ")\n",
            code.size,
            core_ops,
            container_ops,
            window[1],
            jobs->generation
        );
    }

    free(bindings);
    free_states(&resolver);
    free_buffer(&code);
    free_buffer(&serialized);
    free_buffer(&register_file);
    return ok ? 0 : 1;
}
