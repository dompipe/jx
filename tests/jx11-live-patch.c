#include <stdio.h>
#include <stdint.h>
#include <string.h>
#include "../host/linux/jx11-live-patch.h"

static void fill(uint8_t d[JX_PATCH_DIGEST_BYTES], uint8_t seed) {
    for (size_t i = 0; i < JX_PATCH_DIGEST_BYTES; ++i) d[i] = (uint8_t)(seed + i);
}

int main(void) {
    uint8_t base[JX_PATCH_DIGEST_BYTES];
    uint8_t next[JX_PATCH_DIGEST_BYTES];
    fill(base, 0x10u);
    fill(next, 0x80u);

    jx11_live_patch manager;
    jx11_live_patch_init(&manager, 3u, base,
        JX_PATCH_CAP_HOT_TABLES | JX_PATCH_CAP_REACTIONS | JX_PATCH_CAP_CONFIG);

    static const int api_v3 = 3;
    static const int api_v4 = 4;
    manager.active.api_table = &api_v3;

    jx_patch_manifest manifest;
    memset(&manifest, 0, sizeof manifest);
    manifest.version = JX_LIVE_PATCH_VERSION;
    manifest.protocol = JX_PATCH_PROTOCOL_HTTPS;
    manifest.base_generation = 3u;
    manifest.generation = 4u;
    manifest.nonce = 11u;
    manifest.capability_mask = JX_PATCH_CAP_HOT_TABLES;
    manifest.patch_length = 64u;
    memcpy(manifest.base_digest, base, sizeof base);
    memcpy(manifest.target_digest, next, sizeof next);

    jx11_generation staged;
    memset(&staged, 0, sizeof staged);
    staged.generation = 4u;
    memcpy(staged.digest, next, sizeof next);
    staged.api_table = &api_v4;

    if (jx11_live_patch_stage(&manager, &manifest, &staged) != JX_PATCH_OK) return 2;
    if (!manager.pending_ready || manager.active.generation != 3u || manager.active.api_table != &api_v3) return 3;

    /* No swap occurs merely by staging: active execution remains generation 3. */
    if (jx11_live_patch_commit_pending(&manager, &manifest) != JX_PATCH_OK) return 4;
    if (manager.pending_ready || manager.active.generation != 4u || manager.active.api_table != &api_v4) return 5;
    if (!manager.previous_valid || manager.previous.generation != 3u || manager.previous.api_table != &api_v3) return 6;

    if (jx11_live_patch_rollback(&manager) != JX_PATCH_OK) return 7;
    if (manager.active.generation != 3u || manager.active.api_table != &api_v3) return 8;
    if (manager.security.generation != 3u || !jx_patch_digest_equal(manager.security.digest, base)) return 9;

    puts("jx11-live-patch: ok stage safe-point-swap rollback");
    return 0;
}
