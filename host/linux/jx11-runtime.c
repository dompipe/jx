#define _POSIX_C_SOURCE 200809L
#include <xcb/xcb.h>
#include <errno.h>
#include <fcntl.h>
#include <poll.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <openssl/evp.h>
#include "jx11-live-patch.h"
#include "jx11-patch-service.h"
#include "jx11-task-manager.h"
#include "../common/jx-host-trace.h"
/* jx11-runtime.c is intentionally the composition translation unit for the
 * canonical JX11 core, so pull the tiny common implementations in here just
 * as the wrapper below pulls in jx11.c. */
#include "../common/jx-host-trace.c"
#include "../common/jx-task-manager.c"
#include "../common/jx-task-control.c"
#include "jx11-task-manager.c"

static jx11_live_patch patch_manager;
static jx11_patch_service patch_service;
static int patch_service_enabled = 0;
static jx_host_trace runtime_trace;
static uint64_t runtime_generation = 1u;
static jx11_task_manager runtime_tasks;
static int runtime_tasks_bound = 0;

/* The executable patch endpoint is deliberately authorized for executable
 * module replacement only. Numeric routes and a signed transport never imply
 * permission for unrelated hot tables, assets, reactions, or configuration. */
#define JX11_RUNTIME_PATCH_CAPABILITIES JX_PATCH_CAP_NATIVE_CODE

static xcb_generic_event_t *jx11_runtime_wait_for_event(xcb_connection_t *connection);

#define xcb_wait_for_event jx11_runtime_wait_for_event
#define main jx11_core_main
#include "jx11.c"
#undef main
#undef xcb_wait_for_event

static void trace_emit(uint16_t kind, uint64_t subject, uint64_t value) {
    (void)jx_host_trace_emit(&runtime_trace, kind, runtime_generation, subject, value);
}

static void trace_x_event(const xcb_generic_event_t *event) {
    if (!event) return;
    trace_emit(JX_TRACE_INPUT, (uint64_t)(event->response_type & 0x7fu), 0u);
}

static void trace_generation_if_changed(void) {
    uint64_t generation = patch_service_enabled ? patch_manager.active.generation : runtime_generation;
    if (generation == runtime_generation) return;
    runtime_generation = generation;
    runtime_tasks.generation = generation;
    trace_emit(JX_TRACE_GENERATION, 0u, generation);
}

static void patch_host_log(int level, const char *message) {
    fprintf(stderr, "jx11: patch[%d]: %s\n", level, message ? message : "");
}

static void patch_host_set_background_rgb(uint32_t rgb) {
    background_pixel = rgb & 0x00ffffffu;
    invalidate(DIRTY_ROOT);
    trace_emit(JX_TRACE_RENDER_INVALIDATE, DIRTY_ROOT, background_pixel);
}

static void patch_host_invalidate_desktop(void) {
    invalidate(DIRTY_ROOT | DIRTY_TASKBAR);
    trace_emit(JX_TRACE_RENDER_INVALIDATE, DIRTY_ROOT | DIRTY_TASKBAR, 1u);
}

static uint64_t patch_host_active_generation(void) {
    return patch_manager.active.generation;
}

static const jx11_patch_host_v1 patch_host = {
    JX11_PATCH_MODULE_ABI_VERSION,
    sizeof(jx11_patch_host_v1),
    patch_host_log,
    patch_host_set_background_rgb,
    patch_host_invalidate_desktop,
    patch_host_active_generation
};

static void patch_safe_point(void) {
    const jx11_patch_module_v1 *module = patch_manager.active.native_module;
    if (patch_service_enabled && module && module->safe_point) module->safe_point(&patch_host);
}

static int patch_filter_event(xcb_generic_event_t *event) {
    const jx11_patch_module_v1 *module = patch_manager.active.native_module;
    if (!patch_service_enabled || !module || !module->filter_x_event || !event) return 1;
    return module->filter_x_event(&patch_host, (uint8_t)(event->response_type & 0x7fu), event) != 0;
}

static xcb_generic_event_t *poll_filtered_x_event(xcb_connection_t *connection) {
    for (;;) {
        xcb_generic_event_t *event = xcb_poll_for_event(connection);
        if (!event) return NULL;
        if (patch_filter_event(event)) return event;
        free(event);
    }
}

