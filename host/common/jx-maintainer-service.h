#ifndef JX_MAINTAINER_SERVICE_H
#define JX_MAINTAINER_SERVICE_H

#include <stddef.h>
#include <stdint.h>
#include "jx-remote-bag.h"

#define JX_MAINTAINER_SERVICE_VERSION 1u

typedef int (*jx_maintainer_resolve_fn)(const char *bag_name,
                                        jx_bag_patch_current *out,
                                        void *context);
typedef int (*jx_maintainer_candidate_build_fn)(const jx_bag_patch_current *current,
                                                const jx_bag_patch_qualifier *target,
                                                const uint8_t *json,
                                                size_t json_length,
                                                void **candidate,
                                                uint8_t actual_json_digest[JX_BAG_PATCH_DIGEST_BYTES],
                                                void *context);
typedef int (*jx_maintainer_candidate_commit_fn)(void *candidate,
                                                 const jx_bag_patch_qualifier *target,
                                                 void *context);
typedef void (*jx_maintainer_candidate_discard_fn)(void *candidate,
                                                    void *context);

typedef struct {
    uint8_t version;
    jx_maintainer_plane plane;
    jx_remote_bag_source *sources;
    size_t source_count;
    const jx_bag_listener_registry *listeners;
    jx_maintainer_resolve_fn resolve;
    jx_maintainer_candidate_build_fn build;
    jx_maintainer_candidate_commit_fn commit;
    jx_maintainer_candidate_discard_fn discard;
    void *context;
} jx_maintainer_service;

typedef enum {
    JX_MAINTAINER_SERVICE_OK = 0,
    JX_MAINTAINER_SERVICE_ERR_ARGUMENT = -1,
    JX_MAINTAINER_SERVICE_ERR_SOURCE = -2,
    JX_MAINTAINER_SERVICE_ERR_RESOLVE = -3,
    JX_MAINTAINER_SERVICE_ERR_BUILD = -4,
    JX_MAINTAINER_SERVICE_ERR_PREPARE = -5,
    JX_MAINTAINER_SERVICE_ERR_COMMIT = -6
} jx_maintainer_service_result;

int jx_maintainer_service_apply(jx_maintainer_service *service,
                                const jx_remote_bag_request *request,
                                uint64_t now,
                                const uint8_t *json,
                                size_t json_length);

#endif
