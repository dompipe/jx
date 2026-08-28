#define _POSIX_C_SOURCE 200809L
#include "jx11-task-manager.h"
#include <cairo/cairo.h>
#include <cairo/cairo-xcb.h>
#include <errno.h>
#include <signal.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/wait.h>
#include <time.h>
#include <unistd.h>

#define TM_WIDTH 760u
#define TM_HEIGHT 480u
#define TM_HEADER 56
#define TM_ROW_H 28
#define TM_ROWS 12
#define TM_BUTTON_Y 430
#define TM_BUTTON_W 116
#define TM_BUTTON_H 32

static uint64_t monotonic_ms(void) {
    struct timespec ts;
    if (clock_gettime(CLOCK_MONOTONIC, &ts) != 0) return 0u;
    return (uint64_t)ts.tv_sec * 1000u + (uint64_t)ts.tv_nsec / 1000000u;
}

static jx11_task_process *process_for_task(jx11_task_manager *manager, uint64_t task_id) {
    for (size_t i = 0; i < JX11_TASK_PROCESS_MAX; ++i)
        if (manager->processes[i].in_use && manager->processes[i].task_id == task_id)
            return &manager->processes[i];
    return NULL;
}

static int linux_control(uint64_t task_id, jx_task_action action, void *context) {
    jx11_task_manager *manager = (jx11_task_manager *)context;
    jx11_task_process *process = process_for_task(manager, task_id);
    if (!process || process->pid <= 0) return -1;
    int sig = 0;
    switch (action) {
        case JX_TASK_ACTION_PAUSE: sig = SIGSTOP; break;
        case JX_TASK_ACTION_RESUME: sig = SIGCONT; break;
        case JX_TASK_ACTION_STOP: sig = SIGTERM; break;
        case JX_TASK_ACTION_ROLLBACK:
        case JX_TASK_ACTION_SWAP:
        default: return -1;
    }
    return kill(process->pid, sig) == 0 ? 0 : -1;
}

static uint64_t read_cpu_ns(pid_t pid) {
    char path[64];
    snprintf(path, sizeof path, "/proc/%ld/stat", (long)pid);
    FILE *fp = fopen(path, "r");
    if (!fp) return 0u;
    char line[4096];
    if (!fgets(line, sizeof line, fp)) { fclose(fp); return 0u; }
    fclose(fp);
    char *close = strrchr(line, ')');
    if (!close || close[1] != ' ') return 0u;
    char *save = NULL;
    char *token = strtok_r(close + 2, " ", &save);
    unsigned field = 3u;
    uint64_t utime = 0u, stime = 0u;
    while (token) {
        if (field == 14u) utime = strtoull(token, NULL, 10);
        else if (field == 15u) { stime = strtoull(token, NULL, 10); break; }
        token = strtok_r(NULL, " ", &save);
        ++field;
    }
    long hz = sysconf(_SC_CLK_TCK);
    if (hz <= 0) return 0u;
    return ((utime + stime) * 1000000000ull) / (uint64_t)hz;
}

static xcb_visualtype_t *visual_for_screen(xcb_screen_t *screen) {
    xcb_depth_iterator_t di = xcb_screen_allowed_depths_iterator(screen);
    for (; di.rem; xcb_depth_next(&di)) {
        xcb_visualtype_iterator_t vi = xcb_depth_visuals_iterator(di.data);
        for (; vi.rem; xcb_visualtype_next(&vi))
            if (vi.data->visual_id == screen->root_visual) return vi.data;
    }
    return NULL;
}

static const char *state_name(uint8_t state) {
    switch (state) {
        case JX_TASK_STATE_RUNNING: return "RUNNING";
        case JX_TASK_STATE_PAUSED: return "PAUSED";
        case JX_TASK_STATE_SWAPPING: return "SWAPPING";
        case JX_TASK_STATE_STOPPED: return "STOPPED";
        case JX_TASK_STATE_ERROR: return "ERROR";
        default: return "?";
    }
}