static int hash_running_image(uint8_t out[JX_PATCH_DIGEST_BYTES]) {
    FILE *fp = fopen("/proc/self/exe", "rb");
    if (!fp) return -1;
    EVP_MD_CTX *ctx = EVP_MD_CTX_new();
    if (!ctx) { fclose(fp); return -1; }
    int ok = EVP_DigestInit_ex(ctx, EVP_sha256(), NULL) == 1;
    uint8_t buffer[32768];
    while (ok) {
        size_t n = fread(buffer, 1u, sizeof buffer, fp);
        if (n > 0u && EVP_DigestUpdate(ctx, buffer, n) != 1) ok = 0;
        if (n < sizeof buffer) {
            if (ferror(fp)) ok = 0;
            break;
        }
    }
    unsigned int digest_length = 0u;
    if (ok && (EVP_DigestFinal_ex(ctx, out, &digest_length) != 1 ||
               digest_length != JX_PATCH_DIGEST_BYTES)) ok = 0;
    EVP_MD_CTX_free(ctx);
    fclose(fp);
    return ok ? 0 : -1;
}

static int task_pid_registered(pid_t pid) {
    for (size_t i = 0; i < JX11_TASK_PROCESS_MAX; ++i)
        if (runtime_tasks.processes[i].in_use && runtime_tasks.processes[i].pid == pid) return 1;
    return 0;
}

static void task_program_name(pid_t pid, char *out, size_t capacity) {
    if (!out || capacity == 0u) return;
    out[0] = '\0';
    char path[64];
    snprintf(path, sizeof path, "/proc/%ld/comm", (long)pid);
    FILE *fp = fopen(path, "r");
    if (fp) {
        if (fgets(out, (int)capacity, fp)) {
            size_t n = strlen(out);
            while (n && (out[n - 1u] == '\n' || out[n - 1u] == '\r')) out[--n] = '\0';
        }
        fclose(fp);
    }
    if (!out[0]) snprintf(out, capacity, "pid-%ld", (long)pid);
}

static void task_discover_children(void) {
    char path[96];
    pid_t self = getpid();
    snprintf(path, sizeof path, "/proc/%ld/task/%ld/children", (long)self, (long)self);
    FILE *fp = fopen(path, "r");
    if (!fp) return;
    long raw_pid = 0;
    while (fscanf(fp, "%ld", &raw_pid) == 1) {
        if (raw_pid <= 0) continue;
        pid_t pid = (pid_t)raw_pid;
        if (task_pid_registered(pid)) continue;
        char program[JX_TASK_NAME_MAX + 1u];
        task_program_name(pid, program, sizeof program);
        if (jx11_task_manager_register(&runtime_tasks, pid, program) == 0)
            fprintf(stderr, "jx11: task manager registered %s pid=%ld\n", program, (long)pid);
    }
    fclose(fp);
}

static void task_bind_if_ready(void) {
    if (runtime_tasks_bound || !connection || !screen) return;
}

static void task_bind_runtime(xcb_connection_t *connection) {
    if (runtime_tasks_bound || !connection || !screen) return;
    if (jx11_task_manager_bind_x11(&runtime_tasks, connection, screen) != 0) return;
    /* F10 is keycode 76 on the standard Xorg evdev map. The Task Manager also
     * has mouse controls, and its own window accepts the same key once open. */
    xcb_grab_key(connection, 1, screen->root, XCB_MOD_MASK_ANY, 76u,
                 XCB_GRAB_MODE_ASYNC, XCB_GRAB_MODE_ASYNC);
    xcb_flush(connection);
    runtime_tasks_bound = 1;
    fprintf(stderr, "jx11: live Task Manager attached (F10)\n");
}

static int task_consume_event(xcb_generic_event_t *event) {
    if (!event) return 0;
    uint8_t type = event->response_type & 0x7fu;
    if (type == XCB_KEY_PRESS) {
        xcb_key_press_event_t *key = (xcb_key_press_event_t *)event;
        if (key->detail == 76u && key->event != runtime_tasks.window) {
            jx11_task_manager_toggle(&runtime_tasks);
            return 1;
        }
    }
    return jx11_task_manager_handle_event(&runtime_tasks, event);
}

static void task_refresh_runtime(void) {
    task_discover_children();
    jx11_task_manager_refresh(&runtime_tasks);
}

static xcb_generic_event_t *next_runtime_event(xcb_connection_t *connection) {
    for (;;) {
        xcb_generic_event_t *event = poll_filtered_x_event(connection);
        if (!event) return NULL;
        if (task_consume_event(event)) {
            free(event);
            continue;
        }
        trace_x_event(event);
        return event;
    }
}

