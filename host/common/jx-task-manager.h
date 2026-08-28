#ifndef JX_TASK_MANAGER_H
#define JX_TASK_MANAGER_H

#include <stddef.h>
#include <stdint.h>

#define JX_TASK_MANAGER_VERSION 1u
#define JX_TASK_MANAGER_MAX 256u
#define JX_TASK_NAME_MAX 127u

#define JX_TASK_STATE_RUNNING  1u
#define JX_TASK_STATE_PAUSED   2u
#define JX_TASK_STATE_SWAPPING 3u
#define JX_TASK_STATE_STOPPED  4u
#define JX_TASK_STATE_ERROR    5u

#define JX_TASK_BRANCH_BYTECODE 0u
#define JX_TASK_BRANCH_SHADOW   1u

typedef struct {
    uint8_t version;
    uint8_t in_use;
    uint8_t state;
    uint8_t branch;
    uint64_t task_id;
    uint64_t os_process_id;
    uint64_t generation;
    uint64_t event_count;
    uint64_t cpu_time_ns;
    uint32_t bag_count;
    uint32_t channel_count;
    uint32_t queued_count;
    uint8_t power_ready;
    uint8_t maintainer_managed;
    uint16_t reserved;
    char name[JX_TASK_NAME_MAX + 1u];
} jx_task_record;

typedef struct {
    uint8_t version;
    uint64_t next_task_id;
    size_t count;
    jx_task_record tasks[JX_TASK_MANAGER_MAX];
} jx_task_manager;

void jx_task_manager_init(jx_task_manager *manager);
int jx_task_manager_add(jx_task_manager *manager,
                        const char *name,
                        uint64_t os_process_id,
                        uint64_t generation,
                        uint8_t branch,
                        uint64_t *task_id_out);
jx_task_record *jx_task_manager_find(jx_task_manager *manager, uint64_t task_id);
const jx_task_record *jx_task_manager_find_const(const jx_task_manager *manager, uint64_t task_id);
int jx_task_manager_remove(jx_task_manager *manager, uint64_t task_id);
int jx_task_manager_set_state(jx_task_manager *manager, uint64_t task_id, uint8_t state);
int jx_task_manager_set_generation(jx_task_manager *manager, uint64_t task_id, uint64_t generation, uint8_t branch);
int jx_task_manager_set_resources(jx_task_manager *manager, uint64_t task_id,
                                  uint32_t bag_count, uint32_t channel_count, uint32_t queued_count);
int jx_task_manager_set_power(jx_task_manager *manager, uint64_t task_id, int power_ready);
int jx_task_manager_set_maintainer(jx_task_manager *manager, uint64_t task_id, int managed);
int jx_task_manager_account(jx_task_manager *manager, uint64_t task_id,
                            uint64_t events_delta, uint64_t cpu_time_ns_delta);

#endif
