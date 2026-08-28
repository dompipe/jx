#ifndef JX11_LIVE_PATCH_H
#define JX11_LIVE_PATCH_H

#include <stdint.h>
#include <stddef.h>
#include "../common/jx-live-patch.h"
#include "jx11-patch-module.h"

typedef struct {
    uint64_t generation;
    uint8_t digest[JX_PATCH_DIGEST_BYTES];
    const void *api_table;
    const void *reaction_table;
    const void *config_table;
    const void *asset_table;
    void *native_handle;
    const jx11_patch_module_v1 *native_module;
} jx11_generation;

typedef struct {
    jx11_generation active;
    jx11_generation pending;
    jx11_generation previous;
    jx_patch_state security;
    uint8_t pending_ready;
    uint8_t previous_valid;
} jx11_live_patch;

void jx11_live_patch_init(jx11_live_patch *manager,
                          uint64_t generation,
                          const uint8_t digest[JX_PATCH_DIGEST_BYTES],
                          uint32_t allowed_capabilities);

/** Release resources owned by one generation and clear it. */
void jx11_generation_release(jx11_generation *generation);

/** Drop a staged generation that will not be committed. */
void jx11_live_patch_discard_pending(jx11_live_patch *manager);

/** Release active/pending/rollback generations during host shutdown. */
void jx11_live_patch_dispose(jx11_live_patch *manager);

int jx11_live_patch_stage(jx11_live_patch *manager,
                          const jx_patch_manifest *manifest,
                          const jx11_generation *staged);

/** Call only at a JX11 event-batch/quiescent boundary. */
int jx11_live_patch_commit_pending(jx11_live_patch *manager,
                                    const jx_patch_manifest *manifest);

/** Restore the previous prepared generation at a safe boundary. */
int jx11_live_patch_rollback(jx11_live_patch *manager);

#endif
