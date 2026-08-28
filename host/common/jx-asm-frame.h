#ifndef JX_ASM_FRAME_H
#define JX_ASM_FRAME_H

#include <stddef.h>
#include <stdint.h>

#define JX_ASM_FRAME_VERSION 1u
#define JX_ASM_FRAME_REG_COUNT 8u
#define JX_ASM_FRAME_REG_BITS 3u
#define JX_ASM_FRAME_REG_MASK 0x07u

/*
 * Native ASM frame derived from the proven PASM packed-register convention.
 *
 * r0..r7 are the complete hot argument/result file. A register selector is
 * therefore exactly three bits. Canonical names/types are resolved before
 * execution and do not cross this boundary.
 *
 * Spill is optional external storage. The hot frame does not allocate it and
 * ordinary calls that fit r0..r7 never touch it.
 *
 * Convention:
 *   r0..r5  arguments / working values
 *   r6      scratch / secondary result
 *   r7      status / auxiliary value
 *   return  primary scalar result (and callers may mirror it to r0)
 */
typedef struct {
    uint64_t r[JX_ASM_FRAME_REG_COUNT];
    uint64_t *spill;
    uint32_t spill_count;
    uint16_t flags;
    uint8_t version;
    uint8_t reserved;
} jx_asm_frame;

static inline void jx_asm_frame_init(jx_asm_frame *frame) {
    if (!frame) return;
    for (size_t i = 0; i < JX_ASM_FRAME_REG_COUNT; ++i) frame->r[i] = 0u;
    frame->spill = NULL;
    frame->spill_count = 0u;
    frame->flags = 0u;
    frame->version = JX_ASM_FRAME_VERSION;
    frame->reserved = 0u;
}

static inline int jx_asm_frame_set_spill(jx_asm_frame *frame,
                                         uint64_t *spill,
                                         uint32_t count) {
    if (!frame || frame->version != JX_ASM_FRAME_VERSION) return -1;
    if ((count != 0u) && !spill) return -1;
    frame->spill = spill;
    frame->spill_count = count;
    return 0;
}

static inline int jx_asm_frame_spill_read(const jx_asm_frame *frame,
                                          uint32_t index,
                                          uint64_t *value) {
    if (!frame || frame->version != JX_ASM_FRAME_VERSION || !value ||
        !frame->spill || index >= frame->spill_count) return -1;
    *value = frame->spill[index];
    return 0;
}

static inline int jx_asm_frame_spill_write(jx_asm_frame *frame,
                                           uint32_t index,
                                           uint64_t value) {
    if (!frame || frame->version != JX_ASM_FRAME_VERSION ||
        !frame->spill || index >= frame->spill_count) return -1;
    frame->spill[index] = value;
    return 0;
}

/* Two 3-bit register selectors in one byte; high two bits remain free. */
static inline uint8_t jx_asm_frame_pack2(uint8_t a, uint8_t b) {
    return (uint8_t)((a & JX_ASM_FRAME_REG_MASK) |
                     ((b & JX_ASM_FRAME_REG_MASK) << 3));
}

static inline uint8_t jx_asm_frame_unpack2_a(uint8_t packed) {
    return (uint8_t)(packed & JX_ASM_FRAME_REG_MASK);
}

static inline uint8_t jx_asm_frame_unpack2_b(uint8_t packed) {
    return (uint8_t)((packed >> 3) & JX_ASM_FRAME_REG_MASK);
}

/* Three selectors are a 9-bit tuple, matching the earlier PASM ALU packing. */
static inline uint16_t jx_asm_frame_pack3(uint8_t dst, uint8_t a, uint8_t b) {
    return (uint16_t)((dst & JX_ASM_FRAME_REG_MASK) |
                      ((uint16_t)(a & JX_ASM_FRAME_REG_MASK) << 3) |
                      ((uint16_t)(b & JX_ASM_FRAME_REG_MASK) << 6));
}

static inline uint8_t jx_asm_frame_unpack3_dst(uint16_t packed) {
    return (uint8_t)(packed & JX_ASM_FRAME_REG_MASK);
}

static inline uint8_t jx_asm_frame_unpack3_a(uint16_t packed) {
    return (uint8_t)((packed >> 3) & JX_ASM_FRAME_REG_MASK);
}

static inline uint8_t jx_asm_frame_unpack3_b(uint16_t packed) {
    return (uint8_t)((packed >> 6) & JX_ASM_FRAME_REG_MASK);
}

#endif
