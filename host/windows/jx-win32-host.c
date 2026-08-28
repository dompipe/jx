/* JX Win32 desktop host: jx.desktop/1
 * OS-specific HWND/HDC/HANDLE values remain behind this adapter.
 */
#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#include <tlhelp32.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <wchar.h>
#include "../common/jx-host-trace.h"
#include "../common/jx-global-power.h"
#include "../common/jx-task-manager.h"
#include "../common/jx-task-control.h"

#define JX_WM_CANDIDATE_READY (WM_APP + 0x31)
#define JX_WM_CUTOVER_COMPLETE (WM_APP + 0x32)
#define JX_WM_CUTOVER_ERROR (WM_APP + 0x33)
#define JX_TASK_REFRESH_TIMER 1u
#define JX_TASK_ROW_HEIGHT 24
#define JX_TASK_HEADER_HEIGHT 32
#define JX_TASK_BUTTON_HEIGHT 34
#define JX_TASK_PROCESS_MAX JX_TASK_MANAGER_MAX

static const wchar_t *CLASS_NAME = L"JXDesktopHostWindow";
static const wchar_t *TASK_CLASS_NAME = L"JXTaskManagerWindow";
static jx_host_trace host_trace;
static jx_global_power global_power;
static jx_task_manager task_manager;
static jx_task_controller task_controller;
static uint64_t active_generation = 1u;
static HWND task_window = NULL;
static uint64_t selected_task_id = 0u;

typedef struct {
    uint8_t in_use;
    uint64_t task_id;
    DWORD process_id;
    HANDLE process;
    uint64_t last_cpu_ns;
} jx_win32_task_process;

static jx_win32_task_process task_processes[JX_TASK_PROCESS_MAX];

static void trace_emit(uint16_t kind,uint64_t subject,uint64_t value){
    (void)jx_host_trace_emit(&host_trace,kind,active_generation,subject,value);
}

static uint64_t filetime_u64(const FILETIME *value) {
    ULARGE_INTEGER v;
    v.LowPart = value->dwLowDateTime;
    v.HighPart = value->dwHighDateTime;
    return v.QuadPart;
}

static jx_win32_task_process *task_process_find(uint64_t task_id) {
    size_t i;
    for (i = 0u; i < JX_TASK_PROCESS_MAX; ++i)
        if (task_processes[i].in_use && task_processes[i].task_id == task_id) return &task_processes[i];
    return NULL;
}

static jx_win32_task_process *task_process_add(uint64_t task_id, DWORD process_id, HANDLE process) {
    size_t i;
    for (i = 0u; i < JX_TASK_PROCESS_MAX; ++i) {
        if (task_processes[i].in_use) continue;
        memset(&task_processes[i], 0, sizeof task_processes[i]);
        task_processes[i].in_use = 1u;
        task_processes[i].task_id = task_id;
        task_processes[i].process_id = process_id;
        task_processes[i].process = process;
        return &task_processes[i];
    }
    return NULL;
}

static void task_process_release(jx_win32_task_process *slot) {
    if (!slot) return;
    if (slot->process) CloseHandle(slot->process);
    memset(slot, 0, sizeof *slot);
}

static int task_threads_apply(DWORD process_id, int suspend) {
    HANDLE snapshot = CreateToolhelp32Snapshot(TH32CS_SNAPTHREAD, 0u);
    THREADENTRY32 entry;
    int matched = 0;
    if (snapshot == INVALID_HANDLE_VALUE) return -1;
    memset(&entry, 0, sizeof entry);
    entry.dwSize = sizeof entry;
    if (Thread32First(snapshot, &entry)) {
        do {
            HANDLE thread;
            if (entry.th32OwnerProcessID != process_id) continue;
            thread = OpenThread(THREAD_SUSPEND_RESUME, FALSE, entry.th32ThreadID);
            if (!thread) continue;
            if (suspend) {
                if (SuspendThread(thread) != (DWORD)-1) matched++;
            } else {
                if (ResumeThread(thread) != (DWORD)-1) matched++;
            }
            CloseHandle(thread);
        } while (Thread32Next(snapshot, &entry));
    }
    CloseHandle(snapshot);
    return matched > 0 ? 0 : -1;
}

