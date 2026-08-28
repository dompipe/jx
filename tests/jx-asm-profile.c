#include "../host/common/jx-asm-profile.h"
#include "../host/common/jx-asm-frame.h"
#include <assert.h>
#include <stdint.h>
#include <stdio.h>

static uint64_t ordinary_probe(void *frame_ptr, void *context) {
    jx_asm_frame *frame = (jx_asm_frame *)frame_ptr;
    uint64_t bias = *(const uint64_t *)context;
    frame->r[0] += bias;
    return frame->r[0];
}

static void invoke_many(jx_asm_call_table *table,
                        const uint8_t *code,
                        uint8_t code_len,
                        jx_asm_frame *frame,
                        uint32_t count) {
    for (uint32_t i = 0; i < count; ++i) {
        uint64_t result = 0u;
        uint8_t used = 0u;
        assert(jx_asm_call_invoke(table, code, code_len, frame, &result, &used) == 0);
        assert(used == code_len);
    }
}

int main(void) {
    jx_asm_profile profile;
    jx_asm_profile_init(&profile, 10u);
    assert(profile.version == JX_ASM_PROFILE_VERSION);

    jx_asm_call_table current;
    jx_asm_call_table next;
    jx_asm_call_table_init(&current);
    jx_asm_call_table_init(&next);

    uint64_t bias[10];
    for (uint8_t i = 0; i < 10u; ++i) {
        bias[i] = (uint64_t)i + 1u;
        assert(jx_asm_profile_register(&profile, 1u, (uint8_t)(20u + i)) == 0);
        assert(jx_asm_call_bind(&current, 1u, (uint8_t)(20u + i),
                                ordinary_probe, &bias[i]) == 0);
        assert(jx_asm_call_bind(&next, 1u, (uint8_t)(20u + i),
                                ordinary_probe, &bias[i]) == 0);
    }
    assert(jx_asm_profile_register(&profile, 1u, 20u) == -2);

    /* Candidate 0 starts hot; harvest must aggregate by canonical source. */
    assert(jx_asm_call_promote_hot(&current, 0u, 0u, 1u, 20u) == 0);

    jx_asm_frame frame;
    jx_asm_frame_init(&frame);

    for (uint8_t epoch = 0u; epoch < 2u; ++epoch) {
        for (uint8_t i = 0; i < 10u; ++i) {
            uint32_t count = 100u - i;
            if (i == 0u) {
                const uint8_t hot[] = { 0x80u };
                invoke_many(&current, hot, 1u, &frame, count);
            } else {
                const uint8_t extended[] = { 1u, (uint8_t)(20u + i) };
                invoke_many(&current, extended, 2u, &frame, count);
            }
        }
        assert(jx_asm_profile_harvest_table(&profile, &current) == 0);
        jx_asm_profile_finish_epoch(&profile);
        if (epoch == 0u)
            assert(jx_asm_profile_prepare_hot(&profile, &current, &next) == 0);
    }

    assert(profile.candidates[0].last_epoch_hits == 100u);
    assert(jx_asm_profile_prepare_hot(&profile, &current, &next) == 10);

    /* Ranking is deterministic: hottest candidates occupy earliest hot bytes. */
    for (uint8_t i = 0u; i < 10u; ++i) {
        assert(next.hot[i].fn == ordinary_probe);
        assert(next.hot[i].context == &bias[i]);
        assert(next.hot[i].source_family == 1u);
        assert(next.hot[i].source_slot == (uint8_t)(20u + i));
    }

    uint8_t used = 0u;
    uint64_t result = 0u;
    const uint8_t fastest[] = { 0x80u };
    assert(jx_asm_call_invoke(&next, fastest, 1u, &frame, &result, &used) == 0);
    assert(used == 1u);
    assert(next.hot[0].hits == 1u);

    uint64_t before = profile.candidates[0].hits;
    assert(jx_asm_profile_harvest_table(&profile, &next) == 0);
    assert(next.hot[0].hits == 0u);
    assert(profile.candidates[0].hits == before + 1u);

    assert(jx_asm_profile_hit(&profile, 1u, 20u, 1u) == 0);

    jx_asm_call_table_dispose(&current);
    jx_asm_call_table_dispose(&next);
    puts("jx-asm-profile: stable calls promoted into v4 16x8 hot map ok");
    return 0;
}
