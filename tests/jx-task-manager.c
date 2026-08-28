#include "../host/common/jx-task-manager.h"
#include <assert.h>
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

    assert(jx_task_manager_set_resources(&manager, task_id, 3u, 5u, 2u) == 0);
    assert(jx_task_manager_set_power(&manager, task_id, 1) == 0);
    assert(jx_task_manager_set_maintainer(&manager, task_id, 1) == 0);
    assert(jx_task_manager_account(&manager, task_id, 13u, 9000u) == 0);
    assert(task->bag_count == 3u && task->channel_count == 5u && task->queued_count == 2u);
    assert(task->power_ready == 1u && task->maintainer_managed == 1u);
    assert(task->event_count == 13u && task->cpu_time_ns == 9000u);

    assert(jx_task_manager_set_state(&manager, task_id, JX_TASK_STATE_PAUSED) == 0);
    assert(jx_task_manager_set_generation(&manager, task_id, 8u, JX_TASK_BRANCH_BYTECODE) == 0);
    assert(task->state == JX_TASK_STATE_PAUSED && task->generation == 8u && task->branch == JX_TASK_BRANCH_BYTECODE);

    assert(jx_task_manager_remove(&manager, task_id) == 0);
    assert(manager.count == 0u && jx_task_manager_find(&manager, task_id) == NULL);
    puts("jx-task-manager: stable task registry and runtime state accounting ok");
    return 0;
}
