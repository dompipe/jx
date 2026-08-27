/* JX11 X11 desktop host: jx.desktop/1
 *
 * Build (Debian/Ubuntu):
 *   cc -O2 -Wall -Wextra -o jx11 jx11.c $(pkg-config --cflags --libs xcb cairo-xcb)
 * Run:
 *   ./jx11 --desktop desktop.jx11
 *
 * Hot-path law:
 *   canonical names/assets -> startup prelink/cache -> numeric native slots.
 * Event handling never resolves canonical names and never decodes images.
 */
#include <xcb/xcb.h>
#include <cairo/cairo.h>
#include <cairo/cairo-xcb.h>
#include <stdio.h>
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <unistd.h>
#include <sys/types.h>

#define MAX_ICONS 256
#define MAX_WINDOWS 256
#define XID_INDEX_SIZE 1024
#define ICON_W 104
#define ICON_H 94
#define ICON_IMAGE 56
#define MAX_TITLE 160
#define MAX_TASK_BUTTONS 256

#define DIRTY_NONE    0u
#define DIRTY_TASKBAR 1u
#define DIRTY_ROOT    2u

typedef struct {
    char id[129];
    char label[257];
    char image_path[2049];
    char program[1025];
    int x, y;
    xcb_window_t window;
    cairo_surface_t *image;
    int in_use;
} jx11_icon;

typedef struct {
    xcb_window_t window;
    char title[MAX_TITLE + 1];
    int16_t x, y;
    uint16_t width, height;
    int mapped;
    int in_use;
} jx11_window;

typedef struct {
    xcb_window_t xid;
    int16_t slot;
    uint8_t kind; /* 1 icon, 2 managed window */
    uint8_t used;
} xid_index_entry;

typedef struct {
    int16_t window_slot;
    int16_t x;
    int16_t width;
} task_button;

static xcb_connection_t *conn;
static xcb_screen_t *screen;
static xcb_window_t root;
static xcb_visualtype_t *root_visual;
static xcb_window_t taskbar_window = XCB_NONE;
static uint32_t focused = XCB_NONE;
static uint32_t background_pixel = 0x181a1f;
static char wallpaper_path[2049] = {0};
static char window_bag[129] = {0};
static int taskbar_enabled = 1;
static int taskbar_height = 34;
static jx11_icon icons[MAX_ICONS];
static size_t icon_count = 0;
static jx11_window windows[MAX_WINDOWS];
static size_t window_count = 0;
static xid_index_entry xid_index[XID_INDEX_SIZE];
static task_button task_buttons[MAX_TASK_BUTTONS];
static size_t task_button_count = 0;
static cairo_surface_t *wallpaper_image = NULL;
static unsigned dirty_flags = DIRTY_NONE;

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

static void rgb_double(uint32_t rgb, double *r, double *g, double *b) {
    *r = ((rgb >> 16) & 255) / 255.0;
    *g = ((rgb >> 8) & 255) / 255.0;
    *b = (rgb & 255) / 255.0;
}

static void trim_eol(char *s) {
    size_t n = strlen(s);
    while (n && (s[n-1] == '\n' || s[n-1] == '\r')) s[--n] = '\0';
}

static int safe_field(const char *s) {
    return s && !strchr(s, '|') && !strchr(s, '\n') && !strchr(s, '\r');
}

