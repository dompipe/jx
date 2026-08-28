/* JX Win32 desktop host: jx.desktop/1
 * OS-specific HWND/HDC/HANDLE values remain behind this adapter.
 */
#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#include <shellapi.h>
#include <stdio.h>
#include <wchar.h>
#include "../common/jx-host-trace.h"
#include "../common/jx-global-power.h"

#define JX_WM_CANDIDATE_READY (WM_APP + 0x31)
#define JX_WM_CUTOVER_COMPLETE (WM_APP + 0x32)
#define JX_WM_CUTOVER_ERROR (WM_APP + 0x33)

static const wchar_t *CLASS_NAME = L"JXDesktopHostWindow";
static jx_host_trace host_trace;
static jx_global_power global_power;
static uint64_t active_generation = 1u;

static void trace_emit(uint16_t kind,uint64_t subject,uint64_t value){
    (void)jx_host_trace_emit(&host_trace,kind,active_generation,subject,value);
}

static void launch_program(const wchar_t *program) {
    HINSTANCE r = ShellExecuteW(NULL, L"open", program, NULL, NULL, SW_SHOWNORMAL);
    if ((INT_PTR)r <= 32) fwprintf(stderr, L"jx-win32-host: failed to launch %ls\n", program);
    else trace_emit(JX_TRACE_PROGRAM_START,0u,0u);
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
    (void)prev; (void)show; jx_host_trace_init(&host_trace,JX_HOST_WINDOWS_WIN32); jx_global_power_init(&global_power,active_generation);
    WNDCLASSW wc = {0}; wc.lpfnWndProc=wndproc; wc.hInstance=instance; wc.lpszClassName=CLASS_NAME; wc.hCursor=LoadCursor(NULL,IDC_ARROW); wc.hbrBackground=(HBRUSH)GetStockObject(BLACK_BRUSH);
    if (!RegisterClassW(&wc)) return 2;
    int width=GetSystemMetrics(SM_CXSCREEN),height=GetSystemMetrics(SM_CYSCREEN);
    HWND hwnd=CreateWindowExW(WS_EX_TOOLWINDOW,CLASS_NAME,L"JX Desktop",WS_POPUP|WS_VISIBLE,0,0,width,height,NULL,NULL,instance,NULL); if(!hwnd)return 3;
    SetWindowPos(hwnd,HWND_BOTTOM,0,0,width,height,SWP_SHOWWINDOW); trace_emit(JX_TRACE_PROGRAM_START,0u,0u);
    if(cmd&&*cmd){ while(*cmd==L' '||*cmd==L'\t')++cmd; if(*cmd)launch_program(cmd); }
    MSG msg; while(GetMessageW(&msg,NULL,0,0)>0){ TranslateMessage(&msg); DispatchMessageW(&msg); }
    return (int)msg.wParam;
}
