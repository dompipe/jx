#ifndef JX_REMOTE_BAG_H
#define JX_REMOTE_BAG_H

#include <stddef.h>
#include <stdint.h>
#include "jx-bag-listener.h"

#define JX_REMOTE_BAG_VERSION 1u
#define JX_REMOTE_BAG_SOURCE_MAX 63u
#define JX_REMOTE_BAG_CAP_WRITE (1u << 0)
#define JX_REMOTE_BAG_CAP_SCHEMA (1u << 1)

typedef enum {
    /* Reserved for non-mutating status/read surfaces. Never valid for Bag writes. */
    JX_REMOTE_BAG_TRANSPORT_HTTPS = 1,
    /* Required transport for all remote Bag mutation. */
    JX_REMOTE_BAG_TRANSPORT_SSH = 2
} jx_remote_bag_transport;

typedef struct {
    uint8_t version;
    uint8_t transport;
    uint16_t capability_mask;
    char source_id[JX_REMOTE_BAG_SOURCE_MAX + 1u];
    char bag_name[JX_BAG_PATCH_NAME_MAX + 1u];
    uint64_t last_sequence;
    uint8_t enabled;
} jx_remote_bag_source;

typedef struct {
    uint8_t version;
    uint8_t transport;
    uint16_t requested_capabilities;
    char source_id[JX_REMOTE_BAG_SOURCE_MAX + 1u];
    uint64_t sequence;
    uint64_t issued_at;
    uint64_t expires_at;
    jx_bag_patch_qualifier qualifier;
} jx_remote_bag_request;

typedef enum {
    JX_REMOTE_BAG_OK = 0,
    JX_REMOTE_BAG_ERR_ARGUMENT = -1,
    JX_REMOTE_BAG_ERR_VERSION = -2,
    JX_REMOTE_BAG_ERR_SOURCE = -3,
    JX_REMOTE_BAG_ERR_TRANSPORT = -4,
    JX_REMOTE_BAG_ERR_CAPABILITY = -5,
    JX_REMOTE_BAG_ERR_SCOPE = -6,
    JX_REMOTE_BAG_ERR_REPLAY = -7,
    JX_REMOTE_BAG_ERR_TIME = -8,
    JX_REMOTE_BAG_ERR_PATCH = -9,
    JX_REMOTE_BAG_ERR_LISTENER = -10
} jx_remote_bag_result;

/**
 * Validate provenance and authority for a remote Bag mutation request.
 * Mutation is SSH-only. HTTPS is intentionally rejected here even when the
 * source record is configured for HTTPS; HTTPS belongs to separate read/status APIs.
 * This does not mutate source->last_sequence; callers advance it only after
 * the Bag transaction has committed successfully.
 */
int jx_remote_bag_authorize(const jx_remote_bag_source *source,
                            const jx_remote_bag_request *request,
                            uint64_t now);

/**
 * Validate an SSH-delivered canonical JSON Bag update and emit PREPARE listeners.
 * The caller owns canonicalization/storage and later calls commit/rollback.
 */
int jx_remote_bag_prepare(const jx_remote_bag_source *source,
                          const jx_remote_bag_request *request,
                          uint64_t now,
                          const jx_bag_patch_current *current,
                          const uint8_t *json,
                          size_t json_length,
                          const uint8_t actual_json_digest[JX_BAG_PATCH_DIGEST_BYTES],
                          const jx_bag_listener_registry *listeners,
                          void *candidate);

/** Advance anti-replay state only after successful canonical Bag commit. */
void jx_remote_bag_mark_committed(jx_remote_bag_source *source,
                                  const jx_remote_bag_request *request);

#endif
