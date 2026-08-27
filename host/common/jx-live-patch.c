#include "jx-live-patch.h"
#include <string.h>

int jx_patch_digest_equal(const uint8_t a[JX_PATCH_DIGEST_BYTES],
                          const uint8_t b[JX_PATCH_DIGEST_BYTES]) {
    if (!a || !b) return 0;
    uint8_t diff = 0u;
    for (size_t i = 0; i < JX_PATCH_DIGEST_BYTES; ++i) diff |= (uint8_t)(a[i] ^ b[i]);
    return diff == 0u;
}

int jx_patch_digest_nonzero(const uint8_t digest[JX_PATCH_DIGEST_BYTES]) {
    if (!digest) return 0;
    uint8_t any = 0u;
    for (size_t i = 0; i < JX_PATCH_DIGEST_BYTES; ++i) any |= digest[i];
    return any != 0u;
}

int jx_patch_protocol_valid(uint8_t protocol) {
    return protocol == (uint8_t)JX_PATCH_PROTOCOL_HTTPS ||
           protocol == (uint8_t)JX_PATCH_PROTOCOL_SSH ||
           protocol == (uint8_t)JX_PATCH_PROTOCOL_LOCAL_SIGNED;
}

int jx_patch_validate(
    const jx_patch_state *state,
    const jx_patch_manifest *manifest,
    const uint8_t *signature,
    size_t signature_length,
    const uint8_t *patch,
    size_t patch_length,
    uint64_t now,
    jx_patch_verify_fn verify,
    void *verify_context
) {
    if (!state || !manifest || !patch) return JX_PATCH_ERR_ARGUMENT;
    if (manifest->version != JX_LIVE_PATCH_VERSION) return JX_PATCH_ERR_VERSION;
    if (!jx_patch_protocol_valid(manifest->protocol)) return JX_PATCH_ERR_PROTOCOL;
    if (!verify || !signature || signature_length == 0u) return JX_PATCH_ERR_SIGNATURE_REQUIRED;
    if (manifest->base_generation != state->generation) return JX_PATCH_ERR_BASE_GENERATION;
    if (manifest->generation <= state->generation) return JX_PATCH_ERR_GENERATION;
    if (!jx_patch_digest_equal(manifest->base_digest, state->digest)) return JX_PATCH_ERR_BASE_DIGEST;
    if (manifest->nonce <= state->highest_nonce) return JX_PATCH_ERR_REPLAY;
    if (manifest->issued_at > manifest->expires_at) return JX_PATCH_ERR_TIME;
    if ((manifest->expires_at - manifest->issued_at) > JX_PATCH_MAX_VALIDITY_SECONDS) return JX_PATCH_ERR_TIME;
    if (manifest->issued_at > now && (manifest->issued_at - now) > JX_PATCH_CLOCK_SKEW_SECONDS) return JX_PATCH_ERR_TIME;
    if (now > manifest->expires_at) return JX_PATCH_ERR_TIME;
    if ((manifest->capability_mask & ~state->allowed_capabilities) != 0u) return JX_PATCH_ERR_CAPABILITY;
    if (manifest->capability_mask == 0u || (manifest->capability_mask & ~JX_PATCH_CAP_ALL) != 0u) return JX_PATCH_ERR_CAPABILITY;
    if (patch_length == 0u || patch_length > JX_PATCH_MAX_BYTES ||
        manifest->patch_length != (uint32_t)patch_length) return JX_PATCH_ERR_LENGTH;
    if (!jx_patch_digest_nonzero(manifest->target_digest) ||
        jx_patch_digest_equal(manifest->target_digest, state->digest)) return JX_PATCH_ERR_TARGET_DIGEST;
    if (!verify(manifest, signature, signature_length, patch, patch_length, verify_context)) return JX_PATCH_ERR_SIGNATURE;
    return JX_PATCH_OK;
}

int jx_patch_commit(jx_patch_state *state, const jx_patch_manifest *manifest) {
    if (!state || !manifest) return JX_PATCH_ERR_ARGUMENT;
    if (manifest->version != JX_LIVE_PATCH_VERSION ||
        manifest->base_generation != state->generation ||
        manifest->generation <= state->generation ||
        !jx_patch_digest_equal(manifest->base_digest, state->digest) ||
        manifest->nonce <= state->highest_nonce ||
        !jx_patch_digest_nonzero(manifest->target_digest)) {
        return JX_PATCH_ERR_BASE_GENERATION;
    }
    state->generation = manifest->generation;
    state->highest_nonce = manifest->nonce;
    memcpy(state->digest, manifest->target_digest, JX_PATCH_DIGEST_BYTES);
    return JX_PATCH_OK;
}
