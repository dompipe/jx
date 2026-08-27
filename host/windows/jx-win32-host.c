/* JX Win32 desktop host prototype: jx.desktop/1
 *
 * Build (MSVC): cl /O2 jx-win32-host.c user32.lib gdi32.lib shell32.lib
 * Build (clang): clang -O2 -o jx-win32-host.exe jx-win32-host.c -luser32 -lgdi32 -lshell32
 *
 * This prototype owns one borderless desktop surface, receives pointer/keyboard
 * input, paints a background, and launches programs. It implements the same
 * host-neutral Desktop contract as the X11 host; HWND/HDC/HANDLE never cross
 * into canonical JX state.
 */
#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#include <shellapi.h>
#include <stdio.h>
#include <wchar.h>

static const wchar_t *CLASS_NAME = L"JXDesktopHostWindow";

static void launch_program(const wchar_t *program) {
    HINSTANCE r = ShellExecuteW(NULL, L"open", program, NULL, NULL, SW_SHOWNORMAL);
    if ((INT_PTR)r <= 32) {
        fwprintf(stderr, L"jx-win32-host: failed to launch %ls\n", program);
    }
}

static LRESULT CALLBACK wndproc(HWND hwnd, UINT msg, WPARAM wParam, LPARAM lParam) {
    switch (msg) {
        case WM_ERASEBKGND: {
            RECT rc;
            GetClientRect(hwnd, &rc);
            FillRect((HDC)wParam, &rc, (HBRUSH)GetStockObject(BLACK_BRUSH));
            return 1;
        }
        case WM_LBUTTONDOWN:
            SetFocus(hwnd);
            return 0;
        case WM_KEYDOWN:
            if (wParam == VK_ESCAPE) {
                PostQuitMessage(0);
                return 0;
            }
            return 0;
        case WM_DISPLAYCHANGE:
            InvalidateRect(hwnd, NULL, TRUE);
            return 0;
        case WM_DESTROY:
            PostQuitMessage(0);
            return 0;
    }
    return DefWindowProcW(hwnd, msg, wParam, lParam);
}

int WINAPI wWinMain(HINSTANCE instance, HINSTANCE prev, PWSTR cmd, int show) {
    (void)prev; (void)show;

    WNDCLASSW wc = {0};
    wc.lpfnWndProc = wndproc;
    wc.hInstance = instance;
    wc.lpszClassName = CLASS_NAME;
    wc.hCursor = LoadCursor(NULL, IDC_ARROW);
    wc.hbrBackground = (HBRUSH)GetStockObject(BLACK_BRUSH);
    if (!RegisterClassW(&wc)) return 2;

    int width = GetSystemMetrics(SM_CXSCREEN);
    int height = GetSystemMetrics(SM_CYSCREEN);
    HWND hwnd = CreateWindowExW(
        WS_EX_TOOLWINDOW,
        CLASS_NAME,
        L"JX Desktop",
        WS_POPUP | WS_VISIBLE,
        0, 0, width, height,
        NULL, NULL, instance, NULL
    );
    if (!hwnd) return 3;

    SetWindowPos(hwnd, HWND_BOTTOM, 0, 0, width, height, SWP_SHOWWINDOW);
    fprintf(stderr, "jx-win32-host: desktop active %dx%d\n", width, height);

    if (cmd && *cmd) {
        while (*cmd == L' ' || *cmd == L'\t') ++cmd;
        if (*cmd) launch_program(cmd);
    }

    MSG msg;
    while (GetMessageW(&msg, NULL, 0, 0) > 0) {
        TranslateMessage(&msg);
        DispatchMessageW(&msg);
    }
    return (int)msg.wParam;
}
