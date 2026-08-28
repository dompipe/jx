#include "../host/common/jx-asm-call.h"
#include <assert.h>
#include <stdio.h>
#include <string.h>

typedef struct {
    uint64_t left;
    uint64_t right;
} frame_t;

static uint64_t add_bias(void *frame, void *context) {
    const frame_t *f = (const frame_t *)frame;
    const uint64_t *bias = (const uint64_t *)context;
    return f->left + f->right + *bias;
}

int main(void) {
    jx_asm_call_table table;
    jx_asm_call_table_init(&table);
    assert(table.version == JX_ASM_CALL_VERSION);
    assert(table.families[0x10] == NULL);
    assert(table.families[0x11] == NULL);

    uint64_t bias = 5u;
    frame_t frame = { 17u, 20u };
    assert(jx_asm_call_bind(&table, 0x10u, 0x03u, add_bias, &bias) == 0);
    assert(table.families[0x10] != NULL);
    assert(table.families[0x11] == NULL);

    const uint8_t cold[] = { 0x10u, 0x03u };
    uint64_t result = 0u;
    uint8_t used = 0u;
    assert(jx_asm_call_invoke(&table, cold, sizeof cold, &frame, &result, &used) == 0);
    assert(result == 42u);
    assert(used == 2u);

    assert(jx_asm_call_promote(&table, 0x80u, 0x10u, 0x03u) == 0);
    result = 0u;
    assert(jx_asm_call_hot(&table, 0x80u, &frame, &result) == 0);
    assert(result == 42u);

    const uint8_t hot[] = { 0x80u };
    result = 0u; used = 0u;
    assert(jx_asm_call_invoke(&table, hot, sizeof hot, &frame, &result, &used) == 0);
    assert(result == 42u);
    assert(used == 1u);

    assert(jx_asm_call_hot(&table, 0x81u, &frame, &result) != 0);
    assert(jx_asm_call_invoke(&table, cold, 1u, &frame, &result, &used) != 0);

    jx_asm_call_table_dispose(&table);
    assert(table.version == 0u);
    for (size_t i = 0; i < JX_ASM_CALL_FAMILY_COUNT; ++i) assert(table.families[i] == NULL);

    puts("jx-asm-call: 1-byte promoted and 2-byte sparse native calls ok");
    return 0;
}