static void draw_button(cairo_t *cr, double x, const char *label, int enabled) {
    cairo_rectangle(cr, x, TM_BUTTON_Y, TM_BUTTON_W, TM_BUTTON_H);
    cairo_set_source_rgb(cr, enabled ? 0.22 : 0.12, enabled ? 0.24 : 0.12, enabled ? 0.28 : 0.12);
    cairo_fill(cr);
    cairo_set_source_rgb(cr, enabled ? 0.95 : 0.45, enabled ? 0.95 : 0.45, enabled ? 0.95 : 0.45);
    cairo_move_to(cr, x + 14, TM_BUTTON_Y + 21);
    cairo_show_text(cr, label);
}

static void paint(jx11_task_manager *manager) {
    if (!manager->connection || manager->window == XCB_NONE || !manager->visible) return;
    xcb_visualtype_t *visual = visual_for_screen(manager->screen);
    if (!visual) return;
    cairo_surface_t *surface = cairo_xcb_surface_create(manager->connection, manager->window, visual, TM_WIDTH, TM_HEIGHT);
    cairo_t *cr = cairo_create(surface);
    cairo_set_source_rgb(cr, 0.075, 0.08, 0.095);
    cairo_paint(cr);
    cairo_select_font_face(cr, "monospace", CAIRO_FONT_SLANT_NORMAL, CAIRO_FONT_WEIGHT_NORMAL);
    cairo_set_font_size(cr, 14.0);
    cairo_set_source_rgb(cr, 0.95, 0.95, 0.97);
    cairo_move_to(cr, 20, 28);
    cairo_show_text(cr, "JX Task Manager   F10 hide   P pause   R resume   Delete stop");
    cairo_set_font_size(cr, 12.0);
    cairo_move_to(cr, 20, 50);
    cairo_show_text(cr, "TASK  PID      GEN   MODE      STATE      CPU ms      PROGRAM");

    int row = 0;
    for (size_t i = 0; i < JX_TASK_MANAGER_MAX && row < TM_ROWS; ++i) {
        const jx_task_record *task = &manager->tasks.tasks[i];
        if (!task->in_use) continue;
        double y = TM_HEADER + row * TM_ROW_H;
        if (task->task_id == manager->selected_task_id) {
            cairo_rectangle(cr, 10, y - 17, TM_WIDTH - 20, TM_ROW_H - 2);
            cairo_set_source_rgb(cr, 0.18, 0.23, 0.34);
            cairo_fill(cr);
        }
        char line[512];
        snprintf(line, sizeof line, "%-5llu %-8llu %-5llu %-9s %-10s %-11llu %.220s",
                 (unsigned long long)task->task_id,
                 (unsigned long long)task->os_process_id,
                 (unsigned long long)task->generation,
                 task->branch == JX_TASK_BRANCH_SHADOW ? "SHADOW" : "BYTECODE",
                 state_name(task->state),
                 (unsigned long long)(task->cpu_time_ns / 1000000ull),
                 task->name);
        cairo_set_source_rgb(cr, 0.9, 0.92, 0.95);
        cairo_move_to(cr, 20, y);
        cairo_show_text(cr, line);
        ++row;
    }

    const jx_task_record *selected = jx_task_manager_find_const(&manager->tasks, manager->selected_task_id);
    int live = selected && selected->state != JX_TASK_STATE_STOPPED;
    draw_button(cr, 20, "Pause", live && selected->state == JX_TASK_STATE_RUNNING);
    draw_button(cr, 148, "Resume", live && (selected->state == JX_TASK_STATE_PAUSED || selected->state == JX_TASK_STATE_ERROR));
    draw_button(cr, 276, "Stop", live);
    draw_button(cr, 404, "Rollback", 0);
    draw_button(cr, 532, "Swap", 0);

    cairo_destroy(cr);
    cairo_surface_flush(surface);
    cairo_surface_destroy(surface);
    xcb_flush(manager->connection);
}

void jx11_task_manager_init(jx11_task_manager *manager, uint64_t generation) {
    if (!manager) return;
    memset(manager, 0, sizeof *manager);
    manager->generation = generation ? generation : 1u;
    jx_task_manager_init(&manager->tasks);
    jx_task_controller_init(&manager->controller, &manager->tasks, linux_control, manager);
}

