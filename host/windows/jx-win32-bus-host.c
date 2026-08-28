/* Production Win32 JX host wrapper.
 *
 * The legacy host implementation remains the UI/process adapter. This wrapper
 * removes its independent WM timer and supplies the common JX applied-bus
 * clock instead. The UI still receives a WM_TIMER-shaped maintenance message,
 * but that message is synthesized only after a JX 0x7f 0x00 0x01 epoch pulse.
 */
#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#include "../common/jx-idle-bus.h"
#include "jx-idle-shared.h"

static UINT_PTR jx_bus_disabled_set_timer(HWND hwnd, UINT_PTR id, UINT ms, TIMERPROC proc) {
    (void)hwnd; (void)id; (void)ms; (void)proc;
    return 1u;
}

static BOOL jx_bus_disabled_kill_timer(HWND hwnd, UINT_PTR id) {
    (void)hwnd; (void)id;
    return TRUE;
}

#define SetTimer jx_bus_disabled_set_timer
#define KillTimer jx_bus_disabled_kill_timer
#define wWinMain jx_win32_ui_main
#include "jx-win32-host.c"
#undef wWinMain
#undef KillTimer
#undef SetTimer

static jx_win32_idle_shared jx_bus_shared;
static HANDLE jx_bus_thread_handle = NULL;
static volatile LONG jx_bus_stop = 0;

static DWORD WINAPI jx_bus_thread_main(LPVOID context) {
    uint64_t epoch = 0u;
    (void)context;
    while (InterlockedCompareExchange(&jx_bus_stop, 0, 0) == 0) {
        Sleep(JX_IDLE_BUS_PERIOD_MS);
        if (InterlockedCompareExchange(&jx_bus_stop, 0, 0) != 0) break;
        epoch++;
        if (jx_win32_idle_shared_broadcast(&jx_bus_shared, epoch, GetTickCount64()) != 0) continue;
        /* Reuse the existing UI-thread refresh handler without an OS timer. */
        if (task_window) PostMessageW(task_window, WM_TIMER, (WPARAM)JX_TASK_REFRESH_TIMER, 0);
    }
    return 0u;
}

int WINAPI wWinMain(HINSTANCE instance, HINSTANCE prev, PWSTR cmd, int show) {
    int rc;
    if (jx_win32_idle_shared_host_open(&jx_bus_shared) != 0) return 10;
    InterlockedExchange(&jx_bus_stop, 0);
    jx_bus_thread_handle = CreateThread(NULL, 0u, jx_bus_thread_main, NULL, 0u, NULL);
    if (!jx_bus_thread_handle) {
        jx_win32_idle_shared_close(&jx_bus_shared, 1);
        return 11;
    }

    rc = jx_win32_ui_main(instance, prev, cmd, show);

    InterlockedExchange(&jx_bus_stop, 1);
    WaitForSingleObject(jx_bus_thread_handle, JX_IDLE_BUS_PERIOD_MS * 2u);
    CloseHandle(jx_bus_thread_handle);
    jx_bus_thread_handle = NULL;
    jx_win32_idle_shared_close(&jx_bus_shared, 1);
    return rc;
}
