#if defined(__linux__) && defined(JX_HOST_X11)
#include <X11/Xlib.h>
#include <stdlib.h>
#include <string.h>
#include "jx_host.h"

struct jx_host_window { Display *display; Window handle; char id[97]; };

jx_host_window *jx_host_open(const jx_window_spec *spec) {
    jx_host_window *window;
    int screen;
    if (!spec) return NULL;
    window = (jx_host_window *)calloc(1, sizeof(*window));
    if (!window) return NULL;
    window->display = XOpenDisplay(NULL);
    if (!window->display) { free(window); return NULL; }
    screen = DefaultScreen(window->display);
    window->handle = XCreateSimpleWindow(window->display, RootWindow(window->display, screen),
        spec->x, spec->y, (unsigned)spec->width, (unsigned)spec->height, 1,
        BlackPixel(window->display, screen), WhitePixel(window->display, screen));
    strncpy(window->id, spec->id ? spec->id : "main", 96);
    XStoreName(window->display, window->handle, spec->title ? spec->title : "JX");
    XSelectInput(window->display, window->handle, ExposureMask | KeyPressMask | StructureNotifyMask);
    XMapWindow(window->display, window->handle);
    XFlush(window->display);
    return window;
}

int jx_host_poll(jx_host_window *window, jx_host_event *event) {
    XEvent native;
    if (!window || !event) return -1;
    if (!XPending(window->display)) return 0;
    XNextEvent(window->display, &native);
    event->version = JX_HOST_ABI_VERSION;
    event->type = native.type == DestroyNotify ? "window.close" : "window.event";
    event->window_id = window->id;
    event->json_payload = "{}";
    return 1;
}

int jx_host_set_title(jx_host_window *window, const char *title) {
    if (!window) return -1;
    XStoreName(window->display, window->handle, title ? title : "");
    XFlush(window->display);
    return 0;
}

void jx_host_close(jx_host_window *window) {
    if (!window) return;
    XDestroyWindow(window->display, window->handle);
    XCloseDisplay(window->display);
    free(window);
}
#endif
