#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

static char *g_text = NULL;

static void die_last(const char *message) {
    DWORD code = GetLastError();
    fprintf(stderr, "jx-window-win32: %s: error %lu\n", message, (unsigned long)code);
    ExitProcess(1);
}

static char *quote_arg(const char *arg) {
    size_t len = strlen(arg);
    size_t cap = len * 2 + 3;
    char *out = (char *)malloc(cap);
    if (!out) {
        ExitProcess(1);
    }
    size_t j = 0;
    out[j++] = '"';
    for (size_t i = 0; i < len; ++i) {
        if (arg[i] == '"' || arg[i] == '\\') {
            out[j++] = '\\';
        }
        out[j++] = arg[i];
    }
    out[j++] = '"';
    out[j] = '\0';
    return out;
}

static char *build_command_line(int argc, char **argv) {
    size_t cap = 1024;
    char *cmd = (char *)calloc(1, cap);
    if (!cmd) {
        ExitProcess(1);
    }

    for (int i = 1; i < argc; ++i) {
        char *q = quote_arg(argv[i]);
        size_t need = strlen(cmd) + strlen(q) + 2;
        if (need > cap) {
            while (need > cap) {
                cap *= 2;
            }
            char *next = (char *)realloc(cmd, cap);
            if (!next) {
                free(q);
                free(cmd);
                ExitProcess(1);
            }
            cmd = next;
        }
        if (i > 1) {
            strcat(cmd, " ");
        }
        strcat(cmd, q);
        free(q);
    }

    return cmd;
}

static char *read_pipe_to_string(HANDLE pipe_read) {
    size_t cap = 4096;
    size_t len = 0;
    char *buf = (char *)malloc(cap);
    if (!buf) {
        ExitProcess(1);
    }

    for (;;) {
        char chunk[1024];
        DWORD read_count = 0;
        BOOL ok = ReadFile(pipe_read, chunk, sizeof(chunk), &read_count, NULL);
        if (!ok || read_count == 0) {
            break;
        }
        if (len + read_count + 1 > cap) {
            while (len + read_count + 1 > cap) {
                cap *= 2;
            }
            char *next = (char *)realloc(buf, cap);
            if (!next) {
                free(buf);
                ExitProcess(1);
            }
            buf = next;
        }
        memcpy(buf + len, chunk, read_count);
        len += read_count;
    }
    buf[len] = '\0';
    return buf;
}

static char *strip_html(const char *input) {
    size_t len = strlen(input);
    char *out = (char *)malloc(len + 1);
    if (!out) {
        ExitProcess(1);
    }

    int in_tag = 0;
    size_t j = 0;
    for (size_t i = 0; i < len; ++i) {
        char c = input[i];
        if (c == '<') {
            in_tag = 1;
            if (j > 0 && out[j - 1] != '\n') {
                out[j++] = '\n';
            }
            continue;
        }
        if (c == '>') {
            in_tag = 0;
            continue;
        }
        if (!in_tag) {
            out[j++] = c;
        }
    }
    out[j] = '\0';
    return out;
}

static char *run_child_capture(int argc, char **argv) {
    SECURITY_ATTRIBUTES sa;
    ZeroMemory(&sa, sizeof(sa));
    sa.nLength = sizeof(sa);
    sa.bInheritHandle = TRUE;

    HANDLE read_pipe = NULL;
    HANDLE write_pipe = NULL;
    if (!CreatePipe(&read_pipe, &write_pipe, &sa, 0)) {
        die_last("cannot create stdout pipe");
    }
    if (!SetHandleInformation(read_pipe, HANDLE_FLAG_INHERIT, 0)) {
        die_last("cannot configure stdout pipe");
    }

    STARTUPINFOA si;
    PROCESS_INFORMATION pi;
    ZeroMemory(&si, sizeof(si));
    ZeroMemory(&pi, sizeof(pi));
    si.cb = sizeof(si);
    si.dwFlags = STARTF_USESTDHANDLES;
    si.hStdOutput = write_pipe;
    si.hStdError = GetStdHandle(STD_ERROR_HANDLE);
    si.hStdInput = GetStdHandle(STD_INPUT_HANDLE);

    char *cmd = build_command_line(argc, argv);
    BOOL ok = CreateProcessA(NULL, cmd, NULL, NULL, TRUE, 0, NULL, NULL, &si, &pi);
    CloseHandle(write_pipe);
    free(cmd);
    if (!ok) {
        die_last("cannot start generated executable");
    }

    char *html = read_pipe_to_string(read_pipe);
    CloseHandle(read_pipe);
    WaitForSingleObject(pi.hProcess, INFINITE);
    CloseHandle(pi.hThread);
    CloseHandle(pi.hProcess);
    return html;
}

static LRESULT CALLBACK window_proc(HWND hwnd, UINT msg, WPARAM wp, LPARAM lp) {
    switch (msg) {
        case WM_PAINT: {
            PAINTSTRUCT ps;
            HDC hdc = BeginPaint(hwnd, &ps);
            RECT rect;
            GetClientRect(hwnd, &rect);
            rect.left += 20;
            rect.top += 20;
            DrawTextA(hdc, g_text ? g_text : "", -1, &rect, DT_LEFT | DT_TOP | DT_WORDBREAK);
            EndPaint(hwnd, &ps);
            return 0;
        }
        case WM_DESTROY:
            PostQuitMessage(0);
            return 0;
        default:
            return DefWindowProcA(hwnd, msg, wp, lp);
    }
}

int main(int argc, char **argv) {
    if (argc < 2) {
        fprintf(stderr, "Usage: jx-window-win32.exe <compiled-jx-executable.exe> [args...]\n");
        return 1;
    }

    char *html = run_child_capture(argc, argv);
    g_text = strip_html(html);
    free(html);

    HINSTANCE instance = GetModuleHandleA(NULL);
    const char *class_name = "JXNativeWindow";

    WNDCLASSA wc;
    ZeroMemory(&wc, sizeof(wc));
    wc.lpfnWndProc = window_proc;
    wc.hInstance = instance;
    wc.lpszClassName = class_name;
    wc.hbrBackground = (HBRUSH)(COLOR_WINDOW + 1);
    wc.hCursor = LoadCursor(NULL, IDC_ARROW);

    if (!RegisterClassA(&wc)) {
        die_last("cannot register window class");
    }

    HWND hwnd = CreateWindowExA(
        0,
        class_name,
        "JX Win32 Window",
        WS_OVERLAPPEDWINDOW | WS_VISIBLE,
        CW_USEDEFAULT,
        CW_USEDEFAULT,
        900,
        620,
        NULL,
        NULL,
        instance,
        NULL
    );

    if (!hwnd) {
        die_last("cannot create window");
    }

    MSG msg;
    while (GetMessageA(&msg, NULL, 0, 0) > 0) {
        TranslateMessage(&msg);
        DispatchMessageA(&msg);
    }

    free(g_text);
    return 0;
}
