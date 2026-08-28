#include "jx-exec-branch.h"
#include <string.h>

void jx_exec_branch_init(jx_exec_branch *branch,
                         uint64_t revision,
                         const uint8_t schema_digest[JX_BAG_PATCH_DIGEST_BYTES],
                         int shadow_ready) {
    if (!branch) return;
    memset(branch, 0, sizeof *branch);
    branch->version = JX_EXEC_BRANCH_VERSION;
    branch->proven_revision = revision;
    if (schema_digest) memcpy(branch->proven_schema_digest, schema_digest, JX_BAG_PATCH_DIGEST_BYTES);
    branch->shadow_ready = shadow_ready ? 1u : 0u;
    branch->active_branch = branch->shadow_ready ? JX_EXEC_BRANCH_SHADOW : JX_EXEC_BRANCH_BYTECODE;
}

int jx_exec_branch_can_shadow(const jx_exec_branch *branch,
                              const jx_bag_patch_current *bag) {
    if (!branch || !bag || branch->version != JX_EXEC_BRANCH_VERSION || !branch->shadow_ready) return 0;
    if (branch->proven_revision != bag->revision) return 0;
    return jx_bag_patch_digest_equal(branch->proven_schema_digest, bag->schema_digest);
}

jx_exec_branch_kind jx_exec_branch_select(jx_exec_branch *branch,
                                          const jx_bag_patch_current *bag) {
    if (!branch) return JX_EXEC_BRANCH_BYTECODE;
    if (jx_exec_branch_can_shadow(branch, bag) && !branch->fallback_pending) {
        branch->active_branch = JX_EXEC_BRANCH_SHADOW;
    } else {
        branch->active_branch = JX_EXEC_BRANCH_BYTECODE;
    }
    return (jx_exec_branch_kind)branch->active_branch;
}

void jx_exec_branch_promote(jx_exec_branch *branch,
                            const jx_bag_patch_current *bag) {
    if (!branch || !bag) return;
    branch->proven_revision = bag->revision;
    memcpy(branch->proven_schema_digest, bag->schema_digest, JX_BAG_PATCH_DIGEST_BYTES);
    branch->shadow_ready = 1u;
    branch->fallback_pending = 0u;
    branch->active_branch = JX_EXEC_BRANCH_SHADOW;
}

int jx_exec_branch_listener(const jx_bag_listener_event *event, void *context) {
    jx_exec_branch *branch = (jx_exec_branch *)context;
    if (!event || !branch) return -1;
    if (event->phase == JX_BAG_LISTENER_PREPARE) {
        branch->fallback_pending = 1u;
        branch->active_branch = JX_EXEC_BRANCH_BYTECODE;
        return 0;
    }
    if (event->phase == JX_BAG_LISTENER_COMMIT) {
        /* The old shadow is no longer authoritative. Bytecode remains live until
         * the rebuilt shadow explicitly calls jx_exec_branch_promote(). */
        branch->shadow_ready = 0u;
        branch->fallback_pending = 0u;
        branch->active_branch = JX_EXEC_BRANCH_BYTECODE;
        return 0;
    }
    if (event->phase == JX_BAG_LISTENER_ROLLBACK) {
        branch->fallback_pending = 0u;
        branch->active_branch = branch->shadow_ready ? JX_EXEC_BRANCH_SHADOW : JX_EXEC_BRANCH_BYTECODE;
        return 0;
    }
    return -1;
}
