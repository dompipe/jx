#define WIN32_LEAN_AND_MEAN
#include "jx-win32-exe-tether.h"
#include <stdio.h>
#include <string.h>

static int copy_w(wchar_t *dst,size_t cap,const wchar_t *src){
    if(!dst||!src||!*src)return -1; size_t n=wcslen(src); if(n+1u>cap)return -1; memcpy(dst,src,(n+1u)*sizeof(wchar_t)); return 0;
}
int jx_win32_exe_tether_init(jx_win32_exe_tether *tether,
                             const wchar_t *installed,
                             const wchar_t *candidate,
                             const wchar_t *helper){
    if(!tether||!installed||!candidate||!helper)return -1;
    ZeroMemory(tether,sizeof *tether); tether->version=JX_WIN32_TETHER_VERSION;
    if(copy_w(tether->installed,MAX_PATH,installed)!=0||copy_w(tether->candidate,MAX_PATH,candidate)!=0||copy_w(tether->helper,MAX_PATH,helper)!=0)return -1;
    int n=_snwprintf(tether->previous,MAX_PATH,L"%ls.previous",installed);
    return n>0&&n<MAX_PATH?0:-1;
}
int jx_win32_exe_tether_arm(const jx_win32_exe_tether *tether,DWORD owner_pid){
    if(!tether||tether->version!=JX_WIN32_TETHER_VERSION||owner_pid==0)return -1;
    wchar_t cmd[4*MAX_PATH+128];
    int n=_snwprintf(cmd,sizeof cmd/sizeof cmd[0],L"\"%ls\" %lu \"%ls\" \"%ls\" \"%ls\"",
        tether->helper,(unsigned long)owner_pid,tether->installed,tether->candidate,tether->previous);
    if(n<=0||(size_t)n>=sizeof cmd/sizeof cmd[0])return -1;
    STARTUPINFOW si; PROCESS_INFORMATION pi; ZeroMemory(&si,sizeof si); ZeroMemory(&pi,sizeof pi); si.cb=sizeof si;
    if(!CreateProcessW(NULL,cmd,NULL,NULL,FALSE,CREATE_NO_WINDOW,NULL,NULL,&si,&pi))return -2;
    CloseHandle(pi.hThread); CloseHandle(pi.hProcess); return 0;
}
