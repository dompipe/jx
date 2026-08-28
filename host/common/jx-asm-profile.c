#include "jx-asm-profile.h"
#include <limits.h>
#include <string.h>

static jx_asm_profile_candidate *find_candidate(jx_asm_profile *profile,
                                                uint8_t family,
                                                uint8_t slot) {
    if (!profile) return NULL;
    for (uint8_t i = 0; i < profile->candidate_count; ++i) {
        jx_asm_profile_candidate *c = &profile->candidates[i];
        if (c->family == family && c->slot == slot) return c;
    }
    return NULL;
}

static int find_candidate_index(const jx_asm_profile *profile,
                                uint8_t family,
                                uint8_t slot) {
    if (!profile) return -1;
    for (uint8_t i = 0; i < profile->candidate_count; ++i) {
        const jx_asm_profile_candidate *c = &profile->candidates[i];
        if (c->family == family && c->slot == slot) return (int)i;
    }
    return -1;
}

static void add_hits(jx_asm_profile_candidate *c, uint64_t count) {
    if (!c || count == 0u) return;
    if (UINT64_MAX - c->hits < count) c->hits = UINT64_MAX;
    else c->hits += count;
}

void jx_asm_profile_init(jx_asm_profile *profile, uint64_t minimum_epoch_hits) {
    if (!profile) return;
    memset(profile, 0, sizeof *profile);
    profile->version = JX_ASM_PROFILE_VERSION;
    profile->minimum_epoch_hits = minimum_epoch_hits ? minimum_epoch_hits : 1u;
}

int jx_asm_profile_register(jx_asm_profile *profile,
                            uint8_t family,
                            uint8_t slot) {
    if (!profile || profile->version != JX_ASM_PROFILE_VERSION ||
        family >= JX_ASM_CALL_FAMILY_COUNT) return -1;
    if (find_candidate(profile, family, slot)) return -2;
    if (profile->candidate_count >= JX_ASM_PROFILE_MAX_CANDIDATES) return -3;
    jx_asm_profile_candidate *c = &profile->candidates[profile->candidate_count++];
    memset(c, 0, sizeof *c);
    c->family = family;
    c->slot = slot;
    return 0;
}

int jx_asm_profile_hit(jx_asm_profile *profile,
                       uint8_t family,
                       uint8_t slot,
                       uint64_t count) {
    if (!profile || profile->version != JX_ASM_PROFILE_VERSION || count == 0u)
        return -1;
    jx_asm_profile_candidate *c = find_candidate(profile, family, slot);
    if (!c) return -2;
    add_hits(c, count);
    return 0;
}

int jx_asm_profile_harvest_table(jx_asm_profile *profile,
                                 jx_asm_call_table *table) {
    if (!profile || profile->version != JX_ASM_PROFILE_VERSION ||
        !table || table->version != JX_ASM_CALL_VERSION) return -1;

    for (uint8_t family = 0u; family < JX_ASM_CALL_FAMILY_COUNT; ++family) {
        jx_asm_call_target *page = table->families[family];
        if (!page) continue;
        for (uint16_t slot = 0u; slot < JX_ASM_CALL_SLOT_COUNT; ++slot) {
            jx_asm_call_target *target = &page[slot];
            if (!target->fn || target->hits == 0u) continue;
            jx_asm_profile_candidate *c = find_candidate(profile,
                                                          target->source_family,
                                                          target->source_slot);
            if (c) add_hits(c, target->hits);
            target->hits = 0u;
        }
    }

    for (uint8_t i = 0u; i < JX_ASM_CALL_HOT_COUNT; ++i) {
        jx_asm_call_target *target = &table->hot[i];
        if (!target->fn || target->hits == 0u) continue;
        jx_asm_profile_candidate *c = find_candidate(profile,
                                                      target->source_family,
                                                      target->source_slot);
        if (c) add_hits(c, target->hits);
        target->hits = 0u;
    }
    return 0;
}

void jx_asm_profile_finish_epoch(jx_asm_profile *profile) {
    if (!profile || profile->version != JX_ASM_PROFILE_VERSION) return;
    ++profile->epoch;
    for (uint8_t i = 0; i < profile->candidate_count; ++i) {
        jx_asm_profile_candidate *c = &profile->candidates[i];
        uint64_t epoch_hits = c->hits - c->epoch_base;
        c->last_epoch_hits = epoch_hits;
        c->epoch_base = c->hits;
        if (epoch_hits >= profile->minimum_epoch_hits) {
            if (c->stable_epochs != UINT8_MAX) ++c->stable_epochs;
        } else {
            c->stable_epochs = 0u;
        }
    }
}

static int better(const jx_asm_profile_candidate *a,
                  const jx_asm_profile_candidate *b) {
    if (!b) return 1;
    if (a->last_epoch_hits != b->last_epoch_hits)
        return a->last_epoch_hits > b->last_epoch_hits;
    if (a->family != b->family) return a->family < b->family;
    return a->slot < b->slot;
}

int jx_asm_profile_prepare_hot(const jx_asm_profile *profile,
                               const jx_asm_call_table *source_table,
                               jx_asm_call_table *next_table) {
    if (!profile || profile->version != JX_ASM_PROFILE_VERSION ||
        !source_table || source_table->version != JX_ASM_CALL_VERSION ||
        !next_table || next_table->version != JX_ASM_CALL_VERSION) return -1;

    uint8_t chosen[JX_ASM_PROFILE_MAX_CANDIDATES] = {0};
    int bound = 0;
    for (uint16_t hot_index = 0u;
         hot_index < JX_ASM_CALL_HOT_COUNT && hot_index < profile->candidate_count;
         ++hot_index) {
        const jx_asm_profile_candidate *best = NULL;
        int best_index = -1;
        for (uint8_t i = 0; i < profile->candidate_count; ++i) {
            const jx_asm_profile_candidate *c = &profile->candidates[i];
            if (chosen[i] || c->stable_epochs < JX_ASM_PROFILE_STABLE_EPOCHS ||
                c->last_epoch_hits < profile->minimum_epoch_hits) continue;
            if (better(c, best)) {
                best = c;
                best_index = (int)i;
            }
        }
        if (!best) break;
        if (!source_table->families[best->family] ||
            !source_table->families[best->family][best->slot].fn) return -2;
        jx_asm_call_target target = source_table->families[best->family][best->slot];
        target.hits = 0u;
        next_table->hot[hot_index] = target;
        chosen[best_index] = 1u;
        ++bound;
    }
    return bound;
}
