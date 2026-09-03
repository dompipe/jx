#include "jxl_container_runtime.h"

#include <stdint.h>
#include <stdio.h>
#include <string.h>

/* Correctness only. This deliberately does not time either Map backend; the
 * split-vs-keyed-vector performance comparison is kept for the later benchmark.
 */
enum {
    OP_EMPLACE = 0x46,
    OP_GET = 0x47,
    OP_PUT = 0x48,
    OP_HAS = 0x49,
    OP_REMOVE = 0x4a,
    UNUSED = 0x7f,
};

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

static void bind(JxJxlContainerBinding *b, void *fn, uint64_t *entries,
                 uint64_t *cursor, uint64_t *count, uint64_t capacity)
{
    memset(b, 0, sizeof(*b));
    b->native_fn = fn;
    b->base = entries;
    b->head = cursor;
    b->tail = count;
    b->capacity = capacity;
    b->mask = 0;
    b->aux = NULL;
}

static int run(const uint8_t inst[6], JxJxlContainerBinding *bindings,
               uint64_t binding_count, uint64_t w[8])
{
    return jx_jxl_container_execute(inst, bindings, w, binding_count) == inst + 6;
}

int main(void)
{
    enum { CAP = 8 };
    uint64_t entries[CAP * 2] = {0};
    uint64_t cursor = 0, count = 0, w[8] = {0};
    JxJxlContainerBinding b[5];
    uint8_t put[6], get[6], has[6], remove[6], emplace[6];

    /* IDs 18..22 must be the canonical keyed-vector Map backend. */
    bind(&b[0], jx_jxl_container_native_table[20], entries, &cursor, &count, CAP);
    bind(&b[1], jx_jxl_container_native_table[19], entries, &cursor, &count, CAP);
    bind(&b[2], jx_jxl_container_native_table[21], entries, &cursor, &count, CAP);
    bind(&b[3], jx_jxl_container_native_table[22], entries, &cursor, &count, CAP);
    bind(&b[4], jx_jxl_container_native_table[18], entries, &cursor, &count, CAP);

    make_inst(put, OP_PUT, 0, 0, 1, UNUSED);
    make_inst(get, OP_GET, 1, 0, UNUSED, 2);
    make_inst(has, OP_HAS, 2, 0, UNUSED, 2);
    make_inst(remove, OP_REMOVE, 3, 0, UNUSED, 2);
    make_inst(emplace, OP_EMPLACE, 4, 0, 1, 2);

    w[0] = 4; w[1] = 40; if (!run(put, b, 5, w)) return 1;
    w[0] = 2; w[1] = 20; if (!run(put, b, 5, w)) return 1;
    w[0] = 4; w[1] = 99; if (!run(put, b, 5, w)) return 1;
    w[0] = 3; w[1] = 30; if (!run(put, b, 5, w)) return 1;

    if (count != 3) return 1;
    if (entries[0] != 2 || entries[1] != 20) return 1;
    if (entries[2] != 3 || entries[3] != 30) return 1;
    if (entries[4] != 4 || entries[5] != 99) return 1;

    /* Emplace on an existing key must not overwrite. */
    w[0] = 3; w[1] = 300; if (!run(emplace, b, 5, w) || w[2] != 30) return 1;
    if (entries[3] != 30 || count != 3) return 1;

    w[0] = 4; if (!run(get, b, 5, w) || w[2] != 99) return 1;
    w[0] = 3; if (!run(has, b, 5, w) || w[2] != 1) return 1;
    w[0] = 8; if (!run(has, b, 5, w) || w[2] != 0) return 1;

    w[0] = 2; if (!run(remove, b, 5, w) || w[2] != 1) return 1;
    if (count != 2) return 1;
    if (entries[0] != 3 || entries[1] != 30) return 1;
    if (entries[2] != 4 || entries[3] != 99) return 1;

    puts("JXL keyed-vector Map: ok (Entry=[key,value], overwrite-or-insert)");
    return 0;
}
