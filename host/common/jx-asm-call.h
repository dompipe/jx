#ifndef JX_ASM_CALL_H
#define JX_ASM_CALL_H

#include <stddef.h>
#include <stdint.h>
#include "jx-asm-frame.h"

#define JX_ASM_CALL_VERSION 2u
#define JX_ASM_CALL_PROMOTED_BASE 0x80u
#define JX_ASM_CALL_MICRO_BASE 0xC0u
#define JX_ASM_CALL_PROMOTED_COUNT 64u
#define JX_ASM_CALL_MICRO_COUNT 8u
#define JX_ASM_CALL_FAMILY_COUNT 128u
#define JX_ASM_CALL_SLOT_COUNT 256u
#define JX_ASM_CALL_MICRO_REG_MASK 0x07u

/*
 * Compact native-call ABI.
 *
 * Canonical names are resolved and authorized before binding. The execution
 * stream has three tiers:
 *
 *   0x00..0x7F + slot : sparse family+slot call (2 bytes)
 *   0x80..0xBF        : direct promoted call (1 byte)
 *   0xC0..0xFF        : fused microcall: 3-bit target + 3-bit first register
 *
 * A micro target declares arity 0..3 at bind time. Arity 0/1 consumes one
 * byte. Arity 2/3 consumes a second byte carrying r1 in bits 0..2 and r2 in
 * bits 3..5. No operand tags or names survive into the hot stream.
 *
 * Call tables are generation-scoped: hot swap prepares a replacement table
 * and switches the table root atomically with the program generation.
 */
typedef uint64_t (*jx_asm_call_fn)(void *frame, void *context);
typedef uint64_t (*jx_asm_micro_fn)(jx_asm_frame *frame,
                                    uint16_t selectors,
                                    void *context);

typedef struct {
    jx_asm_call_fn fn;
    void *context;
} jx_asm_call_target;

typedef struct {
    jx_asm_micro_fn fn;
    void *context;
    uint8_t arity;
} jx_asm_micro_target;

typedef struct {
    uint8_t version;
    jx_asm_call_target promoted[JX_ASM_CALL_PROMOTED_COUNT];
    jx_asm_micro_target micro[JX_ASM_CALL_MICRO_COUNT];
    /* Sparse: allocate a 256-slot page only for a family that is actually bound. */
    jx_asm_call_target *families[JX_ASM_CALL_FAMILY_COUNT];
} jx_asm_call_table;

typedef struct {
    jx_asm_call_target target;
    jx_asm_micro_target micro;
    uint16_t selectors;
    uint8_t bytes;
    uint8_t promoted;
    uint8_t is_micro;
} jx_asm_call_decoded;

void jx_asm_call_table_init(jx_asm_call_table *table);
void jx_asm_call_table_dispose(jx_asm_call_table *table);
int jx_asm_call_bind(jx_asm_call_table *table,
                     uint8_t family,
                     uint8_t slot,
                     jx_asm_call_fn fn,
                     void *context);
int jx_asm_call_promote(jx_asm_call_table *table,
                        uint8_t opcode,
                        uint8_t family,
                        uint8_t slot);
int jx_asm_call_bind_micro(jx_asm_call_table *table,
                           uint8_t micro_slot,
                           uint8_t arity,
                           jx_asm_micro_fn fn,
                           void *context);
int jx_asm_call_encode_micro(const jx_asm_call_table *table,
                             uint8_t micro_slot,
                             uint8_t r0,
                             uint8_t r1,
                             uint8_t r2,
                             uint8_t out[2],
                             uint8_t *bytes_used);
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

/*
 * Direct promoted path: one opcode byte indexes the prelinked target and
 * immediately enters native/ASM code. Microcalls use jx_asm_call_invoke()
 * because the opcode itself also carries a register selector.
 */
static inline int jx_asm_call_hot(const jx_asm_call_table *table,
                                  uint8_t opcode,
                                  void *frame,
                                  uint64_t *result) {
    if (!table || table->version != JX_ASM_CALL_VERSION ||
        opcode < JX_ASM_CALL_PROMOTED_BASE || opcode >= JX_ASM_CALL_MICRO_BASE)
        return -1;
    jx_asm_call_target target = table->promoted[(uint8_t)(opcode - JX_ASM_CALL_PROMOTED_BASE)];
    if (!target.fn) return -2;
    uint64_t value = target.fn(frame, target.context);
    if (result) *result = value;
    return 0;
}

#endif