static int task_host_control(uint64_t task_id, jx_task_action action, void *context) {
    jx_win32_task_process *slot;
    (void)context;
    slot = task_process_find(task_id);
    if (!slot || !slot->process) return -1;
    switch (action) {
        case JX_TASK_ACTION_PAUSE:
            return task_threads_apply(slot->process_id, 1);
        case JX_TASK_ACTION_RESUME:
            return task_threads_apply(slot->process_id, 0);
        case JX_TASK_ACTION_STOP:
            return TerminateProcess(slot->process, 0u) ? 0 : -1;
        case JX_TASK_ACTION_ROLLBACK:
        case JX_TASK_ACTION_SWAP:
        default:
            return -1;
    }
}

static void task_refresh_processes(void) {
    size_t i;
    for (i = 0u; i < JX_TASK_PROCESS_MAX; ++i) {
        jx_win32_task_process *slot = &task_processes[i];
        jx_task_record *task;
        DWORD exit_code = STILL_ACTIVE;
        FILETIME create_time, exit_time, kernel_time, user_time;
        uint64_t cpu_ns;
        if (!slot->in_use || !slot->process) continue;
        task = jx_task_manager_find(&task_manager, slot->task_id);
        if (!task) {
            task_process_release(slot);
            continue;
        }
        if (GetExitCodeProcess(slot->process, &exit_code) && exit_code != STILL_ACTIVE) {
            task->state = JX_TASK_STATE_STOPPED;
            task_process_release(slot);
            continue;
        }
        if (!GetProcessTimes(slot->process, &create_time, &exit_time, &kernel_time, &user_time)) continue;
        cpu_ns = (filetime_u64(&kernel_time) + filetime_u64(&user_time)) * 100u;
        if (cpu_ns >= slot->last_cpu_ns) {
            (void)jx_task_manager_account(&task_manager, slot->task_id, 0u, cpu_ns - slot->last_cpu_ns);
        }
        slot->last_cpu_ns = cpu_ns;
    }
}

static void wide_task_name(const wchar_t *command, char out[JX_TASK_NAME_MAX + 1u]) {
    wchar_t token[JX_TASK_NAME_MAX + 1u];
    size_t n = 0u;
    const wchar_t *p = command;
    int bytes;
    if (!out) return;
    out[0] = '\0';
    if (!p) return;
    while (*p == L' ' || *p == L'\t') ++p;
    if (*p == L'\"') ++p;
    while (*p && *p != L'\"' && *p != L' ' && *p != L'\t' && n < JX_TASK_NAME_MAX) token[n++] = *p++;
    token[n] = L'\0';
    bytes = WideCharToMultiByte(CP_UTF8, 0, token, -1, out, JX_TASK_NAME_MAX + 1, NULL, NULL);
    if (bytes <= 0) strcpy_s(out, JX_TASK_NAME_MAX + 1u, "jx-task");
}

static void launch_program(const wchar_t *program) {
    STARTUPINFOW startup;
    PROCESS_INFORMATION process;
    wchar_t *command_line;
    size_t chars;
    uint64_t task_id = 0u;
    char task_name[JX_TASK_NAME_MAX + 1u];
    jx_win32_task_process *slot;
    if (!program || !*program) return;
    chars = wcslen(program) + 1u;
    command_line = (wchar_t *)malloc(chars * sizeof *command_line);
    if (!command_line) return;
    memcpy(command_line, program, chars * sizeof *command_line);
    memset(&startup, 0, sizeof startup);
    memset(&process, 0, sizeof process);
    startup.cb = sizeof startup;
    if (!CreateProcessW(NULL, command_line, NULL, NULL, FALSE, 0u, NULL, NULL, &startup, &process)) {
        fwprintf(stderr, L"jx-win32-host: failed to launch %ls\n", program);
        free(command_line);
        return;
    }
    free(command_line);
    CloseHandle(process.hThread);
    wide_task_name(program, task_name);
    if (jx_task_manager_add(&task_manager, task_name, (uint64_t)process.dwProcessId,
                            active_generation, JX_TASK_BRANCH_SHADOW, &task_id) != 0) {
        TerminateProcess(process.hProcess, 1u);
        CloseHandle(process.hProcess);
        return;
    }
    slot = task_process_add(task_id, process.dwProcessId, process.hProcess);
    if (!slot) {
        (void)jx_task_manager_remove(&task_manager, task_id);
        TerminateProcess(process.hProcess, 1u);
        CloseHandle(process.hProcess);
        return;
    }
    trace_emit(JX_TRACE_PROGRAM_START, task_id, (uint64_t)process.dwProcessId);
    if (task_window) InvalidateRect(task_window, NULL, FALSE);
}

