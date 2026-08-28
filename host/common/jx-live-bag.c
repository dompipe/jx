#include "jx-live-bag.h"
#include <stdlib.h>
#include <string.h>

static int copy_name(char out[JX_BAG_PATCH_NAME_MAX + 1u], const char *name) {
    if (!out || !name || !*name) return -1;
    size_t n = strlen(name);
    if (n > JX_BAG_PATCH_NAME_MAX) return -1;
    memcpy(out, name, n + 1u);
    return 0;
}

void jx_live_bag_registry_init(jx_live_bag_registry *registry) {
    if (registry) memset(registry, 0, sizeof *registry);
}

void jx_live_bag_registry_dispose(jx_live_bag_registry *registry) {
    if (!registry) return;
    for (size_t i = 0; i < JX_LIVE_BAG_MAX; ++i) {
        free(registry->bags[i].json);
        registry->bags[i].json = NULL;
        registry->bags[i].json_length = 0u;
    }
    memset(registry, 0, sizeof *registry);
}

jx_live_bag *jx_live_bag_find(jx_live_bag_registry *registry, const char *name) {
    if (!registry || !name) return NULL;
    for (size_t i = 0; i < JX_LIVE_BAG_MAX; ++i) {
        if (registry->bags[i].in_use && strcmp(registry->bags[i].name, name) == 0)
            return &registry->bags[i];
    }
    return NULL;
}

int jx_live_bag_add(jx_live_bag_registry *registry,
                    const char *name,
                    uint8_t discipline,
                    uint64_t revision,
                    const uint8_t schema_digest[32],
                    const uint8_t content_digest[32],
                    const uint8_t *json,
                    size_t json_length) {
    if (!registry || !name || !jx_bag_patch_discipline_valid(discipline) ||
        !schema_digest || !content_digest || !json || json_length == 0u ||
        json_length > JX_BAG_PATCH_JSON_MAX) return -1;
    if (jx_live_bag_find(registry, name)) return -2;

    for (size_t i = 0; i < JX_LIVE_BAG_MAX; ++i) {
        jx_live_bag *b = &registry->bags[i];
        if (b->in_use) continue;

        uint8_t *copy = (uint8_t *)malloc(json_length);
        if (!copy) return -3;
        memcpy(copy, json, json_length);

        memset(b, 0, sizeof *b);
        b->version = JX_LIVE_BAG_VERSION;
        b->discipline = discipline;
        b->in_use = 1u;
        if (copy_name(b->name, name) != 0) {
            free(copy);
            memset(b, 0, sizeof *b);
            return -1;
        }
        b->revision = revision;
        memcpy(b->schema_digest, schema_digest, 32u);
        memcpy(b->content_digest, content_digest, 32u);
        b->json = copy;
        b->json_length = json_length;
        registry->count++;
        return 0;
    }
    return -4;
}

int jx_live_bag_current(jx_live_bag_registry *registry,
                        const char *name,
                        jx_bag_patch_current *out) {
    jx_live_bag *b = jx_live_bag_find(registry, name);
    if (!b || !out) return -1;
    memset(out, 0, sizeof *out);
    out->bag_name = b->name;
    out->discipline = b->discipline;
    out->revision = b->revision;
    memcpy(out->schema_digest, b->schema_digest, 32u);
    memcpy(out->content_digest, b->content_digest, 32u);
    return 0;
}

int jx_live_bag_replace(jx_live_bag_registry *registry,
                        const jx_bag_patch_qualifier *target,
                        const uint8_t *json,
                        size_t json_length) {
    if (!registry || !target || !json || json_length != target->json_length) return -1;
    jx_live_bag *b = jx_live_bag_find(registry, target->bag_name);
    if (!b) return -2;

    uint8_t *copy = (uint8_t *)malloc(json_length);
    if (!copy) return -3;
    memcpy(copy, json, json_length);

    free(b->json);
    b->json = copy;
    b->json_length = json_length;
    b->revision = target->target_revision;
    b->discipline = target->discipline;
    memcpy(b->schema_digest, target->target_schema_digest, 32u);
    memcpy(b->content_digest, target->target_json_digest, 32u);
    return 0;
}