int jx11_task_manager_bind_x11(jx11_task_manager *manager,
                               xcb_connection_t *connection,
                               xcb_screen_t *screen) {
    if (!manager || !connection || !screen) return -1;
    manager->connection = connection;
    manager->screen = screen;
    manager->window = xcb_generate_id(connection);
    uint32_t mask = XCB_CW_BACK_PIXEL | XCB_CW_EVENT_MASK;
    uint32_t values[2] = {
        screen->black_pixel,
        XCB_EVENT_MASK_EXPOSURE | XCB_EVENT_MASK_BUTTON_PRESS | XCB_EVENT_MASK_KEY_PRESS | XCB_EVENT_MASK_STRUCTURE_NOTIFY
    };
    int16_t x = (int16_t)((screen->width_in_pixels > TM_WIDTH) ? (screen->width_in_pixels - TM_WIDTH) / 2u : 0u);
    int16_t y = (int16_t)((screen->height_in_pixels > TM_HEIGHT) ? (screen->height_in_pixels - TM_HEIGHT) / 2u : 0u);
    xcb_create_window(connection, XCB_COPY_FROM_PARENT, manager->window, screen->root,
                      x, y, TM_WIDTH, TM_HEIGHT, 1,
                      XCB_WINDOW_CLASS_INPUT_OUTPUT, screen->root_visual,
                      mask, values);
    const char title[] = "JX Task Manager";
    xcb_change_property(connection, XCB_PROP_MODE_REPLACE, manager->window,
                        XCB_ATOM_WM_NAME, XCB_ATOM_STRING, 8,
                        sizeof title - 1u, title);
    xcb_flush(connection);
    return 0;
}

int jx11_task_manager_register(jx11_task_manager *manager, pid_t pid, const char *program) {
    if (!manager || pid <= 0 || !program || !*program) return -1;
    size_t free_slot = JX11_TASK_PROCESS_MAX;
    for (size_t i = 0; i < JX11_TASK_PROCESS_MAX; ++i) {
        if (manager->processes[i].in_use && manager->processes[i].pid == pid) return -2;
        if (!manager->processes[i].in_use && free_slot == JX11_TASK_PROCESS_MAX) free_slot = i;
    }
    if (free_slot == JX11_TASK_PROCESS_MAX) return -3;
    uint64_t task_id = 0u;
    if (jx_task_manager_add(&manager->tasks, program, (uint64_t)pid, manager->generation,
                            JX_TASK_BRANCH_SHADOW, &task_id) != 0) return -4;
    jx11_task_process *process = &manager->processes[free_slot];
    process->in_use = 1u;
    process->pid = pid;
    process->task_id = task_id;
    process->last_cpu_ns = read_cpu_ns(pid);
    if (!manager->selected_task_id) manager->selected_task_id = task_id;
    paint(manager);
    return 0;
}

void jx11_task_manager_refresh(jx11_task_manager *manager) {
    if (!manager) return;
    uint64_t now = monotonic_ms();
    if (manager->last_refresh_ms && now - manager->last_refresh_ms < 500u) return;
    manager->last_refresh_ms = now;
    for (size_t i = 0; i < JX11_TASK_PROCESS_MAX; ++i) {
        jx11_task_process *process = &manager->processes[i];
        if (!process->in_use) continue;
        int status = 0;
        pid_t w = waitpid(process->pid, &status, WNOHANG | WUNTRACED | WCONTINUED);
        if (w == process->pid) {
            if (WIFEXITED(status) || WIFSIGNALED(status)) {
                (void)jx_task_manager_set_state(&manager->tasks, process->task_id, JX_TASK_STATE_STOPPED);
                process->in_use = 0u;
                continue;
            }
            if (WIFSTOPPED(status)) (void)jx_task_manager_set_state(&manager->tasks, process->task_id, JX_TASK_STATE_PAUSED);
            if (WIFCONTINUED(status)) (void)jx_task_manager_set_state(&manager->tasks, process->task_id, JX_TASK_STATE_RUNNING);
        }
        uint64_t cpu = read_cpu_ns(process->pid);
        if (cpu >= process->last_cpu_ns) {
            (void)jx_task_manager_account(&manager->tasks, process->task_id, 0u, cpu - process->last_cpu_ns);
            process->last_cpu_ns = cpu;
        }
    }
    paint(manager);
}

