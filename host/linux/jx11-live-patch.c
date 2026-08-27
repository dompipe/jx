#include "jx11-live-patch.h"
#include <string.h>

void jx11_live_patch_init(jx11_live_patch *manager,
                          uint64_t generation,
                          const uint8_t digest[JX_PATCH_DIGEST_BYTES],
                          uint32_t allowed_capabilities) {
    if (!manager) return;
    memset(manager, 0, sizeof *manager);
    manager->active.generation = generation;
    manager->security.generation = generation;
    manager->security.allowed_capabilities = allowed_capabilities & JX_PATCH_CAP_ALL;
    if (digest) {
        memcpy(manager->active.digest, digest, JX_PATCH_DIGEST_BYTES);
        memcpy(manager->security.digest, digest, JX_PATCH_DIGEST_BYTES);
    }
}

int jx11_live_patch_stage(jx11_live_patch *manager,
                          const jx_patch_manifest *manifest,
                          const jx11_generation *staged) {
    if (!manager || !manifest || !staged) return JX_PATCH_ERR_ARGUMENT;
    if (manifest->base_generation != manager->active.generation ||
        manifest->generation != staged->generation ||
        !jx_patch_digest_equal(manifest->base_digest, manager->active.digest) ||
        !jx_patch_digest_equal(manifest->target_digest, staged->digest)) {
        return JX_PATCH_ERR_BASE_DIGEST;
    }
    manager->pending = *staged;
    manager->pending_ready = 1u;
    return JX_PATCH_OK;
}

int jx11_live_patch_commit_pending(jx11_live_patch *manager,
                                    const jx_patch_manifest *manifest) {
    if (!manager || !manifest) return JX_PATCH_ERR_ARGUMENT;
    if (!manager->pending_ready) return JX_PATCH_ERR_ARGUMENT;
    if (manager->pending.generation != manifest->generation ||
        !jx_patch_digest_equal(manager->pending.digest, manifest->target_digest)) {
        return JX_PATCH_ERR_TARGET_DIGEST;
    }
    int committed = jx_patch_commit(&manager->security, manifest);
    if (committed != JX_PATCH_OK) return committed;
    manager->previous = manager->active;
    manager->previous_valid = 1u;
    manager->active = manager->pending;
    memset(&manager->pending, 0, sizeof manager->pending);
    manager->pending_ready = 0u;
    return JX_PATCH_OK;
}

int jx11_live_patch_rollback(jx11_live_patch *manager) {
    if (!manager || !manager->previous_valid) return JX_PATCH_ERR_ARGUMENT;
    jx11_generation failed = manager->active;
    manager->active = manager->previous;
    manager->previous = failed;
    manager->security.generation = manager->active.generation;
    memcpy(manager->security.digest, manager->active.digest, JX_PATCH_DIGEST_BYTES);
    return JX_PATCH_OK;
}
