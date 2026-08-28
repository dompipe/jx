#include "jx-maintainer-service.h"
#include <string.h>

static jx_remote_bag_source *find_source(jx_maintainer_service *service,
                                         const jx_remote_bag_request *request) {
    if (!service || !request || !service->sources) return NULL;
    for (size_t i = 0; i < service->source_count; ++i) {
        jx_remote_bag_source *s = &service->sources[i];
        if (s->enabled && strcmp(s->source_id, request->source_id) == 0 &&
            strcmp(s->installation_id, request->installation_id) == 0 &&
            strcmp(s->bag_name, request->qualifier.bag_name) == 0) return s;
    }
    return NULL;
}

static void emit_phase(const jx_maintainer_service *service,
                       uint8_t phase,
                       const jx_bag_patch_current *before,
                       const jx_bag_patch_qualifier *target,
                       const uint8_t *json,
                       size_t json_length,
                       void *candidate) {
    if (!service || !service->listeners) return;
    jx_bag_listener_event event;
    memset(&event, 0, sizeof event);
    event.version = JX_BAG_LISTENER_VERSION;
    event.phase = phase;
    event.change_mask = jx_bag_listener_detect_changes(before, target);
    event.before = before;
    event.target = target;
    event.json = json;
    event.json_length = json_length;
    event.candidate = candidate;
    (void)jx_bag_listener_emit(service->listeners, &event);
}

int jx_maintainer_service_apply(jx_maintainer_service *service,
                                const jx_remote_bag_request *request,
                                uint64_t now,
                                const uint8_t *json,
                                size_t json_length) {
    if (!service || service->version != JX_MAINTAINER_SERVICE_VERSION || !request ||
        !service->resolve || !service->build || !service->commit || !service->discard ||
        !json || json_length == 0u) return JX_MAINTAINER_SERVICE_ERR_ARGUMENT;

    jx_remote_bag_source *source = find_source(service, request);
    if (!source) return JX_MAINTAINER_SERVICE_ERR_SOURCE;

    jx_bag_patch_current current;
    memset(&current, 0, sizeof current);
    if (service->resolve(request->qualifier.bag_name, &current, service->context) != 0)
        return JX_MAINTAINER_SERVICE_ERR_RESOLVE;

    void *candidate = NULL;
    uint8_t digest[JX_BAG_PATCH_DIGEST_BYTES];
    if (service->build(&current, &request->qualifier, json, json_length,
                       &candidate, digest, service->context) != 0 || !candidate)
        return JX_MAINTAINER_SERVICE_ERR_BUILD;

    int prepared = jx_remote_bag_prepare(&service->plane, source, request, now,
                                         &current, json, json_length, digest,
                                         service->listeners, candidate);
    if (prepared != JX_REMOTE_BAG_OK) {
        emit_phase(service, JX_BAG_LISTENER_ROLLBACK, &current, &request->qualifier,
                   json, json_length, candidate);
        service->discard(candidate, service->context);
        return JX_MAINTAINER_SERVICE_ERR_PREPARE;
    }

    if (service->commit(candidate, &request->qualifier, service->context) != 0) {
        emit_phase(service, JX_BAG_LISTENER_ROLLBACK, &current, &request->qualifier,
                   json, json_length, candidate);
        service->discard(candidate, service->context);
        return JX_MAINTAINER_SERVICE_ERR_COMMIT;
    }

    emit_phase(service, JX_BAG_LISTENER_COMMIT, &current, &request->qualifier,
               json, json_length, candidate);
    jx_remote_bag_mark_committed(source, request);
    service->discard(candidate, service->context);
    return JX_MAINTAINER_SERVICE_OK;
}
