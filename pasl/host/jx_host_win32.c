#ifdef _WIN32
#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#include <stdlib.h>
#include "jx_host.h"

struct jx_host_window { HWND handle; char id[97]; };

static LRESULT CALLBACK jx_window_proc(HWND hwnd, UINT message, WPARAM wparam, LPARAM lparam) {
    if (message == WM_CLOSE) { DestroyWindow(hwnd); return 0; }
    return DefWindowProcA(hwnd, message, wparam, lparam);
}

jx_host_window *jx_host_open(const jx_window_spec *spec) {
    static int registered = 0;
    WNDCLASSA wc = {0};
    jx_host_window *window;
    if (!spec) return NULL;
    if (!registered) {
        wc.lpfnWndProc = jx_window_proc;
        wc.hInstance = GetModuleHandleA(NULL);
        wc.lpszClassName = "JxHostWindow";
        wc.hCursor = LoadCursor(NULL, IDC_ARROW);
        if (!RegisterClassA(&wc) && GetLastError() != ERROR_CLASS_ALREADY_EXISTS) return NULL;
        registered = 1;
    }
    window = (jx_host_window *)calloc(1, sizeof(*window));
    if (!window) return NULL;
    lstrcpynA(window->id, spec->id ? spec->id : "main", 97);
    window->handle = CreateWindowExA(0, "JxHostWindow", spec->title ? spec->title : "JX",
        WS_OVERLAPPEDWINDOW, spec->x, spec->y, spec->width, spec->height,
        NULL, NULL, GetModuleHandleA(NULL), NULL);
    if (!window->handle) { free(window); return NULL; }
    ShowWindow(window->handle, SW_SHOW);
    return window;
}

int jx_host_poll(jx_host_window *window, jx_host_event *event) {
    MSG message;
    if (!window || !event) return -1;
    if (!PeekMessageA(&message, window->handle, 0, 0, PM_REMOVE)) return 0;
    TranslateMessage(&message);
    DispatchMessageA(&message);
    event->version = JX_HOST_ABI_VERSION;
    event->type = message.message == WM_DESTROY ? "window.close" : "window.event";
    event->window_id = window->id;
    event->json_payload = "{}";
    return 1;
}

int jx_host_set_title(jx_host_window *window, const char *title) {
    return window && SetWindowTextA(window->handle, title ? title : "") ? 0 : -1;
}

void jx_host_close(jx_host_window *window) {
    if (!window) return;
    if (window->handle) DestroyWindow(window->handle);
    free(window);
}
#endif
