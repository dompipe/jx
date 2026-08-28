#include "jx-maintainer-runtime.h"
#include <stdlib.h>
#include <string.h>

typedef struct {
    uint8_t *json;
    size_t length;
} jx_runtime_candidate;

static int runtime_resolve(const char *bag_name,
                           jx_bag_patch_current *out,
                           void *context) {
    jx_maintainer_runtime *runtime = (jx_maintainer_runtime *)context;
    return runtime ? jx_live_bag_current(&runtime->bags, bag_name, out) : -1;
}

static int runtime_build(const jx_bag_patch_current *current,
                         const jx_bag_patch_qualifier *target,
                         const uint8_t *json,
                         size_t json_length,
                         void **candidate,
                         uint8_t actual_json_digest[JX_BAG_PATCH_DIGEST_BYTES],
                         void *context) {
    (void)current;
    (void)target;
    jx_maintainer_runtime *runtime = (jx_maintainer_runtime *)context;
    if (!runtime || !runtime->digest || !json || json_length == 0u || !candidate || !actual_json_digest)
        return -1;

    jx_runtime_candidate *next = (jx_runtime_candidate *)calloc(1u, sizeof *next);
    if (!next) return -1;
    next->json = (uint8_t *)malloc(json_length);
    if (!next->json) {
        free(next);
        return -1;
    }
    memcpy(next->json, json, json_length);
    next->length = json_length;

    if (runtime->digest(json, json_length, actual_json_digest, runtime->digest_context) != 0) {
        free(next->json);
        free(next);
        return -1;
    }
    *candidate = next;
    return 0;
}

static int runtime_commit(void *candidate,
                          const jx_bag_patch_qualifier *target,
                          void *context) {
    jx_maintainer_runtime *runtime = (jx_maintainer_runtime *)context;
    jx_runtime_candidate *next = (jx_runtime_candidate *)candidate;
    if (!runtime || !next) return -1;
    return jx_live_bag_replace(&runtime->bags, target, next->json, next->length);
}

static void runtime_discard(void *candidate, void *context) {
    (void)context;
    jx_runtime_candidate *next = (jx_runtime_candidate *)candidate;
    if (!next) return;
    free(next->json);
    free(next);
}

int jx_maintainer_runtime_init(jx_maintainer_runtime *runtime,
                               const char *installation_id,
                               const uint8_t maintainer_trust_digest[JX_REMOTE_BAG_TRUST_DIGEST_BYTES],
                               jx_maintainer_digest_fn digest,
                               void *digest_context) {
    if (!runtime || !installation_id || !*installation_id || !maintainer_trust_digest || !digest)
        return -1;
    size_t n = strlen(installation_id);
    if (n > JX_REMOTE_BAG_INSTALLATION_MAX) return -1;

    memset(runtime, 0, sizeof *runtime);
    runtime->version = JX_MAINTAINER_RUNTIME_VERSION;
    runtime->digest = digest;
    runtime->digest_context = digest_context;
    jx_live_bag_registry_init(&runtime->bags);
    jx_bag_listener_registry_init(&runtime->listeners);

    runtime->service.version = JX_MAINTAINER_SERVICE_VERSION;
    runtime->service.plane.version = JX_REMOTE_BAG_VERSION;
    runtime->service.plane.provisioned = 1u;
    memcpy(runtime->service.plane.installation_id, installation_id, n + 1u);
    memcpy(runtime->service.plane.maintainer_trust_digest,
           maintainer_trust_digest, JX_REMOTE_BAG_TRUST_DIGEST_BYTES);
    runtime->service.sources = runtime->sources;
    runtime->service.source_count = 0u;
    runtime->service.listeners = &runtime->listeners;
    runtime->service.resolve = runtime_resolve;
    runtime->service.build = runtime_build;
    runtime->service.commit = runtime_commit;
    runtime->service.discard = runtime_discard;
    runtime->service.context = runtime;
    return 0;
}

void jx_maintainer_runtime_dispose(jx_maintainer_runtime *runtime) {
    if (!runtime) return;
    jx_live_bag_registry_dispose(&runtime->bags);
    memset(runtime, 0, sizeof *runtime);
}

int jx_maintainer_runtime_add_source(jx_maintainer_runtime *runtime,
                                     const jx_remote_bag_source *source) {
    if (!runtime || runtime->version != JX_MAINTAINER_RUNTIME_VERSION || !source ||
        source->version != JX_REMOTE_BAG_VERSION || runtime->source_count >= JX_MAINTAINER_RUNTIME_SOURCE_MAX)
        return -1;
    if (strcmp(source->installation_id, runtime->service.plane.installation_id) != 0 ||
        memcmp(source->maintainer_trust_digest,
               runtime->service.plane.maintainer_trust_digest,
               JX_REMOTE_BAG_TRUST_DIGEST_BYTES) != 0)
        return -2;
    runtime->sources[runtime->source_count++] = *source;
    runtime->service.source_count = runtime->source_count;
    return 0;
}

int jx_maintainer_runtime_add_bag(jx_maintainer_runtime *runtime,
                                  const char *name,
                                  uint8_t discipline,
                                  uint64_t revision,
                                  const uint8_t schema_digest[JX_BAG_PATCH_DIGEST_BYTES],
                                  const uint8_t content_digest[JX_BAG_PATCH_DIGEST_BYTES],
                                  const uint8_t *json,
                                  size_t json_length) {
    if (!runtime || runtime->version != JX_MAINTAINER_RUNTIME_VERSION) return -1;
    return jx_live_bag_add(&runtime->bags, name, discipline, revision,
                           schema_digest, content_digest, json, json_length);
}

int jx_maintainer_runtime_add_listener(jx_maintainer_runtime *runtime,
                                       const char *bag_name,
                                       uint16_t change_mask,
                                       jx_bag_listener_fn callback,
                                       void *context) {
    if (!runtime || runtime->version != JX_MAINTAINER_RUNTIME_VERSION) return -1;
    return jx_bag_listener_add(&runtime->listeners, bag_name, change_mask, callback, context);
}