static const wchar_t *task_state_text(uint8_t state) {
    switch (state) {
        case JX_TASK_STATE_RUNNING: return L"Running";
        case JX_TASK_STATE_PAUSED: return L"Paused";
        case JX_TASK_STATE_SWAPPING: return L"Swapping";
        case JX_TASK_STATE_STOPPED: return L"Stopped";
        case JX_TASK_STATE_ERROR: return L"Error";
        default: return L"?";
    }
}

static const wchar_t *task_branch_text(uint8_t branch) {
    return branch == JX_TASK_BRANCH_SHADOW ? L"SHADOW" : L"BYTECODE";
}

static void task_draw_button(HDC dc, const RECT *rect, const wchar_t *label, int enabled) {
    HBRUSH brush = CreateSolidBrush(enabled ? RGB(58, 67, 81) : RGB(35, 38, 43));
    FillRect(dc, rect, brush);
    DeleteObject(brush);
    FrameRect(dc, rect, (HBRUSH)GetStockObject(GRAY_BRUSH));
    SetBkMode(dc, TRANSPARENT);
    SetTextColor(dc, enabled ? RGB(245, 245, 245) : RGB(120, 120, 120));
    DrawTextW(dc, label, -1, (RECT *)rect, DT_CENTER | DT_VCENTER | DT_SINGLELINE);
}

static size_t task_visible_row_at(int y) {
    size_t visible = 0u;
    size_t i;
    if (y < JX_TASK_HEADER_HEIGHT) return (size_t)-1;
    for (i = 0u; i < JX_TASK_MANAGER_MAX; ++i) {
        if (!task_manager.tasks[i].in_use) continue;
        if (y >= JX_TASK_HEADER_HEIGHT + (int)(visible * JX_TASK_ROW_HEIGHT) &&
            y < JX_TASK_HEADER_HEIGHT + (int)((visible + 1u) * JX_TASK_ROW_HEIGHT)) return i;
        visible++;
    }
    return (size_t)-1;
}

static void task_button_rects(HWND hwnd, RECT *pause, RECT *resume, RECT *stop, RECT *rollback, RECT *swap) {
    RECT client;
    int gap = 8, width = 100, y;
    GetClientRect(hwnd, &client);
    y = client.bottom - JX_TASK_BUTTON_HEIGHT - 10;
    *pause = (RECT){10, y, 10 + width, y + JX_TASK_BUTTON_HEIGHT};
    *resume = (RECT){10 + (width + gap), y, 10 + (width + gap) + width, y + JX_TASK_BUTTON_HEIGHT};
    *stop = (RECT){10 + 2 * (width + gap), y, 10 + 2 * (width + gap) + width, y + JX_TASK_BUTTON_HEIGHT};
    *rollback = (RECT){10 + 3 * (width + gap), y, 10 + 3 * (width + gap) + width, y + JX_TASK_BUTTON_HEIGHT};
    *swap = (RECT){10 + 4 * (width + gap), y, 10 + 4 * (width + gap) + width, y + JX_TASK_BUTTON_HEIGHT};
}

static int point_in_rect(const RECT *rect, int x, int y) {
    return x >= rect->left && x < rect->right && y >= rect->top && y < rect->bottom;
}

static void task_apply_selected(jx_task_action action) {
    if (!selected_task_id) return;
    (void)jx_task_controller_apply(&task_controller, selected_task_id, action);
    if (task_window) InvalidateRect(task_window, NULL, FALSE);
}

