#include "../host/common/jx-bag-listener.h"
#include <stdio.h>
#include <string.h>

static int calls = 0;
static int phases = 0;

static int listener(const jx_bag_listener_event *event, void *context) {
    (void)context;
    ++calls;
    phases |= (1 << event->phase);
    if (event->phase == JX_BAG_LISTENER_PREPARE && !event->json) return -9;
    return 0;
}

int main(void) {
    jx_bag_listener_registry registry;
    jx_bag_listener_registry_init(&registry);
    if (jx_bag_listener_add(&registry, "Controls", JX_BAG_CHANGE_SCHEMA | JX_BAG_CHANGE_CONTENT, listener, NULL) != 0) return 1;

    jx_bag_patch_current before;
    memset(&before, 0, sizeof before);
    before.bag_name = "Controls";
    before.discipline = JX_BAG_DISCIPLINE_VECTOR;
    before.revision = 7u;
    memset(before.schema_digest, 0x11, sizeof before.schema_digest);
    memset(before.content_digest, 0x22, sizeof before.content_digest);

    jx_bag_patch_qualifier target;
    memset(&target, 0, sizeof target);
    target.version = JX_BAG_PATCH_VERSION;
    target.discipline = JX_BAG_DISCIPLINE_VECTOR;
    target.expected_revision = 7u;
    target.target_revision = 8u;
    strcpy(target.bag_name, "Controls");
    memset(target.current_schema_digest, 0x11, sizeof target.current_schema_digest);
    memset(target.current_content_digest, 0x22, sizeof target.current_content_digest);
    memset(target.target_schema_digest, 0x33, sizeof target.target_schema_digest);
    memset(target.target_json_digest, 0x44, sizeof target.target_json_digest);

    uint16_t changes = jx_bag_listener_detect_changes(&before, &target);
    if ((changes & (JX_BAG_CHANGE_SCHEMA | JX_BAG_CHANGE_CONTENT | JX_BAG_CHANGE_REVISION)) !=
        (JX_BAG_CHANGE_SCHEMA | JX_BAG_CHANGE_CONTENT | JX_BAG_CHANGE_REVISION)) return 2;

    static const uint8_t json[] = "{\"gap\":8}";
    jx_bag_listener_event event;
    memset(&event, 0, sizeof event);
    event.version = JX_BAG_LISTENER_VERSION;
    event.change_mask = changes;
    event.before = &before;
    event.target = &target;
    event.json = json;
    event.json_length = sizeof json - 1u;

    event.phase = JX_BAG_LISTENER_PREPARE;
    if (jx_bag_listener_emit(&registry, &event) != 1) return 3;
    event.phase = JX_BAG_LISTENER_COMMIT;
    if (jx_bag_listener_emit(&registry, &event) != 1) return 4;
    event.phase = JX_BAG_LISTENER_ROLLBACK;
    if (jx_bag_listener_emit(&registry, &event) != 1) return 5;

    if (calls != 3 || (phases & (1 << JX_BAG_LISTENER_PREPARE)) == 0 ||
        (phases & (1 << JX_BAG_LISTENER_COMMIT)) == 0 ||
        (phases & (1 << JX_BAG_LISTENER_ROLLBACK)) == 0) return 6;

    if (jx_bag_listener_remove(&registry, "Controls", listener, NULL) != 0 || registry.count != 0u) return 7;
    puts("jx-bag-listener: prepare/commit/rollback listeners ok");
    return 0;
}
