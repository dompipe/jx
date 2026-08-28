#ifndef JX_BAG_LISTENER_H
#define JX_BAG_LISTENER_H

#include <stddef.h>
#include <stdint.h>
#include "jx-bag-patch.h"

#define JX_BAG_LISTENER_VERSION 1u
#define JX_BAG_LISTENER_MAX 64u

#define JX_BAG_CHANGE_SCHEMA   (1u << 0)
#define JX_BAG_CHANGE_CONTENT  (1u << 1)
#define JX_BAG_CHANGE_REVISION (1u << 2)
#define JX_BAG_CHANGE_ALL      (JX_BAG_CHANGE_SCHEMA | JX_BAG_CHANGE_CONTENT | JX_BAG_CHANGE_REVISION)

typedef enum {
    JX_BAG_LISTENER_PREPARE = 1,
    JX_BAG_LISTENER_COMMIT = 2,
    JX_BAG_LISTENER_ROLLBACK = 3
} jx_bag_listener_phase;

typedef struct {
    uint8_t version;
    uint8_t phase;
    uint16_t change_mask;
    const jx_bag_patch_current *before;
    const jx_bag_patch_qualifier *target;
    const uint8_t *json;
    size_t json_length;
    void *candidate;
} jx_bag_listener_event;

typedef int (*jx_bag_listener_fn)(const jx_bag_listener_event *event, void *context);

typedef struct {
    char bag_name[JX_BAG_PATCH_NAME_MAX + 1u];
    uint16_t change_mask;
    jx_bag_listener_fn callback;
    void *context;
    uint8_t in_use;
} jx_bag_listener;

typedef struct {
    jx_bag_listener entries[JX_BAG_LISTENER_MAX];
    size_t count;
} jx_bag_listener_registry;

void jx_bag_listener_registry_init(jx_bag_listener_registry *registry);
int jx_bag_listener_add(jx_bag_listener_registry *registry,
                        const char *bag_name,
                        uint16_t change_mask,
                        jx_bag_listener_fn callback,
                        void *context);
int jx_bag_listener_remove(jx_bag_listener_registry *registry,
                           const char *bag_name,
                           jx_bag_listener_fn callback,
                           void *context);
uint16_t jx_bag_listener_detect_changes(const jx_bag_patch_current *before,
                                        const jx_bag_patch_qualifier *target);
int jx_bag_listener_emit(const jx_bag_listener_registry *registry,
                         const jx_bag_listener_event *event);

#endif