static LRESULT CALLBACK task_wndproc(HWND hwnd, UINT msg, WPARAM wParam, LPARAM lParam) {
    switch (msg) {
        case WM_CREATE:
            SetTimer(hwnd, JX_TASK_REFRESH_TIMER, 500u, NULL);
            return 0;
        case WM_TIMER:
            if (wParam == JX_TASK_REFRESH_TIMER) {
                task_refresh_processes();
                InvalidateRect(hwnd, NULL, FALSE);
            }
            return 0;
        case WM_LBUTTONDOWN: {
            int x = (short)LOWORD(lParam), y = (short)HIWORD(lParam);
            RECT pause, resume, stop, rollback, swap;
            size_t row;
            task_button_rects(hwnd, &pause, &resume, &stop, &rollback, &swap);
            if (point_in_rect(&pause, x, y)) task_apply_selected(JX_TASK_ACTION_PAUSE);
            else if (point_in_rect(&resume, x, y)) task_apply_selected(JX_TASK_ACTION_RESUME);
            else if (point_in_rect(&stop, x, y)) task_apply_selected(JX_TASK_ACTION_STOP);
            else {
                row = task_visible_row_at(y);
                if (row != (size_t)-1) selected_task_id = task_manager.tasks[row].task_id;
            }
            InvalidateRect(hwnd, NULL, FALSE);
            return 0;
        }
        case WM_KEYDOWN:
            if (wParam == VK_ESCAPE || wParam == VK_F10) {
                ShowWindow(hwnd, SW_HIDE);
                return 0;
            }
            if (wParam == 'P') task_apply_selected(JX_TASK_ACTION_PAUSE);
            if (wParam == 'R') task_apply_selected(JX_TASK_ACTION_RESUME);
            if (wParam == VK_DELETE) task_apply_selected(JX_TASK_ACTION_STOP);
            return 0;
        case WM_PAINT: {
            PAINTSTRUCT ps;
            HDC dc = BeginPaint(hwnd, &ps);
            RECT client, pause, resume, stop, rollback, swap;
            size_t i, visible = 0u;
            GetClientRect(hwnd, &client);
            FillRect(dc, &client, (HBRUSH)GetStockObject(BLACK_BRUSH));
            SetBkMode(dc, TRANSPARENT);
            SetTextColor(dc, RGB(225, 225, 225));
            TextOutW(dc, 10, 8, L"Task", 4);
            TextOutW(dc, 235, 8, L"PID", 3);
            TextOutW(dc, 305, 8, L"Gen", 3);
            TextOutW(dc, 355, 8, L"Mode", 4);
            TextOutW(dc, 440, 8, L"State", 5);
            TextOutW(dc, 525, 8, L"CPU ms", 6);
            for (i = 0u; i < JX_TASK_MANAGER_MAX; ++i) {
                const jx_task_record *task = &task_manager.tasks[i];
                wchar_t name[128], numbers[64];
                RECT row_rect;
                int y;
                if (!task->in_use) continue;
                y = JX_TASK_HEADER_HEIGHT + (int)(visible * JX_TASK_ROW_HEIGHT);
                row_rect = (RECT){0, y, client.right, y + JX_TASK_ROW_HEIGHT};
                if (task->task_id == selected_task_id) {
                    HBRUSH selected = CreateSolidBrush(RGB(35, 66, 102));
                    FillRect(dc, &row_rect, selected);
                    DeleteObject(selected);
                }
                MultiByteToWideChar(CP_UTF8, 0, task->name, -1, name, 128);
                TextOutW(dc, 10, y + 4, name, (int)wcslen(name));
                _snwprintf_s(numbers, 64, _TRUNCATE, L"%llu", (unsigned long long)task->os_process_id);
                TextOutW(dc, 235, y + 4, numbers, (int)wcslen(numbers));
                _snwprintf_s(numbers, 64, _TRUNCATE, L"%llu", (unsigned long long)task->generation);
                TextOutW(dc, 305, y + 4, numbers, (int)wcslen(numbers));
                TextOutW(dc, 355, y + 4, task_branch_text(task->branch), (int)wcslen(task_branch_text(task->branch)));
                TextOutW(dc, 440, y + 4, task_state_text(task->state), (int)wcslen(task_state_text(task->state)));
                _snwprintf_s(numbers, 64, _TRUNCATE, L"%.3f", (double)task->cpu_time_ns / 1000000.0);
                TextOutW(dc, 525, y + 4, numbers, (int)wcslen(numbers));
                visible++;
            }
            task_button_rects(hwnd, &pause, &resume, &stop, &rollback, &swap);
            task_draw_button(dc, &pause, L"Pause (P)", selected_task_id != 0u);
            task_draw_button(dc, &resume, L"Resume (R)", selected_task_id != 0u);
            task_draw_button(dc, &stop, L"Stop (Del)", selected_task_id != 0u);
            task_draw_button(dc, &rollback, L"Rollback", 0);
            task_draw_button(dc, &swap, L"Swap", 0);
            EndPaint(hwnd, &ps);
            return 0;
        }
        case WM_CLOSE:
            ShowWindow(hwnd, SW_HIDE);
            return 0;
        case WM_DESTROY:
            KillTimer(hwnd, JX_TASK_REFRESH_TIMER);
            task_window = NULL;
            return 0;
    }
    return DefWindowProcW(hwnd, msg, wParam, lParam);
}