static void load_desktop(const char *path) {
    FILE *fp = fopen(path, "r");
    if (!fp) { perror("jx11 desktop"); exit(2); }
    char line[8192];
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
        if (!strncmp(line, "wallpaper=", 10)) {
            snprintf(wallpaper_path, sizeof wallpaper_path, "%s", line + 10);
            continue;
        }
        if (!strncmp(line, "taskbar=", 8)) {
            taskbar_enabled = atoi(line + 8) != 0;
            continue;
        }
        if (!strncmp(line, "taskbar-height=", 15)) {
            taskbar_height = atoi(line + 15);
            if (taskbar_height < 24) taskbar_height = 24;
            if (taskbar_height > 96) taskbar_height = 96;
            continue;
        }
        if (!strncmp(line, "window-bag=", 11)) {
            snprintf(window_bag, sizeof window_bag, "%s", line + 11);
            continue;
        }
        if (!strncmp(line, "icon=", 5)) {
            if (icon_count >= MAX_ICONS) die("too many desktop icons");
            char *p = line + 5;
            char *parts[6] = {0};
            for (int i = 0; i < 6; ++i) {
                parts[i] = p;
                char *bar = (i < 5) ? strchr(p, '|') : NULL;
                if (i < 5) {
                    if (!bar) die("malformed icon manifest row");
                    *bar = '\0'; p = bar + 1;
                }
            }
            for (int i = 0; i < 4; ++i) if (!safe_field(parts[i])) die("unsafe icon manifest field");
            jx11_icon *ic = &icons[icon_count];
            memset(ic, 0, sizeof *ic);
            ic->in_use = 1;
            snprintf(ic->id, sizeof ic->id, "%s", parts[0]);
            snprintf(ic->label, sizeof ic->label, "%s", parts[1]);
            snprintf(ic->image_path, sizeof ic->image_path, "%s", parts[2]);
            snprintf(ic->program, sizeof ic->program, "%s", parts[3]);
            ic->x = atoi(parts[4]); ic->y = atoi(parts[5]);
            ++icon_count;
            continue;
        }
        fprintf(stderr, "jx11: ignoring unknown manifest row: %s\n", line);
    }
    fclose(fp);
}

static xcb_visualtype_t *find_visual(xcb_visualid_t id) {
    xcb_depth_iterator_t di = xcb_screen_allowed_depths_iterator(screen);
    for (; di.rem; xcb_depth_next(&di)) {
        xcb_visualtype_iterator_t vi = xcb_depth_visuals_iterator(di.data);
        for (; vi.rem; xcb_visualtype_next(&vi)) if (vi.data->visual_id == id) return vi.data;
    }
    return NULL;
}

static cairo_surface_t *xcb_surface(xcb_drawable_t drawable, int width, int height) {
    return cairo_xcb_surface_create(conn, drawable, root_visual, width, height);
}

static cairo_surface_t *load_png_once(const char *path, int noisy) {
    if (!path || !*path) return NULL;
    cairo_surface_t *img = cairo_image_surface_create_from_png(path);
    if (cairo_surface_status(img) != CAIRO_STATUS_SUCCESS) {
        if (noisy) fprintf(stderr, "jx11: cannot load PNG asset %s: %s\n", path,
                           cairo_status_to_string(cairo_surface_status(img)));
        cairo_surface_destroy(img);
        return NULL;
    }
    return img;
}

static void paint_surface_cover(cairo_t *cr, cairo_surface_t *img, double width, double height) {
    if (!img) return;
    double iw = cairo_image_surface_get_width(img), ih = cairo_image_surface_get_height(img);
    if (iw <= 0 || ih <= 0) return;
    double scale = width / iw;
    if (ih * scale < height) scale = height / ih;
    double dw = iw * scale, dh = ih * scale;
    cairo_save(cr);
    cairo_translate(cr, (width - dw) / 2.0, (height - dh) / 2.0);
    cairo_scale(cr, scale, scale);
    cairo_set_source_surface(cr, img, 0, 0);
    cairo_paint(cr);
    cairo_restore(cr);
}

static void paint_surface_fit(cairo_t *cr, cairo_surface_t *img,
                              double x, double y, double width, double height) {
    if (!img) return;
    double iw = cairo_image_surface_get_width(img), ih = cairo_image_surface_get_height(img);
    if (iw <= 0 || ih <= 0) return;
    double scale = width / iw;
    if (ih * scale > height) scale = height / ih;
    double dw = iw * scale, dh = ih * scale;
    cairo_save(cr);
    cairo_translate(cr, x + (width - dw) / 2.0, y + (height - dh) / 2.0);
    cairo_scale(cr, scale, scale);
    cairo_set_source_surface(cr, img, 0, 0);
    cairo_paint(cr);
    cairo_restore(cr);
}

static uint32_t xid_hash(xcb_window_t xid) {
    uint32_t x = xid;
    x ^= x >> 16;
    x *= 0x7feb352dU;
    x ^= x >> 15;
    x *= 0x846ca68bU;
    x ^= x >> 16;
    return x & (XID_INDEX_SIZE - 1u);
}

static void xid_index_clear(void) { memset(xid_index, 0, sizeof xid_index); }

