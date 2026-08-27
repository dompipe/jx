/* JX11 X11 desktop host: jx.desktop/1
 *
 * Build: cc -O2 -Wall -Wextra -o jx11 jx11.c -lxcb
 * Run:   ./jx11 --desktop desktop.jx11
 *
 * `jx` is the language/compiler/runtime. `jx11` is the Linux/X11 desktop and
 * window-manager host. Canonical Desktop objects compile to a deliberately
 * tiny native manifest consumed here; host XIDs never enter canonical state.
 */
#include <xcb/xcb.h>
#include <stdio.h>
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <unistd.h>
#include <sys/types.h>

#define MAX_ICONS 256
#define ICON_W 96
#define ICON_H 84
#define ICON_BOX 48

typedef struct {
    char id[129];
    char label[257];
    char program[1025];
    int x, y;
    xcb_window_t window;
} jx11_icon;

static xcb_connection_t *conn;
static xcb_screen_t *screen;
static xcb_window_t root;
static xcb_gcontext_t gc;
static xcb_font_t font_id;
static uint32_t focused = XCB_NONE;
static uint32_t background_pixel = 0x181a1f;
static jx11_icon icons[MAX_ICONS];
static size_t icon_count = 0;

static void die(const char *msg) { fprintf(stderr, "jx11: %s\n", msg); exit(2); }

static void flush_or_die(const char *what) {
    if (xcb_connection_has_error(conn)) {
        fprintf(stderr, "jx11: X connection failed during %s\n", what);
        exit(2);
    }
    xcb_flush(conn);
}

static uint32_t parse_rgb(const char *s, uint32_t fallback) {
    if (!s) return fallback;
    if (*s == '#') ++s;
    if (strlen(s) != 6) return fallback;
    char *end = NULL;
    unsigned long v = strtoul(s, &end, 16);
    return (end && *end == '\0' && v <= 0xffffffUL) ? (uint32_t)v : fallback;
}

static void trim_eol(char *s) {
    size_t n = strlen(s);
    while (n && (s[n-1] == '\n' || s[n-1] == '\r')) s[--n] = '\0';
}

/* Shadow manifest:
 *   JX11/1
 *   background=#181a1f
 *   icon=id|Label|program|x|y
 *
 * The canonical compiler/host adapter owns escaping/validation. This v1 shadow
 * intentionally forbids `|`, CR and LF in manifest string fields.
 */
static int safe_field(const char *s) {
    return s && !strchr(s, '|') && !strchr(s, '\n') && !strchr(s, '\r');
}

static void load_desktop(const char *path) {
    FILE *fp = fopen(path, "r");
    if (!fp) { perror("jx11 desktop"); exit(2); }
    char line[4096];
    if (!fgets(line, sizeof line, fp)) die("empty desktop manifest");
    trim_eol(line);
    if (strcmp(line, "JX11/1") != 0) die("unsupported desktop manifest version");

    while (fgets(line, sizeof line, fp)) {
        trim_eol(line);
        if (!line[0] || line[0] == '#') continue;
        if (!strncmp(line, "background=", 11)) {
            background_pixel = parse_rgb(line + 11, background_pixel);
            continue;
        }
        if (!strncmp(line, "icon=", 5)) {
            if (icon_count >= MAX_ICONS) die("too many desktop icons");
            char *p = line + 5;
            char *parts[5] = {0};
            for (int i = 0; i < 5; ++i) {
                parts[i] = p;
                char *bar = (i < 4) ? strchr(p, '|') : NULL;
                if (i < 4) {
                    if (!bar) die("malformed icon manifest row");
                    *bar = '\0'; p = bar + 1;
                }
            }
            if (!safe_field(parts[0]) || !safe_field(parts[1]) || !safe_field(parts[2])) die("unsafe icon manifest field");
            jx11_icon *ic = &icons[icon_count++];
            snprintf(ic->id, sizeof ic->id, "%s", parts[0]);
            snprintf(ic->label, sizeof ic->label, "%s", parts[1]);
            snprintf(ic->program, sizeof ic->program, "%s", parts[2]);
            ic->x = atoi(parts[3]); ic->y = atoi(parts[4]);
            continue;
        }
        fprintf(stderr, "jx11: ignoring unknown manifest row: %s\n", line);
    }
    fclose(fp);
}

static void paint_background(void) {
    uint32_t values[] = { background_pixel };
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
    uint32_t values[7]; int i = 0;
    if (mask & XCB_CONFIG_WINDOW_X) values[i++] = (uint32_t)e->x;
    if (mask & XCB_CONFIG_WINDOW_Y) values[i++] = (uint32_t)e->y;
    if (mask & XCB_CONFIG_WINDOW_WIDTH) values[i++] = e->width;
    if (mask & XCB_CONFIG_WINDOW_HEIGHT) values[i++] = e->height;
    if (mask & XCB_CONFIG_WINDOW_BORDER_WIDTH) values[i++] = e->border_width;
    if (mask & XCB_CONFIG_WINDOW_SIBLING) values[i++] = e->sibling;
    if (mask & XCB_CONFIG_WINDOW_STACK_MODE) values[i++] = e->stack_mode;
    xcb_configure_window(conn, e->window, mask, values);
}

static pid_t launch_program(const char *program) {
    pid_t pid = fork();
    if (pid == 0) {
        setsid();
        execlp(program, program, (char *)NULL);
        perror("jx11 exec");
        _exit(127);
    }
    return pid;
}

static jx11_icon *icon_by_window(xcb_window_t win) {
    for (size_t i = 0; i < icon_count; ++i) if (icons[i].window == win) return &icons[i];
    return NULL;
}

