#ifndef JX11_TASK_MANAGER_H
#define JX11_TASK_MANAGER_H

#include <stdint.h>
#include <sys/types.h>
#include <xcb/xcb.h>
#include "../common/jx-task-manager.h"
#include "../common/jx-task-control.h"

#define JX11_TASK_PROCESS_MAX 256u

typedef struct {
    uint8_t in_use;
    pid_t pid;
    uint64_t task_id;
    uint64_t last_cpu_ns;
} jx11_task_process;

typedef struct {
    jx_task_manager tasks;
    jx_task_controller controller;
    jx11_task_process processes[JX11_TASK_PROCESS_MAX];
    xcb_connection_t *connection;
    xcb_screen_t *screen;
    xcb_window_t window;
    xcb_gcontext_t gc;
    uint64_t selected_task_id;
    uint64_t generation;
    uint64_t last_refresh_ms;
    uint8_t visible;
} jx11_task_manager;

void jx11_task_manager_init(jx11_task_manager *manager, uint64_t generation);
int jx11_task_manager_bind_x11(jx11_task_manager *manager,
                               xcb_connection_t *connection,
                               xcb_screen_t *screen);
int jx11_task_manager_register(jx11_task_manager *manager,
                               pid_t pid,
                               const char *program);
void jx11_task_manager_refresh(jx11_task_manager *manager);
int jx11_task_manager_handle_event(jx11_task_manager *manager,
                                   xcb_generic_event_t *event);
void jx11_task_manager_toggle(jx11_task_manager *manager);
void jx11_task_manager_dispose(jx11_task_manager *manager);

#endif
