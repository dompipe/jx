#include "../host/common/jx-exec-branch.h"
#include <stdio.h>
#include <string.h>

static void fill(uint8_t d[JX_BAG_PATCH_DIGEST_BYTES], uint8_t v) {
    memset(d, v, JX_BAG_PATCH_DIGEST_BYTES);
}

int main(void) {
    uint8_t schema1[JX_BAG_PATCH_DIGEST_BYTES];
    uint8_t schema2[JX_BAG_PATCH_DIGEST_BYTES];
    fill(schema1, 0x11u);
    fill(schema2, 0x22u);

    jx_bag_patch_current bag1;
    memset(&bag1, 0, sizeof bag1);
    bag1.bag_name = "Controls";
    bag1.discipline = JX_BAG_DISCIPLINE_VECTOR;
    bag1.revision = 41u;
    memcpy(bag1.schema_digest, schema1, sizeof schema1);

    jx_exec_branch branch;
    jx_exec_branch_init(&branch, 41u, schema1, 1);
    if (jx_exec_branch_select(&branch, &bag1) != JX_EXEC_BRANCH_SHADOW) return 1;

    jx_bag_patch_qualifier target;
    memset(&target, 0, sizeof target);
    target.version = JX_BAG_PATCH_VERSION;
    target.discipline = JX_BAG_DISCIPLINE_VECTOR;
    target.expected_revision = 41u;
    target.target_revision = 42u;
    strcpy(target.bag_name, "Controls");
    memcpy(target.target_schema_digest, schema2, sizeof schema2);

    jx_bag_listener_event event;
    memset(&event, 0, sizeof event);
    event.version = JX_BAG_LISTENER_VERSION;
    event.before = &bag1;
    event.target = &target;

    event.phase = JX_BAG_LISTENER_PREPARE;
    if (jx_exec_branch_listener(&event, &branch) != 0) return 2;
    if (branch.active_branch != JX_EXEC_BRANCH_BYTECODE) return 3;

    event.phase = JX_BAG_LISTENER_ROLLBACK;
    if (jx_exec_branch_listener(&event, &branch) != 0) return 4;
    if (branch.active_branch != JX_EXEC_BRANCH_SHADOW) return 5;

    event.phase = JX_BAG_LISTENER_PREPARE;
    if (jx_exec_branch_listener(&event, &branch) != 0) return 6;
    event.phase = JX_BAG_LISTENER_COMMIT;
    if (jx_exec_branch_listener(&event, &branch) != 0) return 7;
    if (branch.active_branch != JX_EXEC_BRANCH_BYTECODE || branch.shadow_ready) return 8;

    jx_bag_patch_current bag2 = bag1;
    bag2.revision = 42u;
    memcpy(bag2.schema_digest, schema2, sizeof schema2);
    jx_exec_branch_promote(&branch, &bag2);
    if (jx_exec_branch_select(&branch, &bag2) != JX_EXEC_BRANCH_SHADOW) return 9;

    bag2.revision = 43u;
    if (jx_exec_branch_select(&branch, &bag2) != JX_EXEC_BRANCH_BYTECODE) return 10;

    puts("jx-exec-branch: shadow -> bytecode -> shadow filter semantics ok");
    return 0;
}
