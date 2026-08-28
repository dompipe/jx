#ifndef JX_ASM_PROFILE_H
#define JX_ASM_PROFILE_H

#include <stddef.h>
#include <stdint.h>
#include "jx-asm-call.h"

#define JX_ASM_PROFILE_VERSION 1u
#define JX_ASM_PROFILE_MAX_CANDIDATES 64u
#define JX_ASM_PROFILE_STABLE_EPOCHS 2u

typedef struct {
    uint8_t family;
    uint8_t slot;
    uint8_t arity;
    uint8_t stable_epochs;
    uint64_t hits;
    uint64_t epoch_base;
    uint64_t last_epoch_hits;
    jx_asm_micro_fn micro_fn;
    void *context;
} jx_asm_profile_candidate;

typedef struct {
    uint8_t version;
    uint8_t candidate_count;
    uint16_t reserved;
    uint64_t epoch;
    uint64_t minimum_epoch_hits;
    jx_asm_profile_candidate candidates[JX_ASM_PROFILE_MAX_CANDIDATES];
} jx_asm_profile;

/*
 * Profiling never edits a live call table. It only accumulates observations.
 * A completed profile is consumed while preparing the NEXT generation's table.
 */
void jx_asm_profile_init(jx_asm_profile *profile, uint64_t minimum_epoch_hits);
int jx_asm_profile_register(jx_asm_profile *profile,
                            uint8_t family,
                            uint8_t slot,
                            uint8_t arity,
                            jx_asm_micro_fn micro_fn,
                            void *context);
int jx_asm_profile_hit(jx_asm_profile *profile,
                       uint8_t family,
                       uint8_t slot,
                       uint64_t count);
void jx_asm_profile_finish_epoch(jx_asm_profile *profile);

/*
 * Select up to JX_ASM_CALL_MICRO_COUNT stable candidates and bind them to the
 * supplied NEXT-generation call table. Returns the number of bound micro slots.
 * Ranking is deterministic: last epoch hits descending, then family/slot.
 */
int jx_asm_profile_prepare_micro(const jx_asm_profile *profile,
                                 jx_asm_call_table *next_table);

#endif