static xcb_generic_event_t *jx11_runtime_wait_for_event(xcb_connection_t *connection) {
    patch_safe_point();
    task_bind_runtime(connection);
    task_refresh_runtime();

    for (;;) {
        xcb_generic_event_t *event = next_runtime_event(connection);
        if (event) return event;
        if (xcb_connection_has_error(connection)) return NULL;

        struct pollfd fds[2];
        nfds_t count = 1u;
        fds[0].fd = xcb_get_file_descriptor(connection);
        fds[0].events = POLLIN;
        fds[0].revents = 0;
        if (patch_service_enabled) {
            fds[1].fd = jx11_patch_service_fd(&patch_service);
            fds[1].events = POLLIN;
            fds[1].revents = 0;
            count = 2u;
        }

        int rc = poll(fds, count, 500);
        if (rc < 0 && errno == EINTR) continue;
        if (rc < 0) return NULL;

        task_refresh_runtime();
        patch_safe_point();

        if (patch_service_enabled && (fds[1].revents & POLLIN) != 0) {
            int prc = jx11_patch_service_process_one(&patch_service);
            if (prc < 0) fprintf(stderr, "jx11: patch service transaction failed (%d)\n", prc);
            trace_generation_if_changed();
            patch_safe_point();
        }
        if (rc == 0) continue;
        if ((fds[0].revents & (POLLIN | POLLERR | POLLHUP)) != 0) {
            event = next_runtime_event(connection);
            if (event) return event;
            if (xcb_connection_has_error(connection)) return NULL;
        }
    }
}

static void print_runtime_help(void) {
    puts("jx11 [--nested] [--desktop FILE] [--launch PROGRAM] [--patch-socket PATH --patch-pubkey PEM]");
    puts("  F10                   open/close the live JX Task Manager");
    puts("  --patch-socket PATH   enable local JXP1 executable live-patch service");
    puts("  --patch-pubkey PEM    Ed25519 public key used to authorize signed native-code patches");
    puts("  patch capability      native-code only; other capabilities are not pre-authorized");
}

int main(int argc, char **argv) {
    const char *patch_socket = NULL;
    const char *patch_pubkey = NULL;
    jx_host_trace_init(&runtime_trace, JX_HOST_LINUX_X11);
    jx11_task_manager_init(&runtime_tasks, runtime_generation);
    trace_emit(JX_TRACE_PROGRAM_START, 0u, 0u);

    char **core_argv = calloc((size_t)argc + 1u, sizeof *core_argv);
    if (!core_argv) return 70;
    int core_argc = 0;
    core_argv[core_argc++] = argv[0];

    for (int i = 1; i < argc; ++i) {
        if (!strcmp(argv[i], "--patch-socket")) {
            if (++i >= argc) { fputs("jx11: --patch-socket requires a path\n", stderr); free(core_argv); return 64; }
            patch_socket = argv[i];
        } else if (!strcmp(argv[i], "--patch-pubkey")) {
            if (++i >= argc) { fputs("jx11: --patch-pubkey requires a PEM file\n", stderr); free(core_argv); return 64; }
            patch_pubkey = argv[i];
        } else if (!strcmp(argv[i], "--help")) {
            print_runtime_help();
            free(core_argv);
            return 0;
        } else {
            core_argv[core_argc++] = argv[i];
        }
    }

    if ((patch_socket == NULL) != (patch_pubkey == NULL)) {
        fputs("jx11: --patch-socket and --patch-pubkey must be supplied together\n", stderr);
        free(core_argv);
        return 64;
    }

    if (patch_socket) {
        uint8_t base_digest[JX_PATCH_DIGEST_BYTES];
        if (hash_running_image(base_digest) != 0) {
            fputs("jx11: cannot hash running image for live-patch base identity\n", stderr);
            free(core_argv);
            return 78;
        }
        jx11_live_patch_init(&patch_manager, 1u, base_digest, JX11_RUNTIME_PATCH_CAPABILITIES);
        int rc = jx11_patch_service_open(&patch_service, patch_socket, patch_pubkey, &patch_manager);
        if (rc != 0) {
            fprintf(stderr, "jx11: cannot open patch service (%d)\n", rc);
            free(core_argv);
            return 78;
        }
        jx11_patch_service_set_host(&patch_service, &patch_host);
        patch_service_enabled = 1;
        fprintf(stderr, "jx11: signed native-code live patch service active socket=%s generation=1\n", patch_socket);
    }

    int rc = jx11_core_main(core_argc, core_argv);
    jx11_task_manager_dispose(&runtime_tasks);
    if (patch_service_enabled) {
        jx11_patch_service_close(&patch_service);
        patch_service_enabled = 0;
    }
    trace_emit(JX_TRACE_PROGRAM_STOP, 0u, (uint64_t)(unsigned int)rc);
    free(core_argv);
    return rc;
}
