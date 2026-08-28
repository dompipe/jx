#ifndef JX_EXEC_BRANCH_H
#define JX_EXEC_BRANCH_H

#include <stdint.h>
#include "jx-bag-listener.h"

#define JX_EXEC_BRANCH_VERSION 1u

typedef enum {
    JX_EXEC_BRANCH_BYTECODE = 0,
    JX_EXEC_BRANCH_SHADOW = 1
} jx_exec_branch_kind;

typedef struct {
    uint8_t version;
    uint8_t active_branch;
    uint8_t shadow_ready;
    uint8_t fallback_pending;
    uint64_t proven_revision;
    uint8_t proven_schema_digest[JX_BAG_PATCH_DIGEST_BYTES];
} jx_exec_branch;

void jx_exec_branch_init(jx_exec_branch *branch,
                         uint64_t revision,
                         const uint8_t schema_digest[JX_BAG_PATCH_DIGEST_BYTES],
                         int shadow_ready);

/** True only while the compiled shadow is proven against the current Bag. */
int jx_exec_branch_can_shadow(const jx_exec_branch *branch,
                              const jx_bag_patch_current *bag);

/** Select the currently safe execution branch for this Bag. */
jx_exec_branch_kind jx_exec_branch_select(jx_exec_branch *branch,
                                          const jx_bag_patch_current *bag);

/** Mark a newly rebuilt shadow as proven for the committed Bag. */
void jx_exec_branch_promote(jx_exec_branch *branch,
                            const jx_bag_patch_current *bag);

/** Bag-listener callback: PREPARE demotes immediately; rollback restores if still proven. */
int jx_exec_branch_listener(const jx_bag_listener_event *event, void *context);

#endif