static void draw_icon(jx11_icon *ic) {
    if (!ic || ic->window == XCB_NONE) return;
    uint32_t fg[] = { screen->white_pixel };
    xcb_change_gc(conn, gc, XCB_GC_FOREGROUND, fg);
    xcb_rectangle_t box = { (ICON_W-ICON_BOX)/2, 6, ICON_BOX, ICON_BOX };
    xcb_poly_rectangle(conn, ic->window, gc, 1, &box);
    size_t len = strlen(ic->label);
    if (len > 14) len = 14;
    int16_t tx = (int16_t)(8 + (14 - (int)len) * 2);
    xcb_image_text_8(conn, (uint8_t)len, ic->window, gc, tx, 73, ic->label);
}

static void create_icons(void) {
    font_id = xcb_generate_id(conn);
    xcb_open_font(conn, font_id, 5, "fixed");
    gc = xcb_generate_id(conn);
    uint32_t gcv[] = { screen->white_pixel, background_pixel, font_id };
    xcb_create_gc(conn, gc, root, XCB_GC_FOREGROUND | XCB_GC_BACKGROUND | XCB_GC_FONT, gcv);

    for (size_t i = 0; i < icon_count; ++i) {
        jx11_icon *ic = &icons[i];
        ic->window = xcb_generate_id(conn);
        uint32_t values[] = {
            background_pixel,
            XCB_EVENT_MASK_EXPOSURE | XCB_EVENT_MASK_BUTTON_PRESS | XCB_EVENT_MASK_BUTTON_RELEASE,
            1
        };
        xcb_create_window(conn, XCB_COPY_FROM_PARENT, ic->window, root,
            (int16_t)ic->x, (int16_t)ic->y, ICON_W, ICON_H, 0,
            XCB_WINDOW_CLASS_INPUT_OUTPUT, screen->root_visual,
            XCB_CW_BACK_PIXEL | XCB_CW_EVENT_MASK | XCB_CW_OVERRIDE_REDIRECT, values);
        xcb_map_window(conn, ic->window);
    }
}

int main(int argc, char **argv) {
    const char *launch_now = NULL;
    const char *desktop_path = NULL;
    int nested = 0;
    for (int i = 1; i < argc; ++i) {
        if (!strcmp(argv[i], "--nested")) nested = 1;
        else if (!strcmp(argv[i], "--launch") && i + 1 < argc) launch_now = argv[++i];
        else if (!strcmp(argv[i], "--desktop") && i + 1 < argc) desktop_path = argv[++i];
        else if (!strcmp(argv[i], "--help")) {
            puts("jx11 [--nested] [--desktop FILE] [--launch PROGRAM]");
            return 0;
        }
    }
    if (desktop_path) load_desktop(desktop_path);

    int screen_no = 0;
    conn = xcb_connect(NULL, &screen_no);
    if (xcb_connection_has_error(conn)) die("cannot connect to X server");
    const xcb_setup_t *setup = xcb_get_setup(conn);
    xcb_screen_iterator_t it = xcb_setup_roots_iterator(setup);
    for (int i = 0; i < screen_no; ++i) xcb_screen_next(&it);
    screen = it.data;
    if (!screen) die("X screen unavailable");
    root = screen->root;

    if (nested) fprintf(stderr, "jx11: nested mode uses the current X server root (run under Xephyr for isolation)\n");

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
        free(error); xcb_disconnect(conn); return 3;
    }

    paint_background();
    create_icons();
    flush_or_die("startup");

    if (launch_now) {
        pid_t pid = launch_program(launch_now);
        if (pid > 0) fprintf(stderr, "jx11: launched %s pid=%ld\n", launch_now, (long)pid);
    }
    fprintf(stderr, "jx11: desktop active %ux%u icons=%zu root=0x%08x\n",
            screen->width_in_pixels, screen->height_in_pixels, icon_count, root);

    for (;;) {
        xcb_generic_event_t *event = xcb_wait_for_event(conn);
        if (!event) break;
        uint8_t type = event->response_type & ~0x80;
        switch (type) {
            case XCB_MAP_REQUEST: manage_map((xcb_map_request_event_t *)event); break;
            case XCB_CONFIGURE_REQUEST: manage_configure((xcb_configure_request_event_t *)event); break;
            case XCB_EXPOSE: {
                xcb_expose_event_t *e = (xcb_expose_event_t *)event;
                jx11_icon *ic = icon_by_window(e->window);
                if (ic && e->count == 0) draw_icon(ic);
                break;
            }
            case XCB_BUTTON_PRESS: {
                xcb_button_press_event_t *e = (xcb_button_press_event_t *)event;
                jx11_icon *ic = icon_by_window(e->event);
                if (ic && e->detail == 1) {
                    pid_t pid = launch_program(ic->program);
                    if (pid > 0) fprintf(stderr, "jx11: icon %s launched %s pid=%ld\n", ic->id, ic->program, (long)pid);
                } else if (e->child != XCB_NONE) focus_window(e->child);
                break;
            }
            case XCB_DESTROY_NOTIFY: {
                xcb_destroy_notify_event_t *e = (xcb_destroy_notify_event_t *)event;
                if (focused == e->window) focused = XCB_NONE;
                break;
            }
            default: break;
        }
        free(event);
        flush_or_die("event");
    }

    if (gc) xcb_free_gc(conn, gc);
    if (font_id) xcb_close_font(conn, font_id);
    xcb_disconnect(conn);
    return 0;
}