static void xid_index_put(xcb_window_t xid, uint8_t kind, int16_t slot) {
    if (xid == XCB_NONE) return;
    uint32_t at = xid_hash(xid);
    for (uint32_t probe = 0; probe < XID_INDEX_SIZE; ++probe) {
        xid_index_entry *e = &xid_index[(at + probe) & (XID_INDEX_SIZE - 1u)];
        if (!e->used || e->xid == xid) {
            e->used = 1; e->xid = xid; e->kind = kind; e->slot = slot;
            return;
        }
    }
    die("XID index exhausted");
}

static int xid_index_get(xcb_window_t xid, uint8_t kind) {
    if (xid == XCB_NONE) return -1;
    uint32_t at = xid_hash(xid);
    for (uint32_t probe = 0; probe < XID_INDEX_SIZE; ++probe) {
        xid_index_entry *e = &xid_index[(at + probe) & (XID_INDEX_SIZE - 1u)];
        if (!e->used) return -1;
        if (e->xid == xid && e->kind == kind) return e->slot;
    }
    return -1;
}

/* Deletion is uncommon and the table is tiny (<=512 live XIDs). Rebuild keeps
 * lookup probing simple and avoids tombstones in the event hot path. */
static void xid_index_rebuild(void) {
    xid_index_clear();
    for (size_t i = 0; i < icon_count; ++i)
        if (icons[i].in_use && icons[i].window != XCB_NONE) xid_index_put(icons[i].window, 1, (int16_t)i);
    for (int i = 0; i < MAX_WINDOWS; ++i)
        if (windows[i].in_use && windows[i].window != XCB_NONE) xid_index_put(windows[i].window, 2, (int16_t)i);
}

static int window_slot_by_xid(xcb_window_t win) { return xid_index_get(win, 2); }
static int icon_slot_by_xid(xcb_window_t win) { return xid_index_get(win, 1); }

static void invalidate(unsigned flags) { dirty_flags |= flags; }

static void paint_background(void) {
    uint32_t values[] = { background_pixel };
    xcb_change_window_attributes(conn, root, XCB_CW_BACK_PIXEL, values);
    xcb_clear_area(conn, 0, root, 0, 0, 0, 0);
    if (!wallpaper_image) return;
    cairo_surface_t *surface = xcb_surface(root, screen->width_in_pixels, screen->height_in_pixels);
    cairo_t *cr = cairo_create(surface);
    paint_surface_cover(cr, wallpaper_image, screen->width_in_pixels, screen->height_in_pixels);
    cairo_destroy(cr);
    cairo_surface_flush(surface);
    cairo_surface_destroy(surface);
}

static void read_title(int slot) {
    if (slot < 0 || slot >= MAX_WINDOWS || !windows[slot].in_use) return;
    jx11_window *mw = &windows[slot];
    char previous[MAX_TITLE + 1];
    memcpy(previous, mw->title, sizeof previous);
    xcb_get_property_cookie_t ck = xcb_get_property(conn, 0, mw->window,
        XCB_ATOM_WM_NAME, XCB_ATOM_STRING, 0, MAX_TITLE);
    xcb_get_property_reply_t *rp = xcb_get_property_reply(conn, ck, NULL);
    if (!rp) return;
    int n = xcb_get_property_value_length(rp);
    if (n > MAX_TITLE) n = MAX_TITLE;
    if (n > 0) {
        memcpy(mw->title, xcb_get_property_value(rp), (size_t)n);
        mw->title[n] = '\0';
    }
    free(rp);
    if (strcmp(previous, mw->title) != 0) invalidate(DIRTY_TASKBAR);
}

static void read_geometry(int slot) {
    if (slot < 0 || slot >= MAX_WINDOWS || !windows[slot].in_use) return;
    jx11_window *mw = &windows[slot];
    xcb_get_geometry_reply_t *g = xcb_get_geometry_reply(conn, xcb_get_geometry(conn, mw->window), NULL);
    if (!g) return;
    mw->x = g->x; mw->y = g->y; mw->width = g->width; mw->height = g->height;
    free(g);
}

static int allocate_window_slot(void) {
    for (int i = 0; i < MAX_WINDOWS; ++i) if (!windows[i].in_use) return i;
    return -1;
}

