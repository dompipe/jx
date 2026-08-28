#include "jx-asm-call.h"
#include <string.h>

void jx_asm_call_table_init(jx_asm_call_table *table) {
    if (!table) return;
    memset(table, 0, sizeof *table);
    table->version = JX_ASM_CALL_VERSION;
}

int jx_asm_call_bind(jx_asm_call_table *table,
                     uint8_t family,
                     uint8_t slot,
                     jx_asm_call_fn fn,
                     void *context) {
    if (!table || table->version != JX_ASM_CALL_VERSION || !fn || family >= JX_ASM_CALL_FAMILY_COUNT)
        return -1;
    table->families[family][slot].fn = fn;
    table->families[family][slot].context = context;
    return 0;
}

int jx_asm_call_promote(jx_asm_call_table *table,
                        uint8_t opcode,
                        uint8_t family,
                        uint8_t slot) {
    if (!table || table->version != JX_ASM_CALL_VERSION ||
        opcode < JX_ASM_CALL_PROMOTED_BASE || family >= JX_ASM_CALL_FAMILY_COUNT)
        return -1;
    jx_asm_call_target target = table->families[family][slot];
    if (!target.fn) return -2;
    table->promoted[(uint8_t)(opcode - JX_ASM_CALL_PROMOTED_BASE)] = target;
    return 0;
}

int jx_asm_call_decode(const jx_asm_call_table *table,
                       const uint8_t *code,
                       size_t length,
                       jx_asm_call_decoded *out) {
    if (!table || table->version != JX_ASM_CALL_VERSION || !code || length == 0u || !out)
        return -1;
    memset(out, 0, sizeof *out);

    uint8_t first = code[0];
    if (first >= JX_ASM_CALL_PROMOTED_BASE) {
        out->target = table->promoted[(uint8_t)(first - JX_ASM_CALL_PROMOTED_BASE)];
        if (!out->target.fn) return -2;
        out->bytes = 1u;
        out->promoted = 1u;
        return 0;
    }

    if (length < 2u || first >= JX_ASM_CALL_FAMILY_COUNT) return -3;
    out->target = table->families[first][code[1]];
    if (!out->target.fn) return -2;
    out->bytes = 2u;
    return 0;
}

int jx_asm_call_invoke(const jx_asm_call_table *table,
                       const uint8_t *code,
                       size_t length,
                       void *frame,
                       uint64_t *result,
                       uint8_t *bytes_used) {
    jx_asm_call_decoded decoded;
    int rc = jx_asm_call_decode(table, code, length, &decoded);
    if (rc != 0) return rc;
    uint64_t value = decoded.target.fn(frame, decoded.target.context);
    if (result) *result = value;
    if (bytes_used) *bytes_used = decoded.bytes;
    return 0;
}
