#ifndef JX_ASM_CALL_H
#define JX_ASM_CALL_H

#include <stddef.h>
#include <stdint.h>
#include "jx-asm-frame.h"

#define JX_ASM_CALL_VERSION 4u
#define JX_ASM_CALL_HOT_BASE 0x80u
#define JX_ASM_CALL_HOT_COUNT 128u
#define JX_ASM_CALL_HOT_BANK_COUNT 16u
#define JX_ASM_CALL_HOT_SHADOW_COUNT 8u
#define JX_ASM_CALL_HOT_BANK_MASK 0x0Fu
#define JX_ASM_CALL_HOT_SHADOW_MASK 0x07u
#define JX_ASM_CALL_FAMILY_COUNT 128u
#define JX_ASM_CALL_SLOT_COUNT 256u
#define JX_ASM_CALL_SOURCE_NONE 0xFFu

/* Compatibility names for code that still calls the v3 promotion API. */
#define JX_ASM_CALL_PROMOTED_BASE JX_ASM_CALL_HOT_BASE
#define JX_ASM_CALL_PROMOTED_COUNT JX_ASM_CALL_HOT_COUNT

/*
 * JX compact native-call ABI v4.
 *
 * The first bit defines instruction width with no exceptions:
 *
 *   1xxxxxxx             one-byte hot call
 *   0xxxxxxx xxxxxxxx    two-byte extended call
 *
 * One-byte form:
 *
 *   [1][bank:4][shadow:3]
 *
 * giving 16 banks x 8 shadows = 128 prelinked native entries. The low three
 * bits always select one of eight shadows. Canonical names, authorization,
 * type resolution, object traversal and promotion policy are completed before
 * a hot entry is installed.
 *
 * Two-byte form:
 *
 *   byte 0 = [0][family:7]
 *   byte 1 = [slot:8]
 *
 * giving the complete 15-bit 128 x 256 extended namespace. Extended calls are
 * intentionally numeric as well; strings and hashes do not belong in the
 * execution loop.
 *
 * Dynamic operands belong in the prepared frame/registers/Bags. They must not
 * turn an MSB=1 opcode back into a variable-width instruction.
 */
typedef uint64_t (*jx_asm_call_fn)(void *frame, void *context);

typedef struct {
    jx_asm_call_fn fn;
    void *context;
    uint32_t hits;
    uint8_t source_family;
    uint8_t source_slot;
    uint16_t reserved;
} jx_asm_call_target;

typedef struct {
    uint8_t version;
    /* Flat layout makes opcode & 0x7f the complete hot index. */
    jx_asm_call_target hot[JX_ASM_CALL_HOT_COUNT];
    /* Sparse extended namespace: allocate a page only for a bound family. */
    jx_asm_call_target *families[JX_ASM_CALL_FAMILY_COUNT];
} jx_asm_call_table;

typedef struct {
    jx_asm_call_target *target;
    uint8_t bytes;
    uint8_t hot;
    uint8_t bank;
    uint8_t shadow;
} jx_asm_call_decoded;

void jx_asm_call_table_init(jx_asm_call_table *table);
void jx_asm_call_table_dispose(jx_asm_call_table *table);
int jx_asm_call_bind(jx_asm_call_table *table,
                     uint8_t family,
                     uint8_t slot,
                     jx_asm_call_fn fn,
                     void *context);

/* Bind an already-resolved extended target into [1][bank:4][shadow:3]. */
int jx_asm_call_promote_hot(jx_asm_call_table *table,
                            uint8_t bank,
                            uint8_t shadow,
                            uint8_t family,
                            uint8_t slot);

/* Compatibility wrapper: opcode must have MSB=1. */
int jx_asm_call_promote(jx_asm_call_table *table,
                        uint8_t opcode,
                        uint8_t family,
                        uint8_t slot);

static inline uint8_t jx_asm_call_hot_opcode(uint8_t bank, uint8_t shadow) {
    return (uint8_t)(JX_ASM_CALL_HOT_BASE |
                     ((bank & JX_ASM_CALL_HOT_BANK_MASK) << 3) |
                     (shadow & JX_ASM_CALL_HOT_SHADOW_MASK));
}

static inline uint8_t jx_asm_call_hot_bank(uint8_t opcode) {
    return (uint8_t)((opcode >> 3) & JX_ASM_CALL_HOT_BANK_MASK);
}

static inline uint8_t jx_asm_call_hot_shadow(uint8_t opcode) {
    return (uint8_t)(opcode & JX_ASM_CALL_HOT_SHADOW_MASK);
}

int jx_asm_call_decode(jx_asm_call_table *table,
                       const uint8_t *code,
                       size_t length,
                       jx_asm_call_decoded *out);
int jx_asm_call_invoke(jx_asm_call_table *table,
                       const uint8_t *code,
                       size_t length,
                       void *frame,
                       uint64_t *result,
                       uint8_t *bytes_used);

static inline void jx_asm_call_count(uint32_t *hits) {
    if (hits && *hits != UINT32_MAX) ++(*hits);
}

/*
 * Fastest entry point. The caller has already established that opcode is hot,
 * so the path is mask -> flat target -> native call. No decoder structure,
 * family-page lookup, string lookup, hash lookup or allocation is involved.
 */
static inline int jx_asm_call_hot(jx_asm_call_table *table,
                                  uint8_t opcode,
                                  void *frame,
                                  uint64_t *result) {
    if (!table || table->version != JX_ASM_CALL_VERSION ||
        (opcode & JX_ASM_CALL_HOT_BASE) == 0u)
        return -1;
    jx_asm_call_target *target = &table->hot[opcode & 0x7Fu];
    if (!target->fn) return -2;
    jx_asm_call_count(&target->hits);
    uint64_t value = target->fn(frame, target->context);
    if (result) *result = value;
    return 0;
}

#endif
