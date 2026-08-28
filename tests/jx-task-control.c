#include "../host/common/jx-task-control.h"
#include <assert.h>
#include <stdio.h>

typedef struct {
    int calls;
    int fail_next;
    jx_task_action last;
} ctx_t;

static int control_cb(uint64_t task_id, jx_task_action action, void *context) {
    ctx_t *c = (ctx_t *)context;
    assert(task_id != 0u);
    c->calls++;
    c->last = action;
    if (c->fail_next) {
        c->fail_next = 0;
        return -1;
    }
    return 0;
}

int main(void) {
    jx_task_manager m;
    jx_task_manager_init(&m);
    uint64_t id = 0u;
    assert(jx_task_manager_add(&m, "worker.jx", 99u, 1u, JX_TASK_BRANCH_SHADOW, &id) == 0);

    ctx_t ctx = {0};
    jx_task_controller ctl;
    jx_task_controller_init(&ctl, &m, control_cb, &ctx);

    assert(jx_task_controller_apply(&ctl, id, JX_TASK_ACTION_PAUSE) == 0);
    assert(jx_task_manager_find(&m, id)->state == JX_TASK_STATE_PAUSED);
    assert(jx_task_controller_apply(&ctl, id, JX_TASK_ACTION_RESUME) == 0);
    assert(jx_task_manager_find(&m, id)->state == JX_TASK_STATE_RUNNING);
    assert(jx_task_controller_apply(&ctl, id, JX_TASK_ACTION_SWAP) == 0);
    assert(jx_task_manager_find(&m, id)->state == JX_TASK_STATE_SWAPPING);
    assert(jx_task_controller_apply(&ctl, id, JX_TASK_ACTION_ROLLBACK) == 0);
    assert(jx_task_manager_find(&m, id)->state == JX_TASK_STATE_RUNNING);

    /* A host-side failure is visible as ERROR and is not mistaken for success. */
    ctx.fail_next = 1;
    assert(jx_task_controller_apply(&ctl, id, JX_TASK_ACTION_PAUSE) == -5);
    assert(jx_task_manager_find(&m, id)->state == JX_TASK_STATE_ERROR);

    /* A successful recovery action can move the task back to a known state. */
    assert(jx_task_controller_apply(&ctl, id, JX_TASK_ACTION_RESUME) == 0);
    assert(jx_task_manager_find(&m, id)->state == JX_TASK_STATE_RUNNING);
    assert(jx_task_controller_apply(&ctl, id, JX_TASK_ACTION_STOP) == 0);
    assert(jx_task_manager_find(&m, id)->state == JX_TASK_STATE_STOPPED);

    /* Stopped tasks cannot be controlled again and callback must not be invoked. */
    const int calls_after_stop = ctx.calls;
    assert(jx_task_controller_apply(&ctl, id, JX_TASK_ACTION_RESUME) == -4);
    assert(ctx.calls == calls_after_stop);

    /* Unknown task and invalid action fail before host dispatch. */
    assert(jx_task_controller_apply(&ctl, id + 999u, JX_TASK_ACTION_PAUSE) == -2);
    assert(jx_task_controller_apply(&ctl, id, (jx_task_action)0xffu) == -4);
    assert(ctx.calls == calls_after_stop);

    puts("jx-task-control: transitions, host failure, stop gate and recovery semantics ok");
    return 0;
}
