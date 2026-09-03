#include "jxl_container_admission.h"

#include <errno.h>
#include <inttypes.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#define MAX_TEST_BAGS 16u

typedef struct Buffer {
    uint8_t *data;
    size_t size;
} Buffer;

typedef struct BagState {
    uint64_t handle;
    uint8_t discipline;
    uint64_t capacity;
    uint64_t *base;          /* Map Entry[] (key,value), Set keys[], ordinary base otherwise */
    uint64_t head;           /* ring head or Map/Set locality cursor */
    uint64_t tail;           /* ring tail or Map/Set element count */
    uint64_t generation;
    uint64_t flags;
} BagState;

typedef struct HarnessBags {
    BagState states[MAX_TEST_BAGS];
    size_t count;
} HarnessBags;

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

static BagState *find_or_create_bag(
    HarnessBags *bags,
    uint64_t handle,
    uint8_t discipline,
    uint64_t capacity
)
{
    for (size_t i = 0; i < bags->count; i++) {
        if (bags->states[i].handle == handle) {
            if (bags->states[i].discipline != discipline || bags->states[i].capacity != capacity) {
                fprintf(stderr, "inconsistent binding metadata for Bag handle %" PRIu64 "\n", handle);
                return NULL;
            }
            return &bags->states[i];
        }
    }

    if (bags->count >= MAX_TEST_BAGS) {
        fprintf(stderr, "too many Bag handles in native harness\n");
        return NULL;
    }

    BagState *state = &bags->states[bags->count++];
    memset(state, 0, sizeof(*state));
    state->handle = handle;
    state->discipline = discipline;
    state->capacity = capacity;

    /* Map is Vector<Entry>, and a v1 Entry is two u64 words. Every other
     * discipline remains one u64 word per logical capacity slot in this test.
     */
    size_t words = (size_t)(capacity == 0 ? 1 : capacity);
    if (discipline == 6) words *= 2u;
    state->base = (uint64_t *)calloc(words, sizeof(uint64_t));
    if (state->base == NULL) {
        fprintf(stderr, "cannot allocate Bag handle %" PRIu64 "\n", handle);
        return NULL;
    }
    return state;
}

static int resolve_bag(
    const JxJxlContainerBindingSpec *spec,
    JxJxlContainerBinding *runtime,
    void *context
)
{
    HarnessBags *bags = (HarnessBags *)context;
    BagState *bag = find_or_create_bag(bags, spec->bag_handle, spec->discipline, spec->capacity);
    if (bag == NULL) return 0;

    runtime->base = bag->base;
    runtime->head = &bag->head;
    runtime->tail = &bag->tail;
    runtime->generation = &bag->generation;
    runtime->flags = &bag->flags;
    runtime->aux = NULL;
    runtime->aux2 = NULL;
    return 1;
}

static BagState *bag_by_handle(HarnessBags *bags, uint64_t handle)
{
    for (size_t i = 0; i < bags->count; i++) if (bags->states[i].handle == handle) return &bags->states[i];
    return NULL;
}

static int set_contains(const BagState *set, uint64_t key)
{
    if (set == NULL || set->discipline != 7) return 0;
    for (uint64_t i = 0; i < set->tail; i++) {
        if (set->base[i] == key) return 1;
    }
    return 0;
}

static void free_bags(HarnessBags *bags)
{
    for (size_t i = 0; i < bags->count; i++) free(bags->states[i].base);
    bags->count = 0;
}

