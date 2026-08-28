#include "jx-security.h"
#include "jx-security-hash.h"
#include <string.h>

static int pattern_matches(const uint8_t *data,
                           const jx_security_signature *sig) {
    size_t i;
    for (i = 0; i < sig->length; ++i) {
        uint8_t mask = sig->type == JX_SECURITY_SIG_BYTES_MASKED ? sig->mask[i] : 0xffu;
        if ((data[i] & mask) != (sig->bytes[i] & mask)) return 0;
    }
    return 1;
}

size_t jx_security_hash_digest_length(jx_security_hash_algorithm algorithm) {
    switch (algorithm) {
        case JX_SECURITY_HASH_MD5: return JX_SECURITY_MD5_BYTES;
        case JX_SECURITY_HASH_SHA1: return JX_SECURITY_SHA1_BYTES;
        case JX_SECURITY_HASH_SHA256: return JX_SECURITY_SHA256_BYTES;
        default: return 0u;
    }
}

static int scan_byte_signature(const uint8_t *data,
                               size_t length,
                               const jx_security_signature *sig,
                               uint64_t *match_offset) {
    size_t offset;
    if (!data || !sig || !match_offset) return -1;
    if (sig->length == 0u || sig->length > JX_SECURITY_PATTERN_MAX) return -2;
    if (sig->type != JX_SECURITY_SIG_BYTES &&
        sig->type != JX_SECURITY_SIG_BYTES_MASKED) return -3;
    if (sig->verdict > JX_SECURITY_ERROR) return -4;

    if (sig->offset != JX_SECURITY_OFFSET_ANY) {
        if (sig->offset > (uint64_t)SIZE_MAX) return 0;
        offset = (size_t)sig->offset;
        if (offset > length || sig->length > length - offset) return 0;
        if (pattern_matches(data + offset, sig)) {
            *match_offset = sig->offset;
            return 1;
        }
        return 0;
    }

    if (sig->length > length) return 0;
    for (offset = 0u; offset <= length - sig->length; ++offset) {
        if (pattern_matches(data + offset, sig)) {
            *match_offset = (uint64_t)offset;
            return 1;
        }
    }
    return 0;
}

typedef struct {
    uint8_t ready_md5;
    uint8_t ready_sha1;
    uint8_t ready_sha256;
    uint8_t md5[JX_SECURITY_MD5_BYTES];
    uint8_t sha1[JX_SECURITY_SHA1_BYTES];
    uint8_t sha256[JX_SECURITY_SHA256_BYTES];
} hash_cache;

static int cached_digest(hash_cache *cache,
                         jx_security_hash_algorithm algorithm,
                         const uint8_t *data,
                         size_t length,
                         const uint8_t **digest) {
    if (!cache || !digest || (!data && length != 0u)) return -1;
    switch (algorithm) {
        case JX_SECURITY_HASH_MD5:
            if (!cache->ready_md5) {
                if (jx_security_md5(data, length, cache->md5) != 0) return -2;
                cache->ready_md5 = 1u;
            }
            *digest = cache->md5;
            return 0;
        case JX_SECURITY_HASH_SHA1:
            if (!cache->ready_sha1) {
                if (jx_security_sha1(data, length, cache->sha1) != 0) return -2;
                cache->ready_sha1 = 1u;
            }
            *digest = cache->sha1;
            return 0;
        case JX_SECURITY_HASH_SHA256:
            if (!cache->ready_sha256) {
                if (jx_security_sha256(data, length, cache->sha256) != 0) return -2;
                cache->ready_sha256 = 1u;
            }
            *digest = cache->sha256;
            return 0;
        default:
            return -3;
    }
}

