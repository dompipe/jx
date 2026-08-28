#ifndef JX_LIVE_PATCH_H
#define JX_LIVE_PATCH_H

#include <stddef.h>
#include <stdint.h>

#define JX_LIVE_PATCH_VERSION 1u
#define JX_PATCH_DIGEST_BYTES 32u
#define JX_PATCH_SIGNATURE_MAX 8192u
#define JX_PATCH_MAX_BYTES (64u * 1024u * 1024u)
#define JX_PATCH_MAX_VALIDITY_SECONDS 3600u
#define JX_PATCH_CLOCK_SKEW_SECONDS 300u

#define JX_PATCH_CAP_HOT_TABLES  (1u << 0)
#define JX_PATCH_CAP_REACTIONS   (1u << 1)
#define JX_PATCH_CAP_ASSETS      (1u << 2)
#define JX_PATCH_CAP_CONFIG      (1u << 3)
#define JX_PATCH_CAP_NATIVE_CODE (1u << 4)
#define JX_PATCH_CAP_ALL         (JX_PATCH_CAP_HOT_TABLES | JX_PATCH_CAP_REACTIONS | \
                                  JX_PATCH_CAP_ASSETS | JX_PATCH_CAP_CONFIG | \
                                  JX_PATCH_CAP_NATIVE_CODE)

typedef enum {
    JX_PATCH_PROTOCOL_HTTPS = 1,
    JX_PATCH_PROTOCOL_SSH = 2,
    JX_PATCH_PROTOCOL_LOCAL_SIGNED = 3
} jx_patch_protocol;

typedef enum {
    JX_PATCH_OK = 0,
    JX_PATCH_ERR_ARGUMENT = -1,
    JX_PATCH_ERR_VERSION = -2,
    JX_PATCH_ERR_PROTOCOL = -3,
    JX_PATCH_ERR_SIGNATURE_REQUIRED = -4,
    JX_PATCH_ERR_SIGNATURE = -5,
    JX_PATCH_ERR_BASE_GENERATION = -6,
    JX_PATCH_ERR_GENERATION = -7,
    JX_PATCH_ERR_BASE_DIGEST = -8,
    JX_PATCH_ERR_REPLAY = -9,
    JX_PATCH_ERR_TIME = -10,
    JX_PATCH_ERR_CAPABILITY = -11,
    JX_PATCH_ERR_LENGTH = -12,
    JX_PATCH_ERR_TARGET_DIGEST = -13
} jx_patch_result;

typedef struct {
    uint8_t version;
    uint8_t protocol;
    uint16_t flags;
    uint64_t generation;
    uint64_t base_generation;
    uint64_t issued_at;
    uint64_t expires_at;
    uint64_t nonce;
    uint32_t capability_mask;
    uint32_t patch_length;
    uint8_t base_digest[JX_PATCH_DIGEST_BYTES];
    uint8_t target_digest[JX_PATCH_DIGEST_BYTES];
} jx_patch_manifest;

typedef struct {
    uint64_t generation;
    uint64_t highest_nonce;
    uint32_t allowed_capabilities;
    uint8_t digest[JX_PATCH_DIGEST_BYTES];
} jx_patch_state;

/**
 * Signature verification is supplied by the host/operator trust store.
 * It is mandatory: passing NULL causes JX_PATCH_ERR_SIGNATURE_REQUIRED.
 */
typedef int (*jx_patch_verify_fn)(
    const jx_patch_manifest *manifest,
    const uint8_t *signature,
    size_t signature_length,
    const uint8_t *patch,
    size_t patch_length,
    void *context
);

int jx_patch_digest_equal(const uint8_t a[JX_PATCH_DIGEST_BYTES],
                          const uint8_t b[JX_PATCH_DIGEST_BYTES]);
int jx_patch_digest_nonzero(const uint8_t digest[JX_PATCH_DIGEST_BYTES]);
int jx_patch_protocol_valid(uint8_t protocol);

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
);

/** Commit only after the caller has staged and independently validated the target. */
int jx_patch_commit(jx_patch_state *state, const jx_patch_manifest *manifest);

#endif