static void task_window_toggle(HINSTANCE instance) {
    if (!task_window) {
        task_window = CreateWindowExW(WS_EX_TOOLWINDOW, TASK_CLASS_NAME, L"JX Task Manager",
                                      WS_OVERLAPPEDWINDOW, CW_USEDEFAULT, CW_USEDEFAULT,
                                      680, 460, NULL, NULL, instance, NULL);
        if (!task_window) return;
    }
    if (IsWindowVisible(task_window)) ShowWindow(task_window, SW_HIDE);
    else {
        task_refresh_processes();
        ShowWindow(task_window, SW_SHOW);
        SetForegroundWindow(task_window);
    }
}

static void draw_power(HWND hwnd,HDC dc){
    if(!global_power.visible)return;
    RECT rc; GetClientRect(hwnd,&rc); int size=42,margin=16;
    RECT b={rc.right-margin-size,rc.bottom-margin-size,rc.right-margin,rc.bottom-margin};
    HBRUSH brush=CreateSolidBrush(global_power.state==JX_GLOBAL_POWER_READY?RGB(48,180,96):RGB(96,96,96));
    FillRect(dc,&b,brush); DeleteObject(brush);
    SetBkMode(dc,TRANSPARENT); SetTextColor(dc,RGB(255,255,255)); DrawTextW(dc,L"\x23FB",1,&b,DT_CENTER|DT_VCENTER|DT_SINGLELINE);
}

static int power_hit(HWND hwnd,int x,int y){
    if(!jx_global_power_can_press(&global_power))return 0; RECT rc; GetClientRect(hwnd,&rc); int size=42,margin=16;
    return x>=rc.right-margin-size&&x<rc.right-margin&&y>=rc.bottom-margin-size&&y<rc.bottom-margin;
}