static void add_managed(xcb_window_t win) {
    if (win == XCB_NONE || win == root || win == taskbar_window || window_slot_by_xid(win) >= 0) return;
    if (icon_slot_by_xid(win) >= 0) return;
    int slot = allocate_window_slot();
    if (slot < 0) return;
    jx11_window *mw = &windows[slot];
    memset(mw, 0, sizeof *mw);
    mw->window = win; mw->mapped = 1; mw->in_use = 1;
    snprintf(mw->title, sizeof mw->title, "0x%08x", win);
    xid_index_put(win, 2, (int16_t)slot);
    ++window_count;
    uint32_t ev = XCB_EVENT_MASK_PROPERTY_CHANGE | XCB_EVENT_MASK_STRUCTURE_NOTIFY | XCB_EVENT_MASK_FOCUS_CHANGE;
    xcb_change_window_attributes(conn, win, XCB_CW_EVENT_MASK, &ev);
    read_geometry(slot); read_title(slot);
    invalidate(DIRTY_TASKBAR);
}

static void remove_managed(xcb_window_t win) {
    int slot = window_slot_by_xid(win);
    if (slot < 0) return;
    memset(&windows[slot], 0, sizeof windows[slot]);
    if (window_count) --window_count;
    if (focused == win) focused = XCB_NONE;
    xid_index_rebuild();
    invalidate(DIRTY_TASKBAR);
}

static void focus_window(xcb_window_t win) {
    if (win == XCB_NONE || win == root || win == taskbar_window) return;
    if (window_slot_by_xid(win) < 0) return;
    if (focused != win) {
        focused = win;
        invalidate(DIRTY_TASKBAR);
    }
    xcb_set_input_focus(conn, XCB_INPUT_FOCUS_POINTER_ROOT, win, XCB_CURRENT_TIME);
    uint32_t stack[] = { XCB_STACK_MODE_ABOVE };
    xcb_configure_window(conn, win, XCB_CONFIG_WINDOW_STACK_MODE, stack);
}

