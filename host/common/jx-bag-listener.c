#include "jx-bag-listener.h"
#include <string.h>

void jx_bag_listener_registry_init(jx_bag_listener_registry *registry) {
    if (!registry) return;
    memset(registry, 0, sizeof *registry);
}

static int valid_name(const char *name) {
    if (!name || !*name) return 0;
    size_t n = strlen(name);
    return n <= JX_BAG_PATCH_NAME_MAX;
}

int jx_bag_listener_add(jx_bag_listener_registry *registry,
                        const char *bag_name,
                        uint16_t change_mask,
                        jx_bag_listener_fn callback,
                        void *context) {
    if (!registry || !valid_name(bag_name) || !callback ||
        change_mask == 0u || (change_mask & ~JX_BAG_CHANGE_ALL) != 0u) return -1;
    for (size_t i = 0; i < JX_BAG_LISTENER_MAX; ++i) {
        jx_bag_listener *entry = &registry->entries[i];
        if (entry->in_use && entry->callback == callback && entry->context == context &&
            strcmp(entry->bag_name, bag_name) == 0) {
            entry->change_mask = change_mask;
            return 0;
        }
    }
    for (size_t i = 0; i < JX_BAG_LISTENER_MAX; ++i) {
        jx_bag_listener *entry = &registry->entries[i];
        if (entry->in_use) continue;
        memset(entry, 0, sizeof *entry);
        memcpy(entry->bag_name, bag_name, strlen(bag_name) + 1u);
        entry->change_mask = change_mask;
        entry->callback = callback;
        entry->context = context;
        entry->in_use = 1u;
        ++registry->count;
        return 0;
    }
    return -2;
}

int jx_bag_listener_remove(jx_bag_listener_registry *registry,
                           const char *bag_name,
                           jx_bag_listener_fn callback,
                           void *context) {
    if (!registry || !valid_name(bag_name) || !callback) return -1;
    for (size_t i = 0; i < JX_BAG_LISTENER_MAX; ++i) {
        jx_bag_listener *entry = &registry->entries[i];
        if (!entry->in_use || entry->callback != callback || entry->context != context ||
            strcmp(entry->bag_name, bag_name) != 0) continue;
        memset(entry, 0, sizeof *entry);
        if (registry->count) --registry->count;
        return 0;
    }
    return -2;
}

uint16_t jx_bag_listener_detect_changes(const jx_bag_patch_current *before,
                                        const jx_bag_patch_qualifier *target) {
    if (!before || !target) return 0u;
    uint16_t mask = 0u;
    if (!jx_bag_patch_digest_equal(before->schema_digest, target->target_schema_digest))
        mask |= JX_BAG_CHANGE_SCHEMA;
    if (!jx_bag_patch_digest_equal(before->content_digest, target->target_json_digest))
        mask |= JX_BAG_CHANGE_CONTENT;
    if (before->revision != target->target_revision)
        mask |= JX_BAG_CHANGE_REVISION;
    return mask;
}

int jx_bag_listener_emit(const jx_bag_listener_registry *registry,
                         const jx_bag_listener_event *event) {
    if (!registry || !event || event->version != JX_BAG_LISTENER_VERSION ||
        !event->target || !event->target->bag_name[0] ||
        event->phase < JX_BAG_LISTENER_PREPARE || event->phase > JX_BAG_LISTENER_ROLLBACK)
        return -1;
    int delivered = 0;
    for (size_t i = 0; i < JX_BAG_LISTENER_MAX; ++i) {
        const jx_bag_listener *entry = &registry->entries[i];
        if (!entry->in_use || strcmp(entry->bag_name, event->target->bag_name) != 0 ||
            (entry->change_mask & event->change_mask) == 0u) continue;
        int rc = entry->callback(event, entry->context);
        if (rc != 0 && event->phase == JX_BAG_LISTENER_PREPARE) return rc;
        ++delivered;
    }
    return delivered;
}
