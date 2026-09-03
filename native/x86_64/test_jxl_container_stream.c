#include "jxl_container_runtime.h"

#include <errno.h>
#include <inttypes.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#define JXCB_HEADER_BYTES 12u
#define JXCB_RECORD_BYTES 26u
#define MAX_TEST_BAGS 16u

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
    BagState states[MAX_TEST_BAGS],
    size_t *state_count,
    uint64_t handle,
    uint8_t discipline,
    uint64_t capacity
)
{
    for (size_t i = 0; i < *state_count; i++) {
        if (states[i].handle == handle) {
            if (states[i].discipline != discipline || states[i].capacity != capacity) {
                fprintf(stderr, "inconsistent binding metadata for Bag handle %" PRIu64 "\n", handle);
                return NULL;
            }
            return &states[i];
        }
    }

    if (*state_count >= MAX_TEST_BAGS) {
        fprintf(stderr, "too many Bag handles in native harness\n");
        return NULL;
    }

    BagState *state = &states[(*state_count)++];
    memset(state, 0, sizeof(*state));
    state->handle = handle;
    state->discipline = discipline;
    state->capacity = capacity;

    size_t words = (size_t)(capacity == 0 ? 1 : capacity);
    if (discipline == 6 || discipline == 7) words *= 3u; /* hash slot = state,key,value */
    state->base = (uint64_t *)calloc(words, sizeof(uint64_t));
    if (state->base == NULL) {
        fprintf(stderr, "cannot allocate Bag handle %" PRIu64 "\n", handle);
        return NULL;
    }
    return state;
}

static BagState *bag_by_handle(BagState states[MAX_TEST_BAGS], size_t count, uint64_t handle)
{
    for (size_t i = 0; i < count; i++) if (states[i].handle == handle) return &states[i];
    return NULL;
}

static int set_contains(const BagState *set, uint64_t key)
{
    if (set == NULL || set->discipline != 7) return 0;
    for (uint64_t i = 0; i < set->capacity; i++) {
        const uint64_t *slot = set->base + (size_t)i * 3u;
        if (slot[0] == 1 && slot[1] == key) return 1;
    }
    return 0;
}

static int admit_bindings(
    const Buffer *serialized,
    JxJxlContainerBinding **out_bindings,
    uint16_t *out_count,
    BagState states[MAX_TEST_BAGS],
    size_t *state_count
)
{
    if (serialized->size < JXCB_HEADER_BYTES || memcmp(serialized->data, "JXCBIND1", 8) != 0) {
        fprintf(stderr, "invalid JXCBIND1 header\n");
        return 0;
    }

    const uint16_t version = u16le(serialized->data + 8);
    const uint16_t count = u16le(serialized->data + 10);
    const size_t expected = JXCB_HEADER_BYTES + (size_t)count * JXCB_RECORD_BYTES;
    if (version != 1 || serialized->size != expected) {
        fprintf(stderr, "invalid JXCBIND1 version/length\n");
        return 0;
    }

    JxJxlContainerBinding *bindings = (JxJxlContainerBinding *)calloc(count == 0 ? 1u : count, sizeof(*bindings));
    if (bindings == NULL) return 0;

    const uint64_t native_count = jx_jxl_container_native_count;
    for (uint16_t row = 0; row < count; row++) {
        const uint8_t *p = serialized->data + JXCB_HEADER_BYTES + (size_t)row * JXCB_RECORD_BYTES;
        const uint16_t id = u16le(p + 0);
        const uint8_t discipline = p[2];
        const uint8_t width = p[4];
        const uint64_t handle = u64le(p + 6);
        const uint64_t capacity = u32le(p + 14);
        const uint64_t mask = u32le(p + 18);
        const uint16_t native_id = u16le(p + 22);

        if (id >= count || discipline < 1 || discipline > 7 || width != 8) {
            fprintf(stderr, "invalid serialized binding row %u\n", (unsigned)row);
            free(bindings);
            return 0;
        }
        if (native_id == 0 || native_id > native_count || jx_jxl_container_native_table[native_id] == NULL) {
            fprintf(stderr, "invalid native id %u\n", (unsigned)native_id);
            free(bindings);
            return 0;
        }

        BagState *bag = find_or_create_bag(states, state_count, handle, discipline, capacity);
        if (bag == NULL) {
            free(bindings);
            return 0;
        }

        bindings[id].native_fn = jx_jxl_container_native_table[native_id];
        bindings[id].base = bag->base;
        bindings[id].head = &bag->head;
        bindings[id].tail = &bag->tail;
        bindings[id].capacity = capacity;
        bindings[id].mask = mask;
        bindings[id].generation = &bag->generation;
        bindings[id].flags = &bag->flags;
        bindings[id].aux = (discipline == 6 || discipline == 7) ? (void *)&bag->count : NULL;
        bindings[id].aux2 = NULL;
    }

    *out_bindings = bindings;
    *out_count = count;
    return 1;
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
    if (code.size == 0 || code.size % JX_JXL_CONTAINER_INSTRUCTION_BYTES != 0 || register_file.size != 64) {
        fprintf(stderr, "invalid JXL/register artifact shape\n");
        free_buffer(&code);
        free_buffer(&table);
        free_buffer(&register_file);
        return 2;
    }

    uint64_t window[8] = {0};
    for (size_t i = 0; i < 8; i++) window[i] = u64le(register_file.data + i * 8u);

    BagState states[MAX_TEST_BAGS] = {0};
    size_t state_count = 0;
    JxJxlContainerBinding *bindings = NULL;
    uint16_t binding_count = 0;
    if (!admit_bindings(&table, &bindings, &binding_count, states, &state_count)) {
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
        for (size_t i = 0; i < state_count; i++) free(states[i].base);
        free_buffer(&code);
        free_buffer(&table);
        free_buffer(&register_file);
        return 1;
    }

    BagState *jobs = bag_by_handle(states, state_count, 10);
    BagState *seen = bag_by_handle(states, state_count, 11);
    BagState *state = bag_by_handle(states, state_count, 12);

    int ok = 1;
    ok = ok && jobs != NULL && jobs->discipline == 4;
    ok = ok && jobs->head == 1 && jobs->tail == 1;
    ok = ok && jobs->generation == 1 && jobs->flags == 0;
    ok = ok && seen != NULL && seen->discipline == 7;
    ok = ok && seen->count == 1 && seen->flags == JX_BAG_DIRTY && set_contains(seen, 42);
    ok = ok && state != NULL && state->discipline == 1;
    ok = ok && state->base[0] == 42 && state->flags == JX_BAG_DIRTY;

    /* Source compiler register contract for the fixture:
     * R0 task=42, R1 next, R2 set-add result, R3 record slot constant=0,
     * R4 hp. Native execution must update only the prepared destination slots.
     */
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
            seen ? seen->count : UINT64_MAX,
            seen ? seen->flags : UINT64_MAX,
            state ? state->base[0] : UINT64_MAX,
            state ? state->flags : UINT64_MAX,
            window[0], window[1], window[2], window[3], window[4]
        );
    } else {
        printf(
            "JXL native stream: ok (%zu bytes, %u bindings, queue->set->record, generation=%" PRIu64 ")\n",
            code.size,
            (unsigned)binding_count,
            jobs->generation
        );
    }

    free(bindings);
    for (size_t i = 0; i < state_count; i++) free(states[i].base);
    free_buffer(&code);
    free_buffer(&table);
    free_buffer(&register_file);
    return ok ? 0 : 1;
}
