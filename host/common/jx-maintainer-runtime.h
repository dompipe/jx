#ifndef JX_MAINTAINER_RUNTIME_H
#define JX_MAINTAINER_RUNTIME_H

#include <stddef.h>
#include <stdint.h>
#include "jx-live-bag.h"
#include "jx-maintainer-service.h"
#include "jx-bag-listener.h"

#define JX_MAINTAINER_RUNTIME_VERSION 1u
#define JX_MAINTAINER_RUNTIME_SOURCE_MAX 64u

typedef int (*jx_maintainer_digest_fn)(const uint8_t *bytes,
                                       size_t length,
                                       uint8_t out[JX_BAG_PATCH_DIGEST_BYTES],
                                       void *context);

typedef struct {
    uint8_t version;
    jx_live_bag_registry bags;
    jx_bag_listener_registry listeners;
    jx_remote_bag_source sources[JX_MAINTAINER_RUNTIME_SOURCE_MAX];
    size_t source_count;
    jx_maintainer_service service;
    jx_maintainer_digest_fn digest;
    void *digest_context;
} jx_maintainer_runtime;

int jx_maintainer_runtime_init(jx_maintainer_runtime *runtime,
                               const char *installation_id,
                               const uint8_t maintainer_trust_digest[JX_REMOTE_BAG_TRUST_DIGEST_BYTES],
                               jx_maintainer_digest_fn digest,
                               void *digest_context);
void jx_maintainer_runtime_dispose(jx_maintainer_runtime *runtime);

int jx_maintainer_runtime_add_source(jx_maintainer_runtime *runtime,
                                     const jx_remote_bag_source *source);
int jx_maintainer_runtime_add_bag(jx_maintainer_runtime *runtime,
                                  const char *name,
                                  uint8_t discipline,
                                  uint64_t revision,
                                  const uint8_t schema_digest[JX_BAG_PATCH_DIGEST_BYTES],
                                  const uint8_t content_digest[JX_BAG_PATCH_DIGEST_BYTES],
                                  const uint8_t *json,
                                  size_t json_length);
int jx_maintainer_runtime_add_listener(jx_maintainer_runtime *runtime,
                                       const char *bag_name,
                                       uint16_t change_mask,
                                       jx_bag_listener_fn callback,
                                       void *context);

#endif
