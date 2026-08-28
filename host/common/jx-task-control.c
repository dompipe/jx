#include "jx-task-control.h"
#include <string.h>

void jx_task_controller_init(jx_task_controller *controller,
                             jx_task_manager *manager,
                             jx_task_control_fn control,
                             void *context) {
    if (!controller) return;
    memset(controller, 0, sizeof *controller);
    controller->version = JX_TASK_CONTROL_VERSION;
    controller->manager = manager;
    controller->control = control;
    controller->context = context;
}

int jx_task_controller_apply(jx_task_controller *controller,
                             uint64_t task_id,
                             jx_task_action action) {
    if (!controller || controller->version != JX_TASK_CONTROL_VERSION ||
        !controller->manager || !controller->control) return -1;
    jx_task_record *task = jx_task_manager_find(controller->manager, task_id);
    if (!task) return -2;
    if (action < JX_TASK_ACTION_PAUSE || action > JX_TASK_ACTION_SWAP) return -3;
    if (task->state == JX_TASK_STATE_STOPPED) return -4;

    if (controller->control(task_id, action, controller->context) != 0) {
        task->state = JX_TASK_STATE_ERROR;
        return -5;
    }

    switch (action) {
        case JX_TASK_ACTION_PAUSE: task->state = JX_TASK_STATE_PAUSED; break;
        case JX_TASK_ACTION_RESUME: task->state = JX_TASK_STATE_RUNNING; break;
        case JX_TASK_ACTION_STOP: task->state = JX_TASK_STATE_STOPPED; break;
        case JX_TASK_ACTION_ROLLBACK: task->state = JX_TASK_STATE_RUNNING; break;
        case JX_TASK_ACTION_SWAP: task->state = JX_TASK_STATE_SWAPPING; break;
        default: return -3;
    }
    return 0;
}
