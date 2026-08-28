#ifndef JX_WIN32_EXE_TETHER_H
#define JX_WIN32_EXE_TETHER_H
#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#include <stdint.h>

#define JX_WIN32_TETHER_VERSION 1u

typedef struct {
    uint8_t version;
    wchar_t installed[MAX_PATH];
    wchar_t candidate[MAX_PATH];
    wchar_t previous[MAX_PATH];
    wchar_t helper[MAX_PATH];
} jx_win32_exe_tether;

int jx_win32_exe_tether_init(jx_win32_exe_tether *tether,
                             const wchar_t *installed,
                             const wchar_t *candidate,
                             const wchar_t *helper);
/* Launches the helper after takeover is proven. The helper waits for owner_pid
 * to exit, then atomically moves installed -> .previous and candidate -> installed. */
int jx_win32_exe_tether_arm(const jx_win32_exe_tether *tether, DWORD owner_pid);

#endif
