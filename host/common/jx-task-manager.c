#include "jx-task-manager.h"
#include <string.h>

static int state_valid(uint8_t state) {
    return state >= JX_TASK_STATE_RUNNING && state <= JX_TASK_STATE_ERROR;
}

static int branch_valid(uint8_t branch) {
    return branch == JX_TASK_BRANCH_BYTECODE || branch == JX_TASK_BRANCH_SHADOW;
}

void jx_task_manager_init(jx_task_manager *manager) {
    if (!manager) return;
    memset(manager, 0, sizeof *manager);
    manager->version = JX_TASK_MANAGER_VERSION;
    manager->next_task_id = 1u;
}

jx_task_record *jx_task_manager_find(jx_task_manager *manager, uint64_t task_id) {
    if (!manager || task_id == 0u) return NULL;
    for (size_t i = 0; i < JX_TASK_MANAGER_MAX; ++i)
        if (manager->tasks[i].in_use && manager->tasks[i].task_id == task_id) return &manager->tasks[i];
    return NULL;
}

const jx_task_record *jx_task_manager_find_const(const jx_task_manager *manager, uint64_t task_id) {
    if (!manager || task_id == 0u) return NULL;
    for (size_t i = 0; i < JX_TASK_MANAGER_MAX; ++i)
        if (manager->tasks[i].in_use && manager->tasks[i].task_id == task_id) return &manager->tasks[i];
    return NULL;
}

int jx_task_manager_add(jx_task_manager *manager,
                        const char *name,
                        uint64_t os_process_id,
                        uint64_t generation,
                        uint8_t branch,
                        uint64_t *task_id_out) {
    if (!manager || manager->version != JX_TASK_MANAGER_VERSION || !name || !*name ||
        !branch_valid(branch) || generation == 0u || manager->count >= JX_TASK_MANAGER_MAX) return -1;
    size_t n = strlen(name);
    if (n > JX_TASK_NAME_MAX) return -1;
    for (size_t i = 0; i < JX_TASK_MANAGER_MAX; ++i) {
        jx_task_record *task = &manager->tasks[i];
        if (task->in_use) continue;
        memset(task, 0, sizeof *task);
        task->version = JX_TASK_MANAGER_VERSION;
        task->in_use = 1u;
        task->state = JX_TASK_STATE_RUNNING;
        task->branch = branch;
        task->task_id = manager->next_task_id++;
        if (manager->next_task_id == 0u) manager->next_task_id = 1u;
        task->os_process_id = os_process_id;
        task->generation = generation;
        memcpy(task->name, name, n + 1u);
        manager->count++;
        if (task_id_out) *task_id_out = task->task_id;
        return 0;
    }
    return -1;
}

int jx_task_manager_remove(jx_task_manager *manager, uint64_t task_id) {
    jx_task_record *task = jx_task_manager_find(manager, task_id);
    if (!task) return -1;
    memset(task, 0, sizeof *task);
    if (manager->count) manager->count--;
    return 0;
}

int jx_task_manager_set_state(jx_task_manager *manager, uint64_t task_id, uint8_t state) {
    jx_task_record *task = jx_task_manager_find(manager, task_id);
    if (!task || !state_valid(state)) return -1;
    task->state = state;
    return 0;
}

int jx_task_manager_set_generation(jx_task_manager *manager, uint64_t task_id, uint64_t generation, uint8_t branch) {
    jx_task_record *task = jx_task_manager_find(manager, task_id);
    if (!task || generation == 0u || !branch_valid(branch)) return -1;
    task->generation = generation;
    task->branch = branch;
    return 0;
}

int jx_task_manager_set_resources(jx_task_manager *manager, uint64_t task_id,
                                  uint32_t bag_count, uint32_t channel_count, uint32_t queued_count) {
    jx_task_record *task = jx_task_manager_find(manager, task_id);
    if (!task) return -1;
    task->bag_count = bag_count;
    task->channel_count = channel_count;
    task->queued_count = queued_count;
    return 0;
}

int jx_task_manager_set_power(jx_task_manager *manager, uint64_t task_id, int power_ready) {
    jx_task_record *task = jx_task_manager_find(manager, task_id);
    if (!task) return -1;
    task->power_ready = power_ready ? 1u : 0u;
    return 0;
}

int jx_task_manager_set_maintainer(jx_task_manager *manager, uint64_t task_id, int managed) {
    jx_task_record *task = jx_task_manager_find(manager, task_id);
    if (!task) return -1;
    task->maintainer_managed = managed ? 1u : 0u;
    return 0;
}

int jx_task_manager_account(jx_task_manager *manager, uint64_t task_id,
                            uint64_t events_delta, uint64_t cpu_time_ns_delta) {
    jx_task_record *task = jx_task_manager_find(manager, task_id);
    if (!task) return -1;
    task->event_count += events_delta;
    task->cpu_time_ns += cpu_time_ns_delta;
    return 0;
}
