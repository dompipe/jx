#include <stdio.h>
#include <stdint.h>
#include <string.h>
#include "../host/common/jx-live-patch.h"

static int fake_verify(const jx_patch_manifest *manifest,
                       const uint8_t *signature, size_t signature_length,
                       const uint8_t *patch, size_t patch_length,
                       void *context) {
    (void)manifest;
    (void)context;
    return signature && signature_length == 4u &&
           signature[0] == 0x4au && signature[1] == 0x58u &&
           signature[2] == 0x50u && signature[3] == 0x31u &&
           patch && patch_length > 0u;
}

static void fill_digest(uint8_t digest[JX_PATCH_DIGEST_BYTES], uint8_t seed) {
    for (size_t i = 0; i < JX_PATCH_DIGEST_BYTES; ++i) digest[i] = (uint8_t)(seed + i);
}

static jx_patch_manifest make_manifest(const jx_patch_state *state, uint8_t protocol,
                                       uint64_t generation, uint64_t nonce,
                                       uint64_t issued, uint64_t expires,
                                       uint32_t caps, size_t patch_length) {
    jx_patch_manifest m;
    memset(&m, 0, sizeof m);
    m.version = JX_LIVE_PATCH_VERSION;
    m.protocol = protocol;
    m.generation = generation;
    m.base_generation = state->generation;
    m.issued_at = issued;
    m.expires_at = expires;
    m.nonce = nonce;
    m.capability_mask = caps;
    m.patch_length = (uint32_t)patch_length;
    memcpy(m.base_digest, state->digest, JX_PATCH_DIGEST_BYTES);
    fill_digest(m.target_digest, (uint8_t)(0x80u + generation));
    return m;
}

int main(void) {
    jx_patch_state state;
    memset(&state, 0, sizeof state);
    state.generation = 7u;
    state.highest_nonce = 100u;
    state.allowed_capabilities = JX_PATCH_CAP_HOT_TABLES | JX_PATCH_CAP_REACTIONS |
                                 JX_PATCH_CAP_ASSETS | JX_PATCH_CAP_CONFIG;
    fill_digest(state.digest, 0x10u);

    const uint8_t patch[] = { 0x01u,0x02u,0x03u,0x04u,0x05u };
    const uint8_t signature[] = { 0x4au,0x58u,0x50u,0x31u };
    const uint64_t now = 10000u;

    jx_patch_manifest https = make_manifest(&state, JX_PATCH_PROTOCOL_HTTPS, 8u, 101u,
        now - 10u, now + 300u, JX_PATCH_CAP_HOT_TABLES | JX_PATCH_CAP_REACTIONS, sizeof patch);
    if (jx_patch_validate(&state, &https, signature, sizeof signature, patch, sizeof patch,
                          now, fake_verify, NULL) != JX_PATCH_OK) return 2;

    jx_patch_manifest ssh = make_manifest(&state, JX_PATCH_PROTOCOL_SSH, 8u, 101u,
        now - 10u, now + 300u, JX_PATCH_CAP_CONFIG, sizeof patch);
    if (jx_patch_validate(&state, &ssh, signature, sizeof signature, patch, sizeof patch,
                          now, fake_verify, NULL) != JX_PATCH_OK) return 3;

    if (jx_patch_validate(&state, &https, signature, sizeof signature, patch, sizeof patch,
                          now, NULL, NULL) != JX_PATCH_ERR_SIGNATURE_REQUIRED) return 4;
    const uint8_t bad_signature[] = { 1u,2u,3u,4u };
    if (jx_patch_validate(&state, &https, bad_signature, sizeof bad_signature, patch, sizeof patch,
                          now, fake_verify, NULL) != JX_PATCH_ERR_SIGNATURE) return 5;

    jx_patch_manifest wrong_base = https;
    wrong_base.base_generation = 6u;
    if (jx_patch_validate(&state, &wrong_base, signature, sizeof signature, patch, sizeof patch,
                          now, fake_verify, NULL) != JX_PATCH_ERR_BASE_GENERATION) return 6;
    wrong_base = https;
    wrong_base.base_digest[0] ^= 0xffu;
    if (jx_patch_validate(&state, &wrong_base, signature, sizeof signature, patch, sizeof patch,
                          now, fake_verify, NULL) != JX_PATCH_ERR_BASE_DIGEST) return 7;

    jx_patch_manifest replay = https;
    replay.nonce = state.highest_nonce;
    if (jx_patch_validate(&state, &replay, signature, sizeof signature, patch, sizeof patch,
                          now, fake_verify, NULL) != JX_PATCH_ERR_REPLAY) return 8;

    jx_patch_manifest expired = https;
    expired.issued_at = now - 500u;
    expired.expires_at = now - 1u;
    if (jx_patch_validate(&state, &expired, signature, sizeof signature, patch, sizeof patch,
                          now, fake_verify, NULL) != JX_PATCH_ERR_TIME) return 9;

    jx_patch_manifest privileged = https;
    privileged.capability_mask = JX_PATCH_CAP_NATIVE_CODE;
    if (jx_patch_validate(&state, &privileged, signature, sizeof signature, patch, sizeof patch,
                          now, fake_verify, NULL) != JX_PATCH_ERR_CAPABILITY) return 10;

    if (jx_patch_commit(&state, &https) != JX_PATCH_OK) return 11;
    if (state.generation != 8u || state.highest_nonce != 101u ||
        !jx_patch_digest_equal(state.digest, https.target_digest)) return 12;

    if (jx_patch_validate(&state, &https, signature, sizeof signature, patch, sizeof patch,
                          now, fake_verify, NULL) != JX_PATCH_ERR_BASE_GENERATION) return 13;

    puts("jx-live-patch: ok https ssh signed base-hash replay capability generation");
    return 0;
}
