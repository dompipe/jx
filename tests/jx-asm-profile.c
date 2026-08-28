#include "../host/common/jx-asm-profile.h"
#include "../host/common/jx-asm-frame.h"
#include <assert.h>
#include <stdint.h>
#include <stdio.h>

static uint64_t micro_probe(jx_asm_frame *frame, uint16_t selectors, void *context) {
    uint8_t dst = (uint8_t)(selectors & 7u);
    uint64_t bias = *(const uint64_t *)context;
    frame->r[dst] += bias;
    return frame->r[dst];
}

int main(void) {
    jx_asm_profile profile;
    jx_asm_profile_init(&profile, 10u);
    assert(profile.version == JX_ASM_PROFILE_VERSION);

    uint64_t bias[10];
    for (uint8_t i = 0; i < 10u; ++i) {
        bias[i] = (uint64_t)i + 1u;
        assert(jx_asm_profile_register(&profile, 1u, (uint8_t)(20u + i), 1u,
                                       micro_probe, &bias[i]) == 0);
    }
    assert(jx_asm_profile_register(&profile, 1u, 20u, 1u, micro_probe, &bias[0]) == -2);

    /* One hot burst is intentionally insufficient. */
    for (uint8_t i = 0; i < 10u; ++i)
        assert(jx_asm_profile_hit(&profile, 1u, (uint8_t)(20u + i), 100u - i) == 0);
    jx_asm_profile_finish_epoch(&profile);

    jx_asm_call_table current;
    jx_asm_call_table next;
    jx_asm_call_table_init(&current);
    jx_asm_call_table_init(&next);
    assert(jx_asm_profile_prepare_micro(&profile, &next) == 0);
    for (uint8_t i = 0; i < JX_ASM_CALL_MICRO_COUNT; ++i) {
        assert(current.micro[i].fn == NULL);
        assert(next.micro[i].fn == NULL);
    }

    /* Second hot epoch makes the candidates stable. */
    for (uint8_t i = 0; i < 10u; ++i)
        assert(jx_asm_profile_hit(&profile, 1u, (uint8_t)(20u + i), 100u - i) == 0);
    jx_asm_profile_finish_epoch(&profile);
    assert(jx_asm_profile_prepare_micro(&profile, &next) == 8);

    /* Top eight by last-epoch frequency were selected, in deterministic order. */
    for (uint8_t i = 0; i < 8u; ++i) {
        assert(next.micro[i].fn == micro_probe);
        assert(next.micro[i].context == &bias[i]);
        assert(next.micro[i].arity == 1u);
        assert(current.micro[i].fn == NULL);
    }

    jx_asm_frame frame;
    jx_asm_frame_init(&frame);
    frame.r[3] = 40u;
    uint8_t code[2] = {0};
    uint8_t used = 0u;
    uint64_t result = 0u;
    assert(jx_asm_call_encode_micro(&next, 0u, 3u, 0u, 0u, code, &used) == 0);
    assert(used == 1u);
    assert(jx_asm_call_invoke(&next, code, used, &frame, &result, &used) == 0);
    assert(result == 41u);

    /* A cold epoch resets stability instead of preserving stale promotion eligibility. */
    assert(jx_asm_profile_hit(&profile, 1u, 20u, 1u) == 0);
    jx_asm_profile_finish_epoch(&profile);
    assert(profile.candidates[0].stable_epochs == 0u);

    jx_asm_call_table_dispose(&current);
    jx_asm_call_table_dispose(&next);
    puts("jx-asm-profile: stable top-eight next-generation micro promotion ok");
    return 0;
}
