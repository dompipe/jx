/* JX11 X11 desktop host prototype: jx.desktop/1
 *
 * Build (Debian/Ubuntu): cc -O2 -Wall -Wextra -o jx11 jx11.c -lxcb
 *
 * JX11 is the Linux/X11 desktop and window-manager host. `jx` remains the
 * compiler/runtime name. This host proves display ownership, input/event
 * capture, a painted root background, child launch, and basic top-level window
 * management while canonical Desktop/Icon/Shortcut state stays host-neutral.
 */
#include <xcb/xcb.h>
#include <stdio.h>
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <unistd.h>
#include <sys/types.h>

static xcb_connection_t *conn;
static xcb_screen_t *screen;
static xcb_window_t root;
static uint32_t focused = XCB_NONE;

static void flush_or_die(const char *what) {
    if (xcb_connection_has_error(conn)) {
        fprintf(stderr, "jx11: X connection failed during %s\n", what);
        exit(2);
    }
    xcb_flush(conn);
}

static void paint_background(uint32_t pixel) {
    uint32_t values[] = { pixel };
    xcb_change_window_attributes(conn, root, XCB_CW_BACK_PIXEL, values);
    xcb_clear_area(conn, 0, root, 0, 0, 0, 0);
}

static void focus_window(xcb_window_t win) {
    if (win == XCB_NONE || win == root) return;
    focused = win;
    xcb_set_input_focus(conn, XCB_INPUT_FOCUS_POINTER_ROOT, win, XCB_CURRENT_TIME);
    uint32_t stack[] = { XCB_STACK_MODE_ABOVE };
    xcb_configure_window(conn, win, XCB_CONFIG_WINDOW_STACK_MODE, stack);
}

static void manage_map(xcb_map_request_event_t *e) {
    xcb_map_window(conn, e->window);
    focus_window(e->window);
}

static void manage_configure(xcb_configure_request_event_t *e) {
    uint16_t mask = e->value_mask;
    uint32_t values[7];
    int i = 0;
    if (mask & XCB_CONFIG_WINDOW_X) values[i++] = (uint32_t)e->x;
    if (mask & XCB_CONFIG_WINDOW_Y) values[i++] = (uint32_t)e->y;
    if (mask & XCB_CONFIG_WINDOW_WIDTH) values[i++] = e->width;
    if (mask & XCB_CONFIG_WINDOW_HEIGHT) values[i++] = e->height;
    if (mask & XCB_CONFIG_WINDOW_BORDER_WIDTH) values[i++] = e->border_width;
    if (mask & XCB_CONFIG_WINDOW_SIBLING) values[i++] = e->sibling;
    if (mask & XCB_CONFIG_WINDOW_STACK_MODE) values[i++] = e->stack_mode;
    xcb_configure_window(conn, e->window, mask, values);
}

static pid_t launch(const char *program) {
    pid_t pid = fork();
    if (pid == 0) {
        setsid();
        execlp(program, program, (char *)NULL);
        perror("jx11 exec");
        _exit(127);
    }
    return pid;
}

int main(int argc, char **argv) {
    const char *launch_program = NULL;
    int nested = 0;
    for (int i = 1; i < argc; ++i) {
        if (!strcmp(argv[i], "--nested")) nested = 1;
        else if (!strcmp(argv[i], "--launch") && i + 1 < argc) launch_program = argv[++i];
    }

    int screen_no = 0;
    conn = xcb_connect(NULL, &screen_no);
    if (xcb_connection_has_error(conn)) {
        fprintf(stderr, "jx11: cannot connect to X server\n");
        return 2;
    }

    const xcb_setup_t *setup = xcb_get_setup(conn);
    xcb_screen_iterator_t it = xcb_setup_roots_iterator(setup);
    for (int i = 0; i < screen_no; ++i) xcb_screen_next(&it);
    screen = it.data;
    if (!screen) return 2;
    root = screen->root;

    if (nested) {
        fprintf(stderr, "jx11: --nested requires a nested X server such as Xephyr; using its root window\n");
    }

    uint32_t event_mask = XCB_EVENT_MASK_SUBSTRUCTURE_REDIRECT |
                          XCB_EVENT_MASK_SUBSTRUCTURE_NOTIFY |
                          XCB_EVENT_MASK_BUTTON_PRESS |
                          XCB_EVENT_MASK_BUTTON_RELEASE |
                          XCB_EVENT_MASK_POINTER_MOTION |
                          XCB_EVENT_MASK_KEY_PRESS |
                          XCB_EVENT_MASK_KEY_RELEASE |
                          XCB_EVENT_MASK_PROPERTY_CHANGE |
                          XCB_EVENT_MASK_STRUCTURE_NOTIFY;
    xcb_void_cookie_t own = xcb_change_window_attributes_checked(conn, root, XCB_CW_EVENT_MASK, &event_mask);
    xcb_generic_error_t *error = xcb_request_check(conn, own);
    if (error) {
        fprintf(stderr, "jx11: another window manager owns this display (X error %u)\n", error->error_code);
        free(error);
        xcb_disconnect(conn);
        return 3;
    }

    paint_background(screen->black_pixel);
    flush_or_die("startup");

    if (launch_program) {
        pid_t pid = launch(launch_program);
        if (pid > 0) fprintf(stderr, "jx11: launched %s pid=%ld\n", launch_program, (long)pid);
    }

    fprintf(stderr, "jx11: desktop active %ux%u root=0x%08x\n",
            screen->width_in_pixels, screen->height_in_pixels, root);

    for (;;) {
        xcb_generic_event_t *event = xcb_wait_for_event(conn);
        if (!event) break;
        uint8_t type = event->response_type & ~0x80;
        switch (type) {
            case XCB_MAP_REQUEST:
                manage_map((xcb_map_request_event_t *)event);
                break;
            case XCB_CONFIGURE_REQUEST:
                manage_configure((xcb_configure_request_event_t *)event);
                break;
            case XCB_BUTTON_PRESS: {
                xcb_button_press_event_t *e = (xcb_button_press_event_t *)event;
                if (e->child != XCB_NONE) focus_window(e->child);
                break;
            }
            case XCB_DESTROY_NOTIFY: {
                xcb_destroy_notify_event_t *e = (xcb_destroy_notify_event_t *)event;
                if (focused == e->window) focused = XCB_NONE;
                break;
            }
            default:
                break;
        }
        free(event);
        flush_or_die("event");
    }

    xcb_disconnect(conn);
    return 0;
}
