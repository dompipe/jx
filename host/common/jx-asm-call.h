#ifndef JX_ASM_CALL_H
#define JX_ASM_CALL_H

#include <stddef.h>
#include <stdint.h>

#define JX_ASM_CALL_VERSION 1u
#define JX_ASM_CALL_PROMOTED_BASE 0x80u
#define JX_ASM_CALL_PROMOTED_COUNT 128u
#define JX_ASM_CALL_FAMILY_COUNT 128u
#define JX_ASM_CALL_SLOT_COUNT 256u

/*
 * Compact native-call ABI.
 *
 * Canonical names are resolved and authorized before binding. The hot path
 * carries only either:
 *   - one promoted byte 0x80..0xFF, or
 *   - two bytes: family (0x00..0x7F), slot (0x00..0xFF).
 *
 * The function receives an already-prepared register/frame pointer. It does
 * not receive names, capability strings, or lookup metadata.
 */
typedef uint64_t (*jx_asm_call_fn)(void *frame, void *context);

typedef struct {
    jx_asm_call_fn fn;
    void *context;
} jx_asm_call_target;

typedef struct {
    uint8_t version;
    jx_asm_call_target promoted[JX_ASM_CALL_PROMOTED_COUNT];
    jx_asm_call_target families[JX_ASM_CALL_FAMILY_COUNT][JX_ASM_CALL_SLOT_COUNT];
} jx_asm_call_table;

typedef struct {
    jx_asm_call_target target;
    uint8_t bytes;
    uint8_t promoted;
} jx_asm_call_decoded;

void jx_asm_call_table_init(jx_asm_call_table *table);
int jx_asm_call_bind(jx_asm_call_table *table,
                     uint8_t family,
                     uint8_t slot,
                     jx_asm_call_fn fn,
                     void *context);
int jx_asm_call_promote(jx_asm_call_table *table,
                        uint8_t opcode,
                        uint8_t family,
                        uint8_t slot);
int jx_asm_call_decode(const jx_asm_call_table *table,
                       const uint8_t *code,
                       size_t length,
                       jx_asm_call_decoded *out);
int jx_asm_call_invoke(const jx_asm_call_table *table,
                       const uint8_t *code,
                       size_t length,
                       void *frame,
                       uint64_t *result,
                       uint8_t *bytes_used);

#endif