static void manage_map(xcb_map_request_event_t *e) {
    xcb_map_window(conn, e->window);
    add_managed(e->window);
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
    int slot = window_slot_by_xid(e->window);
    if (slot >= 0) read_geometry(slot);
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

static void draw_icon(int slot) {
    if (slot < 0 || slot >= (int)icon_count || !icons[slot].in_use) return;
    jx11_icon *ic = &icons[slot];
    if (ic->window == XCB_NONE) return;
    cairo_surface_t *surface = xcb_surface(ic->window, ICON_W, ICON_H);
    cairo_t *cr = cairo_create(surface);
    double r,g,b; rgb_double(background_pixel, &r,&g,&b);
    cairo_set_source_rgba(cr, r,g,b, 0.86); cairo_paint(cr);

    if (ic->image) paint_surface_fit(cr, ic->image, (ICON_W-ICON_IMAGE)/2.0, 5, ICON_IMAGE, ICON_IMAGE);
    else {
        cairo_set_source_rgba(cr, 1,1,1,0.7); cairo_set_line_width(cr, 2);
        cairo_rectangle(cr, (ICON_W-ICON_IMAGE)/2.0, 5, ICON_IMAGE, ICON_IMAGE); cairo_stroke(cr);
    }

    cairo_select_font_face(cr, "sans", CAIRO_FONT_SLANT_NORMAL, CAIRO_FONT_WEIGHT_NORMAL);
    cairo_set_font_size(cr, 12); cairo_set_source_rgb(cr, 1,1,1);
    cairo_text_extents_t ex; cairo_text_extents(cr, ic->label, &ex);
    double tx = (ICON_W - ex.width) / 2.0 - ex.x_bearing;
    if (tx < 3) tx = 3;
    cairo_move_to(cr, tx, ICON_H - 10); cairo_show_text(cr, ic->label);
    cairo_destroy(cr); cairo_surface_flush(surface); cairo_surface_destroy(surface);
}

static void create_icons(void) {
    for (size_t i = 0; i < icon_count; ++i) {
        jx11_icon *ic = &icons[i];
        if (ic->image_path[0]) ic->image = load_png_once(ic->image_path, 0);
        ic->window = xcb_generate_id(conn);
        uint32_t values[] = { background_pixel,
            XCB_EVENT_MASK_EXPOSURE | XCB_EVENT_MASK_BUTTON_PRESS | XCB_EVENT_MASK_BUTTON_RELEASE, 1 };
        xcb_create_window(conn, XCB_COPY_FROM_PARENT, ic->window, root,
            (int16_t)ic->x, (int16_t)ic->y, ICON_W, ICON_H, 0,
            XCB_WINDOW_CLASS_INPUT_OUTPUT, screen->root_visual,
            XCB_CW_BACK_PIXEL | XCB_CW_EVENT_MASK | XCB_CW_OVERRIDE_REDIRECT, values);
        xid_index_put(ic->window, 1, (int16_t)i);
        xcb_map_window(conn, ic->window);
    }
}

static void draw_taskbar(void) {
    if (!taskbar_enabled || taskbar_window == XCB_NONE) return;
    int width = screen->width_in_pixels;
    cairo_surface_t *surface = xcb_surface(taskbar_window, width, taskbar_height);
    cairo_t *cr = cairo_create(surface);
    cairo_set_source_rgba(cr, 0.04,0.05,0.07,0.94); cairo_paint(cr);
    cairo_select_font_face(cr, "sans", CAIRO_FONT_SLANT_NORMAL, CAIRO_FONT_WEIGHT_NORMAL);
    cairo_set_font_size(cr, 12);

    task_button_count = 0;
    int left = 8;
    int usable = width - 16;
    int slot_width = window_count ? usable / (int)window_count : usable;
    if (slot_width > 220) slot_width = 220;
    if (slot_width < 80) slot_width = 80;

    int ordinal = 0;
    for (int i = 0; i < MAX_WINDOWS; ++i) {
        if (!windows[i].in_use) continue;
        int x = left + ordinal * slot_width;
        ++ordinal;
        if (x >= width - 8) break;
        int w = slot_width - 5;
        if (x + w > width - 8) w = width - 8 - x;
        if (task_button_count < MAX_TASK_BUTTONS) {
            task_buttons[task_button_count].window_slot = (int16_t)i;
            task_buttons[task_button_count].x = (int16_t)x;
            task_buttons[task_button_count].width = (int16_t)w;
            ++task_button_count;
        }
        if (windows[i].window == focused) cairo_set_source_rgba(cr, 0.22,0.42,0.72,0.95);
        else cairo_set_source_rgba(cr, 0.18,0.19,0.22,0.92);
        cairo_rectangle(cr, x, 4, w, taskbar_height - 8); cairo_fill(cr);
        cairo_set_source_rgb(cr, 1,1,1);
        char title[64]; snprintf(title, sizeof title, "%.56s", windows[i].title[0] ? windows[i].title : "Window");
        cairo_move_to(cr, x + 8, taskbar_height / 2 + 5); cairo_show_text(cr, title);
    }
    cairo_destroy(cr); cairo_surface_flush(surface); cairo_surface_destroy(surface);
}

static void create_taskbar(void) {
    if (!taskbar_enabled) return;
    taskbar_window = xcb_generate_id(conn);
    uint32_t values[] = { 0x101217,
        XCB_EVENT_MASK_EXPOSURE | XCB_EVENT_MASK_BUTTON_PRESS | XCB_EVENT_MASK_STRUCTURE_NOTIFY, 1 };
    xcb_create_window(conn, XCB_COPY_FROM_PARENT, taskbar_window, root,
        0, (int16_t)(screen->height_in_pixels - taskbar_height),
        screen->width_in_pixels, (uint16_t)taskbar_height, 0,
        XCB_WINDOW_CLASS_INPUT_OUTPUT, screen->root_visual,
        XCB_CW_BACK_PIXEL | XCB_CW_EVENT_MASK | XCB_CW_OVERRIDE_REDIRECT, values);
    xcb_map_window(conn, taskbar_window);
}

static void taskbar_click(int x) {
    for (size_t i = 0; i < task_button_count; ++i) {
        task_button *b = &task_buttons[i];
        if (x < b->x || x >= b->x + b->width) continue;
        int slot = b->window_slot;
        if (slot >= 0 && slot < MAX_WINDOWS && windows[slot].in_use) focus_window(windows[slot].window);
        return;
    }
}

static void render_dirty(void) {
    unsigned flags = dirty_flags;
    dirty_flags = DIRTY_NONE;
    if (flags & DIRTY_ROOT) paint_background();
    if (flags & DIRTY_TASKBAR) draw_taskbar();
}

static void destroy_assets(void) {
    if (wallpaper_image) { cairo_surface_destroy(wallpaper_image); wallpaper_image = NULL; }
    for (size_t i = 0; i < icon_count; ++i) {
        if (icons[i].image) { cairo_surface_destroy(icons[i].image); icons[i].image = NULL; }
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
    root_visual = find_visual(screen->root_visual);
    if (!root_visual) die("root X visual unavailable");

    if (nested) fprintf(stderr, "jx11: nested mode uses current X root (run under Xephyr for isolation)\n");

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

    xid_index_clear();
    if (wallpaper_path[0]) wallpaper_image = load_png_once(wallpaper_path, 1);
    create_icons();
    create_taskbar();
    invalidate(DIRTY_ROOT | DIRTY_TASKBAR);
    render_dirty();
    flush_or_die("startup");

    if (launch_now) {
        pid_t pid = launch_program(launch_now);
        if (pid > 0) fprintf(stderr, "jx11: launched %s pid=%ld\n", launch_now, (long)pid);
    }
    fprintf(stderr, "jx11: desktop active %ux%u icons=%zu taskbar=%s window-bag=%s root=0x%08x\n",
            screen->width_in_pixels, screen->height_in_pixels, icon_count,
            taskbar_enabled ? "on" : "off", window_bag[0] ? window_bag : "-", root);

    for (;;) {
        xcb_generic_event_t *event = xcb_wait_for_event(conn);
        if (!event) break;
        uint8_t type = event->response_type & ~0x80;
        switch (type) {
            case XCB_MAP_REQUEST: manage_map((xcb_map_request_event_t *)event); break;
            case XCB_CONFIGURE_REQUEST: manage_configure((xcb_configure_request_event_t *)event); break;
            case XCB_EXPOSE: {
                xcb_expose_event_t *e = (xcb_expose_event_t *)event;
                int icon_slot = icon_slot_by_xid(e->window);
                if (icon_slot >= 0 && e->count == 0) draw_icon(icon_slot);
                else if (e->window == taskbar_window && e->count == 0) invalidate(DIRTY_TASKBAR);
                break;
            }
            case XCB_BUTTON_PRESS: {
                xcb_button_press_event_t *e = (xcb_button_press_event_t *)event;
                int icon_slot = icon_slot_by_xid(e->event);
                if (icon_slot >= 0 && e->detail == 1) {
                    jx11_icon *ic = &icons[icon_slot];
                    pid_t pid = launch_program(ic->program);
                    if (pid > 0) fprintf(stderr, "jx11: icon %s launched %s pid=%ld\n", ic->id, ic->program, (long)pid);
                } else if (e->event == taskbar_window && e->detail == 1) {
                    taskbar_click(e->event_x);
                } else if (e->child != XCB_NONE) {
                    focus_window(e->child);
                }
                break;
            }
            case XCB_PROPERTY_NOTIFY: {
                xcb_property_notify_event_t *e = (xcb_property_notify_event_t *)event;
                int slot = window_slot_by_xid(e->window);
                if (slot >= 0) read_title(slot);
                break;
            }
            case XCB_CONFIGURE_NOTIFY: {
                xcb_configure_notify_event_t *e = (xcb_configure_notify_event_t *)event;
                int slot = window_slot_by_xid(e->window);
                if (slot >= 0) {
                    windows[slot].x=e->x; windows[slot].y=e->y;
                    windows[slot].width=e->width; windows[slot].height=e->height;
                }
                break;
            }
            case XCB_UNMAP_NOTIFY: {
                xcb_unmap_notify_event_t *e = (xcb_unmap_notify_event_t *)event;
                if (e->window != root && e->window != taskbar_window) remove_managed(e->window);
                break;
            }
            case XCB_DESTROY_NOTIFY: {
                xcb_destroy_notify_event_t *e = (xcb_destroy_notify_event_t *)event;
                remove_managed(e->window);
                break;
            }
            case XCB_FOCUS_IN: {
                xcb_focus_in_event_t *e = (xcb_focus_in_event_t *)event;
                if (window_slot_by_xid(e->event) >= 0 && focused != e->event) {
                    focused = e->event;
                    invalidate(DIRTY_TASKBAR);
                }
                break;
            }
            default: break;
        }
        free(event);
        render_dirty();
        flush_or_die("event");
    }

    destroy_assets();
    xcb_disconnect(conn);
    return 0;
}
