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

static uint64_t micro_identity(jx_asm_frame *frame, uint16_t selectors, void *context) {
    const uint64_t *bias = (const uint64_t *)context;
    const uint8_t src = jx_asm_frame_unpack3_dst(selectors);
    return frame->r[src] + *bias;
}

static uint64_t micro_add3(jx_asm_frame *frame, uint16_t selectors, void *context) {
    (void)context;
    const uint8_t dst = jx_asm_frame_unpack3_dst(selectors);
    const uint8_t a = jx_asm_frame_unpack3_a(selectors);
    const uint8_t b = jx_asm_frame_unpack3_b(selectors);
    frame->r[dst] = frame->r[a] + frame->r[b];
    return frame->r[dst];
}

int main(void) {
    jx_asm_call_table table;
    jx_asm_call_table_init(&table);
    assert(table.version == JX_ASM_CALL_VERSION);
    assert(table.families[0x10] == NULL);
    assert(table.families[0x11] == NULL);

    jx_asm_frame frame;
    jx_asm_frame_init(&frame);
    assert(frame.version == JX_ASM_FRAME_VERSION);
    frame.r[0] = 17u;
    frame.r[1] = 20u;
    frame.r[2] = 100u;
    frame.r[3] = 7u;
    frame.r[4] = 11u;

    uint8_t p2 = jx_asm_frame_pack2(3u, 6u);
    assert(jx_asm_frame_unpack2_a(p2) == 3u);
    assert(jx_asm_frame_unpack2_b(p2) == 6u);
    uint16_t p3 = jx_asm_frame_pack3(2u, 4u, 7u);
    assert(jx_asm_frame_unpack3_dst(p3) == 2u);
    assert(jx_asm_frame_unpack3_a(p3) == 4u);
    assert(jx_asm_frame_unpack3_b(p3) == 7u);

    uint64_t spill[2] = { 101u, 202u };
    uint64_t spill_value = 0u;
    assert(jx_asm_frame_set_spill(&frame, spill, 2u) == 0);
    assert(jx_asm_frame_spill_read(&frame, 1u, &spill_value) == 0);
    assert(spill_value == 202u);
    assert(jx_asm_frame_spill_write(&frame, 0u, 303u) == 0);
    assert(spill[0] == 303u);

    uint64_t bias = 5u;
    assert(jx_asm_call_bind(&table, 0x10u, 0x03u, add_bias, &bias) == 0);
    assert(table.families[0x10] != NULL);
    assert(table.families[0x11] == NULL);

    const uint8_t cold[] = { 0x10u, 0x03u };
    uint64_t result = 0u;
    uint8_t used = 0u;
    assert(jx_asm_call_invoke(&table, cold, sizeof cold, &frame, &result, &used) == 0);
    assert(result == 42u);
    assert(frame.r[6] == 37u);
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

    /* One-register microcall: target slot + source selector are fused into one byte. */
    assert(jx_asm_call_bind_micro(&table, 2u, 1u, micro_identity, &bias) == 0);
    uint8_t micro[2] = {0u, 0u};
    assert(jx_asm_call_encode_micro(&table, 2u, 4u, 0u, 0u, micro, &used) == 0);
    assert(used == 1u);
    assert(micro[0] == (uint8_t)(JX_ASM_CALL_MICRO_BASE | (2u << 3) | 4u));
    result = 0u;
    assert(jx_asm_call_invoke(&table, micro, used, &frame, &result, &used) == 0);
    assert(result == 16u);
    assert(used == 1u);

    /* Three-register microcall: dst is fused into byte 0, a+b share byte 1. */
    assert(jx_asm_call_bind_micro(&table, 3u, 3u, micro_add3, NULL) == 0);
    assert(jx_asm_call_encode_micro(&table, 3u, 2u, 3u, 4u, micro, &used) == 0);
    assert(used == 2u);
    assert((micro[0] & 7u) == 2u);
    assert((micro[1] & 7u) == 3u);
    assert(((micro[1] >> 3) & 7u) == 4u);
    result = 0u;
    assert(jx_asm_call_invoke(&table, micro, used, &frame, &result, &used) == 0);
    assert(result == 18u);
    assert(frame.r[2] == 18u);
    assert(used == 2u);

    /* Arity determines width; a truncated 3-register microcall must fail. */
    assert(jx_asm_call_invoke(&table, micro, 1u, &frame, &result, &used) != 0);
    assert(jx_asm_call_hot(&table, 0xC0u, &frame, &result) != 0);
    assert(jx_asm_call_invoke(&table, cold, 1u, &frame, &result, &used) != 0);

    jx_asm_call_table_dispose(&table);
    assert(table.version == 0u);
    for (size_t i = 0; i < JX_ASM_CALL_FAMILY_COUNT; ++i) assert(table.families[i] == NULL);

    puts("jx-asm-call: fused 1-byte/2-byte register-selecting ASM microcalls ok");
    return 0;
}
