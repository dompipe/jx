#include "../host/common/jx-task-control.h"
#include <assert.h>
#include <stdio.h>

typedef struct { int calls; jx_task_action last; } ctx_t;
static int control_cb(uint64_t task_id,jx_task_action action,void *context){ctx_t *c=(ctx_t*)context; assert(task_id!=0u); c->calls++; c->last=action; return 0;}
int main(void){
    jx_task_manager m; jx_task_manager_init(&m); uint64_t id=0u; assert(jx_task_manager_add(&m,"worker.jx",99u,1u,JX_TASK_BRANCH_SHADOW,&id)==0);
    ctx_t ctx={0}; jx_task_controller ctl; jx_task_controller_init(&ctl,&m,control_cb,&ctx);
    assert(jx_task_controller_apply(&ctl,id,JX_TASK_ACTION_PAUSE)==0); assert(jx_task_manager_find(&m,id)->state==JX_TASK_STATE_PAUSED);
    assert(jx_task_controller_apply(&ctl,id,JX_TASK_ACTION_RESUME)==0); assert(jx_task_manager_find(&m,id)->state==JX_TASK_STATE_RUNNING);
    assert(jx_task_controller_apply(&ctl,id,JX_TASK_ACTION_SWAP)==0); assert(jx_task_manager_find(&m,id)->state==JX_TASK_STATE_SWAPPING);
    assert(jx_task_controller_apply(&ctl,id,JX_TASK_ACTION_ROLLBACK)==0); assert(jx_task_manager_find(&m,id)->state==JX_TASK_STATE_RUNNING);
    assert(jx_task_controller_apply(&ctl,id,JX_TASK_ACTION_STOP)==0); assert(jx_task_manager_find(&m,id)->state==JX_TASK_STATE_STOPPED);
    assert(ctx.calls==5 && ctx.last==JX_TASK_ACTION_STOP);
    puts("jx-task-control: pause resume swap rollback stop dispatch ok"); return 0;
}