static LRESULT CALLBACK wndproc(HWND hwnd, UINT msg, WPARAM wParam, LPARAM lParam) {
    switch (msg) {
        case WM_PAINT: {
            PAINTSTRUCT ps; HDC dc=BeginPaint(hwnd,&ps); RECT rc; GetClientRect(hwnd,&rc);
            FillRect(dc,&rc,(HBRUSH)GetStockObject(BLACK_BRUSH)); draw_power(hwnd,dc); EndPaint(hwnd,&ps);
            trace_emit(JX_TRACE_RENDER_INVALIDATE,0u,1u); return 0;
        }
        case WM_ERASEBKGND: return 1;
        case WM_LBUTTONDOWN: {
            int x=(short)LOWORD(lParam),y=(short)HIWORD(lParam); SetFocus(hwnd); trace_emit(JX_TRACE_INPUT,1u,((uint64_t)(uint32_t)x<<32)|(uint32_t)y);
            if(power_hit(hwnd,x,y)){ jx_global_power_cutover_begin(&global_power); trace_emit(JX_TRACE_POWER_STATE,0u,JX_GLOBAL_POWER_CUTOVER); InvalidateRect(hwnd,NULL,FALSE); }
            return 0;
        }
        case WM_KEYDOWN:
            trace_emit(JX_TRACE_INPUT,2u,(uint64_t)wParam);
            if (wParam == VK_F10) { task_window_toggle((HINSTANCE)GetWindowLongPtrW(hwnd, GWLP_HINSTANCE)); return 0; }
            if (wParam == VK_ESCAPE) { PostQuitMessage(0); return 0; }
            return 0;
        case WM_DISPLAYCHANGE: InvalidateRect(hwnd,NULL,TRUE); return 0;
        case JX_WM_CANDIDATE_READY:
            jx_global_power_candidate_ready(&global_power,(uint64_t)wParam); trace_emit(JX_TRACE_POWER_STATE,0u,JX_GLOBAL_POWER_READY); InvalidateRect(hwnd,NULL,FALSE); return 0;
        case JX_WM_CUTOVER_COMPLETE:
            active_generation=(uint64_t)wParam; jx_global_power_cutover_complete(&global_power,active_generation); trace_emit(JX_TRACE_GENERATION,0u,active_generation); trace_emit(JX_TRACE_POWER_STATE,0u,JX_GLOBAL_POWER_IDLE); InvalidateRect(hwnd,NULL,FALSE); return 0;
        case JX_WM_CUTOVER_ERROR:
            jx_global_power_error(&global_power); trace_emit(JX_TRACE_POWER_STATE,0u,JX_GLOBAL_POWER_ERROR); InvalidateRect(hwnd,NULL,FALSE); return 0;
        case WM_DESTROY: trace_emit(JX_TRACE_PROGRAM_STOP,0u,0u); PostQuitMessage(0); return 0;
    }
    return DefWindowProcW(hwnd, msg, wParam, lParam);
}

int WINAPI wWinMain(HINSTANCE instance, HINSTANCE prev, PWSTR cmd, int show) {
    WNDCLASSW wc = {0}, task_wc = {0};
    MSG msg;
    size_t i;
    int width, height;
    HWND hwnd;
    (void)prev; (void)show;
    jx_host_trace_init(&host_trace,JX_HOST_WINDOWS_WIN32);
    jx_global_power_init(&global_power,active_generation);
    jx_task_manager_init(&task_manager);
    jx_task_controller_init(&task_controller, &task_manager, task_host_control, NULL);
    memset(task_processes, 0, sizeof task_processes);

    wc.lpfnWndProc=wndproc; wc.hInstance=instance; wc.lpszClassName=CLASS_NAME; wc.hCursor=LoadCursor(NULL,IDC_ARROW); wc.hbrBackground=(HBRUSH)GetStockObject(BLACK_BRUSH);
    task_wc.lpfnWndProc=task_wndproc; task_wc.hInstance=instance; task_wc.lpszClassName=TASK_CLASS_NAME; task_wc.hCursor=LoadCursor(NULL,IDC_ARROW); task_wc.hbrBackground=(HBRUSH)GetStockObject(BLACK_BRUSH);
    if (!RegisterClassW(&wc) || !RegisterClassW(&task_wc)) return 2;
    width=GetSystemMetrics(SM_CXSCREEN); height=GetSystemMetrics(SM_CYSCREEN);
    hwnd=CreateWindowExW(WS_EX_TOOLWINDOW,CLASS_NAME,L"JX Desktop",WS_POPUP|WS_VISIBLE,0,0,width,height,NULL,NULL,instance,NULL); if(!hwnd)return 3;
    SetWindowPos(hwnd,HWND_BOTTOM,0,0,width,height,SWP_SHOWWINDOW); trace_emit(JX_TRACE_PROGRAM_START,0u,0u);
    if(cmd&&*cmd){ while(*cmd==L' '||*cmd==L'\t')++cmd; if(*cmd)launch_program(cmd); }
    while(GetMessageW(&msg,NULL,0,0)>0){ TranslateMessage(&msg); DispatchMessageW(&msg); }
    for (i = 0u; i < JX_TASK_PROCESS_MAX; ++i) if (task_processes[i].in_use) task_process_release(&task_processes[i]);
    return (int)msg.wParam;
}
