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

int jx_asm_call_promote_hot(jx_asm_call_table *table,
                            uint8_t bank,
                            uint8_t shadow,
                            uint8_t family,
                            uint8_t slot) {
    if (!table || table->version != JX_ASM_CALL_VERSION ||
        bank >= JX_ASM_CALL_HOT_BANK_COUNT ||
        shadow >= JX_ASM_CALL_HOT_SHADOW_COUNT ||
        family >= JX_ASM_CALL_FAMILY_COUNT)
        return -1;
    jx_asm_call_target *page = table->families[family];
    if (!page || !page[slot].fn) return -2;
    const uint8_t index = (uint8_t)((bank << 3) | shadow);
    table->hot[index] = page[slot];
    table->hot[index].hits = 0u;
    return 0;
}

int jx_asm_call_promote(jx_asm_call_table *table,
                        uint8_t opcode,
                        uint8_t family,
                        uint8_t slot) {
    if ((opcode & JX_ASM_CALL_HOT_BASE) == 0u) return -1;
    return jx_asm_call_promote_hot(table,
                                   jx_asm_call_hot_bank(opcode),
                                   jx_asm_call_hot_shadow(opcode),
                                   family,
                                   slot);
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

    /* MSB=1 is always complete in one byte. */
    if (first & JX_ASM_CALL_HOT_BASE) {
        out->target = &table->hot[first & 0x7Fu];
        if (!out->target->fn) return -2;
        out->bytes = 1u;
        out->hot = 1u;
        out->bank = jx_asm_call_hot_bank(first);
        out->shadow = jx_asm_call_hot_shadow(first);
        return 0;
    }

    /* MSB=0 is always a two-byte extended family/slot call. */
    if (length < 2u) return -3;
    jx_asm_call_target *page = table->families[first & 0x7Fu];
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
    if (!table || table->version != JX_ASM_CALL_VERSION || !code || length == 0u)
        return -1;

    /* Keep the hot path out of the general decoder entirely. */
    if (code[0] & JX_ASM_CALL_HOT_BASE) {
        int rc = jx_asm_call_hot(table, code[0], frame, result);
        if (rc == 0 && bytes_used) *bytes_used = 1u;
        return rc;
    }

    if (length < 2u) return -3;
    jx_asm_call_target *page = table->families[code[0] & 0x7Fu];
    if (!page) return -2;
    jx_asm_call_target *target = &page[code[1]];
    if (!target->fn) return -2;
    jx_asm_call_count(&target->hits);
    uint64_t value = target->fn(frame, target->context);
    if (result) *result = value;
    if (bytes_used) *bytes_used = 2u;
    return 0;
}