static int scan_hash_signature(const uint8_t *data,
                               size_t length,
                               const jx_security_signature *sig,
                               hash_cache *cache,
                               uint64_t *match_offset) {
    const uint8_t *digest = NULL;
    size_t digest_length;
    int rc;
    if (!sig || !cache || !match_offset) return -1;
    if (sig->type != JX_SECURITY_SIG_HASH || sig->verdict > JX_SECURITY_ERROR) return -2;
    digest_length = jx_security_hash_digest_length((jx_security_hash_algorithm)sig->hash_algorithm);
    if (digest_length == 0u) return -3;
    if (sig->file_size != JX_SECURITY_SIZE_ANY && sig->file_size != (uint64_t)length) return 0;
    rc = cached_digest(cache, (jx_security_hash_algorithm)sig->hash_algorithm, data, length, &digest);
    if (rc != 0) return rc;
    if (memcmp(digest, sig->digest, digest_length) != 0) return 0;
    *match_offset = 0u;
    return 1;
}

int jx_security_scan_buffer(const uint8_t *data,
                            size_t length,
                            const jx_security_signature *signatures,
                            size_t signature_count,
                            jx_security_result *result) {
    size_t i;
    int found = 0;
    uint8_t best_verdict = JX_SECURITY_CLEAN;
    uint64_t best_offset = 0u;
    const jx_security_signature *best = NULL;
    hash_cache cache;

    if ((!data && length != 0u) || (!signatures && signature_count != 0u) || !result)
        return -1;

    memset(result, 0, sizeof *result);
    memset(&cache, 0, sizeof cache);
    result->version = JX_SECURITY_VERSION;
    result->verdict = JX_SECURITY_CLEAN;
    result->bytes_scanned = (uint64_t)length;

    for (i = 0u; i < signature_count; ++i) {
        uint64_t offset = 0u;
        int rc;
        if (signatures[i].type == JX_SECURITY_SIG_HASH) {
            rc = scan_hash_signature(data, length, &signatures[i], &cache, &offset);
        } else {
            rc = scan_byte_signature(data, length, &signatures[i], &offset);
        }
        if (rc < 0) {
            result->verdict = JX_SECURITY_ERROR;
            return rc;
        }
        if (rc == 1 && (!found || signatures[i].verdict > best_verdict)) {
            found = 1;
            best = &signatures[i];
            best_verdict = signatures[i].verdict;
            best_offset = offset;
            if (best_verdict == JX_SECURITY_ERROR) break;
        }
    }

    if (!found) return 0;

    result->verdict = best_verdict;
    result->signature_id = best->id;
    result->match_offset = best_offset;
    memcpy(result->signature_name, best->name, JX_SECURITY_NAME_MAX);
    result->signature_name[JX_SECURITY_NAME_MAX] = '\0';
    return 1;
}

int jx_security_code_encode(jx_security_verdict verdict,
                            uint16_t result_slot,
                            uint8_t *code_length,
                            uint16_t *code) {
    if (!code_length || !code || verdict > JX_SECURITY_ERROR) return -1;
    if (result_slot <= JX_SECURITY_RESULT_SLOT_DIRECT_MAX) {
        *code_length = 1u;
        *code = (uint16_t)(((uint16_t)verdict << 6u) | result_slot);
        return 0;
    }
    if (result_slot <= JX_SECURITY_RESULT_SLOT_EXTENDED_MAX) {
        *code_length = 2u;
        *code = (uint16_t)(((uint16_t)verdict << 14u) | result_slot);
        return 0;
    }
    return -2;
}

int jx_security_code_decode(uint8_t code_length,
                            uint16_t code,
                            jx_security_verdict *verdict,
                            uint16_t *result_slot) {
    if (!verdict || !result_slot) return -1;
    if (code_length == 1u) {
        if (code > 0xffu) return -2;
        *verdict = (jx_security_verdict)((code >> 6u) & 0x03u);
        *result_slot = (uint16_t)(code & 0x3fu);
        return 0;
    }
    if (code_length == 2u) {
        *verdict = (jx_security_verdict)((code >> 14u) & 0x03u);
        *result_slot = (uint16_t)(code & 0x3fffu);
        return 0;
    }
    return -3;
}