void jx11_task_manager_toggle(jx11_task_manager *manager) {
    if (!manager || !manager->connection || manager->window == XCB_NONE) return;
    manager->visible = !manager->visible;
    if (manager->visible) {
        xcb_map_window(manager->connection, manager->window);
        xcb_set_input_focus(manager->connection, XCB_INPUT_FOCUS_POINTER_ROOT,
                            manager->window, XCB_CURRENT_TIME);
        paint(manager);
    } else xcb_unmap_window(manager->connection, manager->window);
    xcb_flush(manager->connection);
}

static int button_action(int x) {
    if (x >= 20 && x < 20 + TM_BUTTON_W) return JX_TASK_ACTION_PAUSE;
    if (x >= 148 && x < 148 + TM_BUTTON_W) return JX_TASK_ACTION_RESUME;
    if (x >= 276 && x < 276 + TM_BUTTON_W) return JX_TASK_ACTION_STOP;
    return 0;
}

int jx11_task_manager_handle_event(jx11_task_manager *manager, xcb_generic_event_t *event) {
    if (!manager || !event || manager->window == XCB_NONE) return 0;
    uint8_t type = event->response_type & 0x7fu;
    xcb_window_t target = XCB_NONE;
    if (type == XCB_EXPOSE) target = ((xcb_expose_event_t *)event)->window;
    else if (type == XCB_BUTTON_PRESS) target = ((xcb_button_press_event_t *)event)->event;
    else if (type == XCB_KEY_PRESS) target = ((xcb_key_press_event_t *)event)->event;
    else if (type == XCB_DESTROY_NOTIFY) target = ((xcb_destroy_notify_event_t *)event)->window;
    if (target != manager->window) return 0;

    if (type == XCB_EXPOSE) { paint(manager); return 1; }
    if (type == XCB_DESTROY_NOTIFY) { manager->window = XCB_NONE; manager->visible = 0u; return 1; }
    if (type == XCB_BUTTON_PRESS) {
        xcb_button_press_event_t *e = (xcb_button_press_event_t *)event;
        if (e->event_y >= TM_HEADER - 18 && e->event_y < TM_HEADER - 18 + TM_ROWS * TM_ROW_H) {
            int wanted = (e->event_y - (TM_HEADER - 18)) / TM_ROW_H;
            int row = 0;
            for (size_t i = 0; i < JX_TASK_MANAGER_MAX; ++i) {
                if (!manager->tasks.tasks[i].in_use) continue;
                if (row++ == wanted) { manager->selected_task_id = manager->tasks.tasks[i].task_id; break; }
            }
            paint(manager);
        } else if (e->event_y >= TM_BUTTON_Y && e->event_y < TM_BUTTON_Y + TM_BUTTON_H) {
            int action = button_action(e->event_x);
            if (action) (void)jx_task_controller_apply(&manager->controller, manager->selected_task_id, (jx_task_action)action);
            paint(manager);
        }
        return 1;
    }
    if (type == XCB_KEY_PRESS) {
        xcb_key_press_event_t *e = (xcb_key_press_event_t *)event;
        /* Common Xorg keycodes: F10=76, P=33, R=27, Delete=119, Escape=9.
         * The window also provides mouse controls so operation does not depend
         * on a particular keyboard map. */
        if (e->detail == 9u || e->detail == 76u) jx11_task_manager_toggle(manager);
        else if (e->detail == 33u) (void)jx_task_controller_apply(&manager->controller, manager->selected_task_id, JX_TASK_ACTION_PAUSE);
        else if (e->detail == 27u) (void)jx_task_controller_apply(&manager->controller, manager->selected_task_id, JX_TASK_ACTION_RESUME);
        else if (e->detail == 119u) (void)jx_task_controller_apply(&manager->controller, manager->selected_task_id, JX_TASK_ACTION_STOP);
        paint(manager);
        return 1;
    }
    return 1;
}

void jx11_task_manager_dispose(jx11_task_manager *manager) {
    if (!manager) return;
    if (manager->connection && manager->window != XCB_NONE)
        xcb_destroy_window(manager->connection, manager->window);
    manager->window = XCB_NONE;
    manager->visible = 0u;
}
