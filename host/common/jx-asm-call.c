#include "jx-asm-call.h"
#include <stdlib.h>
#include <string.h>

void jx_asm_call_table_init(jx_asm_call_table *table) {
    if (!table) return;
    memset(table, 0, sizeof *table);
    table->version = JX_ASM_CALL_VERSION;
}

void jx_asm_call_table_dispose(jx_asm_call_table *table) {
    if (!table) return;
    for (size_t i = 0; i < JX_ASM_CALL_FAMILY_COUNT; ++i) {
        free(table->families[i]);
        table->families[i] = NULL;
    }
    memset(table, 0, sizeof *table);
}

int jx_asm_call_bind(jx_asm_call_table *table,
                     uint8_t family,
                     uint8_t slot,
                     jx_asm_call_fn fn,
                     void *context) {
    if (!table || table->version != JX_ASM_CALL_VERSION || !fn ||
        family >= JX_ASM_CALL_FAMILY_COUNT)
        return -1;
    if (!table->families[family]) {
        table->families[family] = (jx_asm_call_target *)calloc(
            JX_ASM_CALL_SLOT_COUNT, sizeof(jx_asm_call_target));
        if (!table->families[family]) return -2;
    }
    jx_asm_call_target *target = &table->families[family][slot];
    target->fn = fn;
    target->context = context;
    target->hits = 0u;
    target->source_family = family;
    target->source_slot = slot;
    target->reserved = 0u;
    return 0;
}

int jx_asm_call_promote(jx_asm_call_table *table,
                        uint8_t opcode,
                        uint8_t family,
                        uint8_t slot) {
    if (!table || table->version != JX_ASM_CALL_VERSION ||
        opcode < JX_ASM_CALL_PROMOTED_BASE || opcode >= JX_ASM_CALL_MICRO_BASE ||
        family >= JX_ASM_CALL_FAMILY_COUNT)
        return -1;
    jx_asm_call_target *page = table->families[family];
    if (!page || !page[slot].fn) return -2;
    table->promoted[(uint8_t)(opcode - JX_ASM_CALL_PROMOTED_BASE)] = page[slot];
    table->promoted[(uint8_t)(opcode - JX_ASM_CALL_PROMOTED_BASE)].hits = 0u;
    return 0;
}

int jx_asm_call_bind_micro_source(jx_asm_call_table *table,
                                  uint8_t micro_slot,
                                  uint8_t arity,
                                  uint8_t source_family,
                                  uint8_t source_slot,
                                  jx_asm_micro_fn fn,
                                  void *context) {
    if (!table || table->version != JX_ASM_CALL_VERSION || !fn ||
        micro_slot >= JX_ASM_CALL_MICRO_COUNT || arity > 3u)
        return -1;
    jx_asm_micro_target *target = &table->micro[micro_slot];
    target->fn = fn;
    target->context = context;
    target->hits = 0u;
    target->arity = arity;
    target->source_family = source_family;
    target->source_slot = source_slot;
    target->reserved = 0u;
    return 0;
}

int jx_asm_call_bind_micro(jx_asm_call_table *table,
                           uint8_t micro_slot,
                           uint8_t arity,
                           jx_asm_micro_fn fn,
                           void *context) {
    return jx_asm_call_bind_micro_source(table, micro_slot, arity,
                                         JX_ASM_CALL_SOURCE_NONE,
                                         JX_ASM_CALL_SOURCE_NONE,
                                         fn, context);
}

int jx_asm_call_encode_micro(const jx_asm_call_table *table,
                             uint8_t micro_slot,
                             uint8_t r0,
                             uint8_t r1,
                             uint8_t r2,
                             uint8_t out[2],
                             uint8_t *bytes_used) {
    if (!table || table->version != JX_ASM_CALL_VERSION || !out ||
        micro_slot >= JX_ASM_CALL_MICRO_COUNT ||
        r0 > JX_ASM_FRAME_REG_MASK || r1 > JX_ASM_FRAME_REG_MASK ||
        r2 > JX_ASM_FRAME_REG_MASK)
        return -1;
    const jx_asm_micro_target *target = &table->micro[micro_slot];
    if (!target->fn) return -2;

    out[0] = (uint8_t)(JX_ASM_CALL_MICRO_BASE |
                       ((micro_slot & JX_ASM_CALL_MICRO_REG_MASK) << 3) |
                       (r0 & JX_ASM_CALL_MICRO_REG_MASK));
    out[1] = (uint8_t)((r1 & JX_ASM_CALL_MICRO_REG_MASK) |
                       ((r2 & JX_ASM_CALL_MICRO_REG_MASK) << 3));
    if (bytes_used) *bytes_used = (target->arity <= 1u) ? 1u : 2u;
    return 0;
}

int jx_asm_call_decode(jx_asm_call_table *table,
                       const uint8_t *code,
                       size_t length,
                       jx_asm_call_decoded *out) {
    if (!table || table->version != JX_ASM_CALL_VERSION || !code ||
        length == 0u || !out)
        return -1;
    memset(out, 0, sizeof *out);

    const uint8_t first = code[0];
    if (first >= JX_ASM_CALL_MICRO_BASE) {
        const uint8_t micro_slot = (uint8_t)((first >> 3) & JX_ASM_CALL_MICRO_REG_MASK);
        const uint8_t r0 = (uint8_t)(first & JX_ASM_CALL_MICRO_REG_MASK);
        out->micro = &table->micro[micro_slot];
        if (!out->micro->fn) return -2;
        if (out->micro->arity > 1u && length < 2u) return -3;

        uint8_t r1 = 0u;
        uint8_t r2 = 0u;
        if (out->micro->arity > 1u) {
            r1 = (uint8_t)(code[1] & JX_ASM_CALL_MICRO_REG_MASK);
            r2 = (uint8_t)((code[1] >> 3) & JX_ASM_CALL_MICRO_REG_MASK);
        }
        out->selectors = jx_asm_frame_pack3(r0, r1, r2);
        out->bytes = (out->micro->arity <= 1u) ? 1u : 2u;
        out->promoted = 1u;
        out->is_micro = 1u;
        return 0;
    }

    if (first >= JX_ASM_CALL_PROMOTED_BASE) {
        out->target = &table->promoted[(uint8_t)(first - JX_ASM_CALL_PROMOTED_BASE)];
        if (!out->target->fn) return -2;
        out->bytes = 1u;
        out->promoted = 1u;
        return 0;
    }

    if (length < 2u || first >= JX_ASM_CALL_FAMILY_COUNT) return -3;
    jx_asm_call_target *page = table->families[first];
    if (!page) return -2;
    out->target = &page[code[1]];
    if (!out->target->fn) return -2;
    out->bytes = 2u;
    return 0;
}

int jx_asm_call_invoke(jx_asm_call_table *table,
                       const uint8_t *code,
                       size_t length,
                       void *frame,
                       uint64_t *result,
                       uint8_t *bytes_used) {
    jx_asm_call_decoded decoded;
    int rc = jx_asm_call_decode(table, code, length, &decoded);
    if (rc != 0) return rc;

    uint64_t value;
    if (decoded.is_micro) {
        jx_asm_call_count(&decoded.micro->hits);
        value = decoded.micro->fn((jx_asm_frame *)frame,
                                  decoded.selectors,
                                  decoded.micro->context);
    } else {
        jx_asm_call_count(&decoded.target->hits);
        value = decoded.target->fn(frame, decoded.target->context);
    }
    if (result) *result = value;
    if (bytes_used) *bytes_used = decoded.bytes;
    return 0;
}
