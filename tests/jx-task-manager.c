#include "../host/common/jx-task-manager.h"
#include <assert.h>
#include <stdint.h>
#include <stdio.h>
#include <string.h>

int main(void) {
    jx_task_manager manager;
    jx_task_manager_init(&manager);
    assert(manager.version == JX_TASK_MANAGER_VERSION);
    assert(manager.count == 0u);

    uint64_t task_id = 0u;
    assert(jx_task_manager_add(&manager, "editor.jx", 4242u, 7u, JX_TASK_BRANCH_SHADOW, &task_id) == 0);
    assert(task_id != 0u && manager.count == 1u);

    jx_task_record *task = jx_task_manager_find(&manager, task_id);
    assert(task && strcmp(task->name, "editor.jx") == 0);
    assert(task->os_process_id == 4242u && task->generation == 7u);
    assert(task->state == JX_TASK_STATE_RUNNING && task->branch == JX_TASK_BRANCH_SHADOW);

    /* Stable JX task identity survives generation/branch replacement. */
    const uint64_t stable_id = task->task_id;
    assert(jx_task_manager_set_generation(&manager, task_id, 8u, JX_TASK_BRANCH_BYTECODE) == 0);
    assert(task->task_id == stable_id && task->generation == 8u && task->branch == JX_TASK_BRANCH_BYTECODE);
    assert(jx_task_manager_set_generation(&manager, task_id, 9u, JX_TASK_BRANCH_SHADOW) == 0);
    assert(task->task_id == stable_id && task->generation == 9u && task->branch == JX_TASK_BRANCH_SHADOW);

    assert(jx_task_manager_set_resources(&manager, task_id, 3u, 5u, 2u) == 0);
    assert(jx_task_manager_set_power(&manager, task_id, 1) == 0);
    assert(jx_task_manager_set_maintainer(&manager, task_id, 1) == 0);
    assert(jx_task_manager_account(&manager, task_id, 13u, 9000u) == 0);
    assert(task->bag_count == 3u && task->channel_count == 5u && task->queued_count == 2u);
    assert(task->power_ready == 1u && task->maintainer_managed == 1u);
    assert(task->event_count == 13u && task->cpu_time_ns == 9000u);

    /* Counters accumulate rather than overwrite snapshots. */
    assert(jx_task_manager_account(&manager, task_id, 7u, 1000u) == 0);
    assert(task->event_count == 20u && task->cpu_time_ns == 10000u);

    assert(jx_task_manager_set_state(&manager, task_id, JX_TASK_STATE_PAUSED) == 0);
    assert(task->state == JX_TASK_STATE_PAUSED);

    /* Invalid state/generation/branch and unknown task IDs must not mutate state. */
    assert(jx_task_manager_set_state(&manager, task_id, 0xffu) == -1);
    assert(task->state == JX_TASK_STATE_PAUSED);
    assert(jx_task_manager_set_generation(&manager, task_id, 0u, JX_TASK_BRANCH_SHADOW) == -1);
    assert(jx_task_manager_set_generation(&manager, task_id, 10u, 0xffu) == -1);
    assert(task->generation == 9u && task->branch == JX_TASK_BRANCH_SHADOW);
    assert(jx_task_manager_set_resources(&manager, UINT64_MAX, 1u, 1u, 1u) == -1);
    assert(jx_task_manager_account(&manager, UINT64_MAX, 1u, 1u) == -1);

    /* Multiple tasks remain independently addressable. */
    uint64_t worker_id = 0u;
    assert(jx_task_manager_add(&manager, "worker.jx", 5252u, 1u, JX_TASK_BRANCH_BYTECODE, &worker_id) == 0);
    assert(worker_id != task_id && manager.count == 2u);
    const jx_task_record *worker = jx_task_manager_find_const(&manager, worker_id);
    assert(worker && worker->os_process_id == 5252u && worker->state == JX_TASK_STATE_RUNNING);

    assert(jx_task_manager_remove(&manager, task_id) == 0);
    assert(manager.count == 1u && jx_task_manager_find(&manager, task_id) == NULL);
    assert(jx_task_manager_find(&manager, worker_id) != NULL);
    assert(jx_task_manager_remove(&manager, task_id) == -1);
    assert(jx_task_manager_remove(&manager, worker_id) == 0);
    assert(manager.count == 0u);

    puts("jx-task-manager: stable identity, accounting, isolation and invalid-input handling ok");
    return 0;
}
