#ifndef JX_REMOTE_BAG_H
#define JX_REMOTE_BAG_H

#include <stddef.h>
#include <stdint.h>
#include "jx-bag-listener.h"

/*
 * Internal maintainer-plane facility.
 *
 * This header is for host/core integration only. It is not part of the JX
 * program API, API dispatch table, or ordinary capability surface. A normal
 * JX program must never be given a route to these functions.
 */
#define JX_REMOTE_BAG_VERSION 2u
#define JX_REMOTE_BAG_SOURCE_MAX 63u
#define JX_REMOTE_BAG_INSTALLATION_MAX 63u
#define JX_REMOTE_BAG_TRUST_DIGEST_BYTES 32u

#define JX_REMOTE_BAG_CAP_WRITE  (1u << 0)
#define JX_REMOTE_BAG_CAP_SCHEMA (1u << 1)

typedef enum {
    /* Reserved for non-mutating status/read surfaces. Never valid for Bag writes. */
    JX_REMOTE_BAG_TRANSPORT_HTTPS = 1,
    /* Required transport for all maintainer-plane Bag mutation. */
    JX_REMOTE_BAG_TRANSPORT_SSH = 2
} jx_remote_bag_transport;

/* Provisioned by the installer/owner. Disabled unless explicitly installed. */
typedef struct {
    uint8_t version;
    uint8_t provisioned;
    char installation_id[JX_REMOTE_BAG_INSTALLATION_MAX + 1u];
    uint8_t maintainer_trust_digest[JX_REMOTE_BAG_TRUST_DIGEST_BYTES];
} jx_maintainer_plane;

/* Resolved by the restricted SSH maintainer receiver, never by application code. */
typedef struct {
    uint8_t version;
    uint8_t transport;
    uint16_t capability_mask;
    char source_id[JX_REMOTE_BAG_SOURCE_MAX + 1u];
    char installation_id[JX_REMOTE_BAG_INSTALLATION_MAX + 1u];
    uint8_t maintainer_trust_digest[JX_REMOTE_BAG_TRUST_DIGEST_BYTES];
    char bag_name[JX_BAG_PATCH_NAME_MAX + 1u];
    uint64_t last_sequence;
    uint8_t enabled;
    uint8_t maintainer;
} jx_remote_bag_source;

typedef struct {
    uint8_t version;
    uint8_t transport;
    uint16_t requested_capabilities;
    char source_id[JX_REMOTE_BAG_SOURCE_MAX + 1u];
    char installation_id[JX_REMOTE_BAG_INSTALLATION_MAX + 1u];
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
    JX_REMOTE_BAG_ERR_LISTENER = -10,
    JX_REMOTE_BAG_ERR_NOT_PROVISIONED = -11,
    JX_REMOTE_BAG_ERR_INSTALLATION = -12,
    JX_REMOTE_BAG_ERR_TRUST = -13,
    JX_REMOTE_BAG_ERR_NOT_MAINTAINER = -14
} jx_remote_bag_result;

/**
 * Validate installer/maintainer provenance and SSH authority for a Bag mutation.
 * This is an internal core function, not an application-accessible API.
 */
int jx_remote_bag_authorize(const jx_maintainer_plane *plane,
                            const jx_remote_bag_source *source,
                            const jx_remote_bag_request *request,
                            uint64_t now);

/**
 * Validate an SSH-delivered canonical JSON Bag update and emit PREPARE listeners.
 * Caller owns canonicalization/storage and later calls commit/rollback.
 */
int jx_remote_bag_prepare(const jx_maintainer_plane *plane,
                          const jx_remote_bag_source *source,
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
