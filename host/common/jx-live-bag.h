#ifndef JX_LIVE_BAG_H
#define JX_LIVE_BAG_H

#include <stddef.h>
#include <stdint.h>
#include "jx-bag-patch.h"

#define JX_LIVE_BAG_VERSION 1u
#define JX_LIVE_BAG_MAX 64u

typedef struct {
    uint8_t version;
    uint8_t discipline;
    uint8_t in_use;
    uint8_t reserved;
    char name[JX_BAG_PATCH_NAME_MAX + 1u];
    uint64_t revision;
    uint8_t schema_digest[JX_BAG_PATCH_DIGEST_BYTES];
    uint8_t content_digest[JX_BAG_PATCH_DIGEST_BYTES];
    uint8_t *json;
    size_t json_length;
} jx_live_bag;

typedef struct {
    jx_live_bag bags[JX_LIVE_BAG_MAX];
    size_t count;
} jx_live_bag_registry;

void jx_live_bag_registry_init(jx_live_bag_registry *registry);
void jx_live_bag_registry_dispose(jx_live_bag_registry *registry);
int jx_live_bag_add(jx_live_bag_registry *registry,
                    const char *name,
                    uint8_t discipline,
                    uint64_t revision,
                    const uint8_t schema_digest[JX_BAG_PATCH_DIGEST_BYTES],
                    const uint8_t content_digest[JX_BAG_PATCH_DIGEST_BYTES],
                    const uint8_t *json,
                    size_t json_length);
jx_live_bag *jx_live_bag_find(jx_live_bag_registry *registry,const char *name);
int jx_live_bag_current(jx_live_bag_registry *registry,const char *name,jx_bag_patch_current *out);
int jx_live_bag_replace(jx_live_bag_registry *registry,
                        const jx_bag_patch_qualifier *target,
                        const uint8_t *json,
                        size_t json_length);

#endif
