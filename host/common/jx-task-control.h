#ifndef JX_TASK_CONTROL_H
#define JX_TASK_CONTROL_H

#include <stdint.h>
#include "jx-task-manager.h"

#define JX_TASK_CONTROL_VERSION 1u

typedef enum {
    JX_TASK_ACTION_PAUSE = 1,
    JX_TASK_ACTION_RESUME = 2,
    JX_TASK_ACTION_STOP = 3,
    JX_TASK_ACTION_ROLLBACK = 4,
    JX_TASK_ACTION_SWAP = 5
} jx_task_action;

typedef int (*jx_task_control_fn)(uint64_t task_id, jx_task_action action, void *context);

typedef struct {
    uint8_t version;
    jx_task_manager *manager;
    jx_task_control_fn control;
    void *context;
} jx_task_controller;

void jx_task_controller_init(jx_task_controller *controller,
                             jx_task_manager *manager,
                             jx_task_control_fn control,
                             void *context);
int jx_task_controller_apply(jx_task_controller *controller,
                             uint64_t task_id,
                             jx_task_action action);

#endif
