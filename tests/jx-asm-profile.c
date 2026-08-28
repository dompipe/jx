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

static uint64_t micro_probe(jx_asm_frame *frame, uint16_t selectors, void *context) {
    uint8_t dst = (uint8_t)(selectors & 7u);
    uint64_t bias = *(const uint64_t *)context;
    frame->r[dst] += bias;
    return frame->r[dst];
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
        assert(jx_asm_profile_register(&profile, 1u, (uint8_t)(20u + i), 1u,
                                       micro_probe, &bias[i]) == 0);
        assert(jx_asm_call_bind(&current, 1u, (uint8_t)(20u + i),
                                ordinary_probe, &bias[i]) == 0);
    }
    assert(jx_asm_profile_register(&profile, 1u, 20u, 1u, micro_probe, &bias[0]) == -2);

    /* Route candidate 0 through a promoted alias to prove alias hits aggregate. */
    assert(jx_asm_call_promote(&current, 0x80u, 1u, 20u) == 0);

    jx_asm_frame frame;
    jx_asm_frame_init(&frame);

    /* One real hot epoch is intentionally insufficient for micro promotion. */
    for (uint8_t i = 0; i < 10u; ++i) {
        uint32_t count = 100u - i;
        if (i == 0u) {
            const uint8_t hot[] = { 0x80u };
            invoke_many(&current, hot, 1u, &frame, count);
            assert(current.promoted[0].hits == count);
        } else {
            const uint8_t cold[] = { 1u, (uint8_t)(20u + i) };
            invoke_many(&current, cold, 2u, &frame, count);
            assert(current.families[1u][20u + i].hits == count);
        }
    }
    assert(jx_asm_profile_harvest_table(&profile, &current) == 0);
    assert(current.promoted[0].hits == 0u);
    assert(current.families[1u][21u].hits == 0u);
    jx_asm_profile_finish_epoch(&profile);
    assert(profile.candidates[0].last_epoch_hits == 100u);
    assert(jx_asm_profile_prepare_micro(&profile, &next) == 0);

    /* Second real epoch makes the candidates stable. */
    for (uint8_t i = 0; i < 10u; ++i) {
        uint32_t count = 100u - i;
        if (i == 0u) {
            const uint8_t hot[] = { 0x80u };
            invoke_many(&current, hot, 1u, &frame, count);
        } else {
            const uint8_t cold[] = { 1u, (uint8_t)(20u + i) };
            invoke_many(&current, cold, 2u, &frame, count);
        }
    }
    assert(jx_asm_profile_harvest_table(&profile, &current) == 0);
    jx_asm_profile_finish_epoch(&profile);
    assert(jx_asm_profile_prepare_micro(&profile, &next) == 8);

    /* Top eight by harvested frequency were selected deterministically. */
    for (uint8_t i = 0; i < 8u; ++i) {
        assert(next.micro[i].fn == micro_probe);
        assert(next.micro[i].context == &bias[i]);
        assert(next.micro[i].arity == 1u);
        assert(next.micro[i].source_family == 1u);
        assert(next.micro[i].source_slot == (uint8_t)(20u + i));
        assert(current.micro[i].fn == NULL);
    }

    /* A promoted micro alias also counts locally and harvests to the same source. */
    frame.r[3] = 40u;
    uint8_t code[2] = {0};
    uint8_t used = 0u;
    uint64_t result = 0u;
    assert(jx_asm_call_encode_micro(&next, 0u, 3u, 0u, 0u, code, &used) == 0);
    assert(used == 1u);
    invoke_many(&next, code, used, &frame, 11u);
    assert(next.micro[0].hits == 11u);
    uint64_t before = profile.candidates[0].hits;
    assert(jx_asm_profile_harvest_table(&profile, &next) == 0);
    assert(next.micro[0].hits == 0u);
    assert(profile.candidates[0].hits == before + 11u);

    /* Direct compatibility hook still works, but normal execution no longer needs it. */
    assert(jx_asm_profile_hit(&profile, 1u, 20u, 1u) == 0);

    jx_asm_call_table_dispose(&current);
    jx_asm_call_table_dispose(&next);
    puts("jx-asm-profile: target-local counters + off-path alias harvest ok");
    return 0;
}