int main(int argc, char **argv)
{
    if (argc != 4) {
        fprintf(stderr, "usage: %s program.jxl bindings.jxcb registers.jxrw\n", argv[0]);
        return 2;
    }

    Buffer code = read_file(argv[1]);
    Buffer table = read_file(argv[2]);
    Buffer register_file = read_file(argv[3]);
    if (code.data == NULL || table.data == NULL || register_file.data == NULL) {
        free_buffer(&code);
        free_buffer(&table);
        free_buffer(&register_file);
        return 2;
    }
    if (code.size == 0 || code.size % JX_JXL_CONTAINER_INSTRUCTION_BYTES != 0 || register_file.size != 64 || table.size < 12) {
        fprintf(stderr, "invalid JXL/register/binding artifact shape\n");
        free_buffer(&code);
        free_buffer(&table);
        free_buffer(&register_file);
        return 2;
    }

    uint64_t window[8] = {0};
    for (size_t i = 0; i < 8; i++) window[i] = u64le(register_file.data + i * 8u);

    const size_t binding_capacity = u16le(table.data + 10);
    JxJxlContainerBinding *bindings = (JxJxlContainerBinding *)calloc(
        binding_capacity == 0 ? 1u : binding_capacity,
        sizeof(*bindings)
    );
    if (bindings == NULL) {
        free_buffer(&code);
        free_buffer(&table);
        free_buffer(&register_file);
        return 2;
    }

    HarnessBags bags = {0};
    size_t binding_count = 0;
    const int admitted = jx_jxl_container_admit(
        table.data,
        table.size,
        bindings,
        binding_capacity,
        resolve_bag,
        &bags,
        &binding_count
    );
    if (admitted != JX_JXL_ADMIT_OK) {
        fprintf(stderr, "native admission failed: %d\n", admitted);
        free(bindings);
        free_bags(&bags);
        free_buffer(&code);
        free_buffer(&table);
        free_buffer(&register_file);
        return 2;
    }

    const int status = jx_jxl_container_execute_stream(
        code.data,
        code.data + code.size,
        bindings,
        window,
        binding_count
    );
    if (status != 0) {
        fprintf(stderr, "native stream failed: %d\n", status);
        free(bindings);
        free_bags(&bags);
        free_buffer(&code);
        free_buffer(&table);
        free_buffer(&register_file);
        return 1;
    }

    BagState *jobs = bag_by_handle(&bags, 10);
    BagState *seen = bag_by_handle(&bags, 11);
    BagState *state = bag_by_handle(&bags, 12);

    int ok = 1;
    ok = ok && jobs != NULL && jobs->discipline == 4;
    ok = ok && jobs->head == 1 && jobs->tail == 1;
    ok = ok && jobs->generation == 1 && jobs->flags == 0;
    ok = ok && seen != NULL && seen->discipline == 7;
    ok = ok && seen->tail == 1 && seen->flags == JX_BAG_DIRTY && set_contains(seen, 42);
    ok = ok && state != NULL && state->discipline == 1;
    ok = ok && state->base[0] == 42 && state->flags == JX_BAG_DIRTY;

    ok = ok && window[0] == 42;
    ok = ok && window[1] == 42;
    ok = ok && window[2] == 1;
    ok = ok && window[3] == 0;
    ok = ok && window[4] == 42;

    if (!ok) {
        fprintf(stderr,
            "native verification failed: jobs(h=%" PRIu64 ",t=%" PRIu64 ",g=%" PRIu64 ",f=%" PRIu64 ") "
            "seen(count=%" PRIu64 ",f=%" PRIu64 ") state(health=%" PRIu64 ",f=%" PRIu64 ") "
            "regs=[%" PRIu64 ",%" PRIu64 ",%" PRIu64 ",%" PRIu64 ",%" PRIu64 "]\n",
            jobs ? jobs->head : UINT64_MAX,
            jobs ? jobs->tail : UINT64_MAX,
            jobs ? jobs->generation : UINT64_MAX,
            jobs ? jobs->flags : UINT64_MAX,
            seen ? seen->tail : UINT64_MAX,
            seen ? seen->flags : UINT64_MAX,
            state ? state->base[0] : UINT64_MAX,
            state ? state->flags : UINT64_MAX,
            window[0], window[1], window[2], window[3], window[4]
        );
    } else {
        printf(
            "JXL native stream: ok (%zu bytes, %zu admitted bindings, queue->set-array->record, generation=%" PRIu64 ")\n",
            code.size,
            binding_count,
            jobs->generation
        );
    }

    free(bindings);
    free_bags(&bags);
    free_buffer(&code);
    free_buffer(&table);
    free_buffer(&register_file);
    return ok ? 0 : 1;
}
