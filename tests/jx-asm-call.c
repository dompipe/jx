#include "../host/common/jx-asm-call.h"
#include "../host/common/jx-asm-frame.h"
#include <assert.h>
#include <stdio.h>

static uint64_t add_bias(void *frame_ptr, void *context) {
    jx_asm_frame *frame = (jx_asm_frame *)frame_ptr;
    const uint64_t *bias = (const uint64_t *)context;
    assert(frame && frame->version == JX_ASM_FRAME_VERSION);
    frame->r[6] = frame->r[0] + frame->r[1];
    return frame->r[6] + *bias;
}

int main(void) {
    jx_asm_call_table table;
    jx_asm_call_table_init(&table);
    assert(table.version == JX_ASM_CALL_VERSION);
    assert(JX_ASM_CALL_VERSION == 4u);
    assert(JX_ASM_CALL_HOT_COUNT == 128u);
    assert(JX_ASM_CALL_HOT_BANK_COUNT == 16u);
    assert(JX_ASM_CALL_HOT_SHADOW_COUNT == 8u);

    jx_asm_frame frame;
    jx_asm_frame_init(&frame);
    frame.r[0] = 17u;
    frame.r[1] = 20u;

    uint64_t bias = 5u;
    assert(jx_asm_call_bind(&table, 0x10u, 0x03u, add_bias, &bias) == 0);

    /* Extended form: MSB=0 means exactly two bytes. */
    const uint8_t extended[] = { 0x10u, 0x03u };
    uint64_t result = 0u;
    uint8_t used = 0u;
    assert(jx_asm_call_invoke(&table, extended, sizeof extended, &frame, &result, &used) == 0);
    assert(result == 42u);
    assert(used == 2u);
    assert(jx_asm_call_invoke(&table, extended, 1u, &frame, &result, &used) != 0);

    /* Hot form: [1][bank:4][shadow:3] is always one complete byte. */
    assert(jx_asm_call_promote_hot(&table, 0u, 0u, 0x10u, 0x03u) == 0);
    const uint8_t hot0 = jx_asm_call_hot_opcode(0u, 0u);
    assert(hot0 == 0x80u);
    assert(jx_asm_call_hot_bank(hot0) == 0u);
    assert(jx_asm_call_hot_shadow(hot0) == 0u);
    assert(jx_asm_call_hot(&table, hot0, &frame, &result) == 0);
    assert(result == 42u);

    /* Prove the top bank/shadow also occupies one byte: 11111111. */
    assert(jx_asm_call_promote_hot(&table, 15u, 7u, 0x10u, 0x03u) == 0);
    const uint8_t hot127 = jx_asm_call_hot_opcode(15u, 7u);
    assert(hot127 == 0xFFu);
    assert(jx_asm_call_hot_bank(hot127) == 15u);
    assert(jx_asm_call_hot_shadow(hot127) == 7u);
    const uint8_t one_byte[] = { hot127 };
    result = 0u; used = 0u;
    assert(jx_asm_call_invoke(&table, one_byte, sizeof one_byte, &frame, &result, &used) == 0);
    assert(result == 42u);
    assert(used == 1u);

    /* Compatibility promotion accepts every MSB=1 opcode, including 0xC0..0xFF. */
    assert(jx_asm_call_promote(&table, 0xC0u, 0x10u, 0x03u) == 0);
    const uint8_t c0[] = { 0xC0u };
    assert(jx_asm_call_invoke(&table, c0, sizeof c0, &frame, &result, &used) == 0);
    assert(used == 1u);

    jx_asm_call_decoded decoded;
    assert(jx_asm_call_decode(&table, one_byte, 1u, &decoded) == 0);
    assert(decoded.hot == 1u && decoded.bytes == 1u);
    assert(decoded.bank == 15u && decoded.shadow == 7u);
    assert(jx_asm_call_decode(&table, extended, 2u, &decoded) == 0);
    assert(decoded.hot == 0u && decoded.bytes == 2u);

    jx_asm_call_table_dispose(&table);
    assert(table.version == 0u);
    puts("jx-asm-call: v4 1xxxxxxx hot / 0xxxxxxx xxxxxxxx extended ABI ok");
    return 0;
}
