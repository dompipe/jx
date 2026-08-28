#include "jx-remote-bag.h"
#include <string.h>

static int text_equal(const char *a, const char *b, size_t max) {
    if (!a || !b) return 0;
    return strncmp(a, b, max) == 0;
}

static int digest_nonzero(const uint8_t *digest, size_t length) {
    uint8_t any = 0u;
    if (!digest) return 0;
    for (size_t i = 0; i < length; ++i) any |= digest[i];
    return any != 0u;
}

int jx_remote_bag_authorize(const jx_maintainer_plane *plane,
                            const jx_remote_bag_source *source,
                            const jx_remote_bag_request *request,
                            uint64_t now) {
    if (!plane || !source || !request) return JX_REMOTE_BAG_ERR_ARGUMENT;
    if (plane->version != JX_REMOTE_BAG_VERSION ||
        source->version != JX_REMOTE_BAG_VERSION ||
        request->version != JX_REMOTE_BAG_VERSION)
        return JX_REMOTE_BAG_ERR_VERSION;

    if (!plane->provisioned || !plane->installation_id[0] ||
        !digest_nonzero(plane->maintainer_trust_digest, JX_REMOTE_BAG_TRUST_DIGEST_BYTES))
        return JX_REMOTE_BAG_ERR_NOT_PROVISIONED;

    if (!source->enabled || !source->maintainer) return JX_REMOTE_BAG_ERR_NOT_MAINTAINER;
    if (!source->source_id[0] ||
        !text_equal(source->source_id, request->source_id, JX_REMOTE_BAG_SOURCE_MAX + 1u))
        return JX_REMOTE_BAG_ERR_SOURCE;

    if (!text_equal(plane->installation_id, source->installation_id,
                    JX_REMOTE_BAG_INSTALLATION_MAX + 1u) ||
        !text_equal(plane->installation_id, request->installation_id,
                    JX_REMOTE_BAG_INSTALLATION_MAX + 1u))
        return JX_REMOTE_BAG_ERR_INSTALLATION;

    if (!jx_bag_patch_digest_equal(plane->maintainer_trust_digest,
                                   source->maintainer_trust_digest))
        return JX_REMOTE_BAG_ERR_TRUST;

    /* Maintainer mutation is deliberately SSH-only. HTTPS may expose separate
     * non-mutating inspection surfaces, but it is never mutation authority. */
    if (source->transport != JX_REMOTE_BAG_TRANSPORT_SSH ||
        request->transport != JX_REMOTE_BAG_TRANSPORT_SSH ||
        request->transport != source->transport)
        return JX_REMOTE_BAG_ERR_TRANSPORT;

    if (!(source->capability_mask & JX_REMOTE_BAG_CAP_WRITE) ||
        !(request->requested_capabilities & JX_REMOTE_BAG_CAP_WRITE) ||
        (request->requested_capabilities & ~source->capability_mask))
        return JX_REMOTE_BAG_ERR_CAPABILITY;
    if (!source->bag_name[0] || !request->qualifier.bag_name[0] ||
        !text_equal(source->bag_name, request->qualifier.bag_name, JX_BAG_PATCH_NAME_MAX + 1u))
        return JX_REMOTE_BAG_ERR_SCOPE;
    if ((request->requested_capabilities & JX_REMOTE_BAG_CAP_SCHEMA) == 0u &&
        !jx_bag_patch_digest_equal(request->qualifier.current_schema_digest,
                                   request->qualifier.target_schema_digest))
        return JX_REMOTE_BAG_ERR_CAPABILITY;
    if (request->sequence <= source->last_sequence) return JX_REMOTE_BAG_ERR_REPLAY;
    if (request->issued_at > now || request->expires_at < now || request->expires_at < request->issued_at)
        return JX_REMOTE_BAG_ERR_TIME;
    return JX_REMOTE_BAG_OK;
}

int jx_remote_bag_prepare(const jx_maintainer_plane *plane,
                          const jx_remote_bag_source *source,
                          const jx_remote_bag_request *request,
                          uint64_t now,
                          const jx_bag_patch_current *current,
                          const uint8_t *json,
                          size_t json_length,
                          const uint8_t actual_json_digest[JX_BAG_PATCH_DIGEST_BYTES],
                          const jx_bag_listener_registry *listeners,
                          void *candidate) {
    int rc = jx_remote_bag_authorize(plane, source, request, now);
    if (rc != JX_REMOTE_BAG_OK) return rc;
    if (jx_bag_patch_validate(current, &request->qualifier, json, json_length, actual_json_digest) != JX_BAG_PATCH_OK)
        return JX_REMOTE_BAG_ERR_PATCH;
    if (listeners) {
        jx_bag_listener_event event;
        memset(&event, 0, sizeof event);
        event.version = JX_BAG_LISTENER_VERSION;
        event.phase = JX_BAG_LISTENER_PREPARE;
        event.change_mask = jx_bag_listener_detect_changes(current, &request->qualifier);
        event.before = current;
        event.target = &request->qualifier;
        event.json = json;
        event.json_length = json_length;
        event.candidate = candidate;
        if (jx_bag_listener_emit(listeners, &event) < 0) return JX_REMOTE_BAG_ERR_LISTENER;
    }
    return JX_REMOTE_BAG_OK;
}

void jx_remote_bag_mark_committed(jx_remote_bag_source *source,
                                  const jx_remote_bag_request *request) {
    if (!source || !request) return;
    if (request->sequence > source->last_sequence) source->last_sequence = request->sequence;
}
