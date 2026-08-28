#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#include <wchar.h>
#include <stdlib.h>

static int valid_pe(const wchar_t *path){
    HANDLE h=CreateFileW(path,GENERIC_READ,FILE_SHARE_READ,NULL,OPEN_EXISTING,FILE_ATTRIBUTE_NORMAL,NULL);
    if(h==INVALID_HANDLE_VALUE)return 0; unsigned char mz[2]={0}; DWORD got=0; BOOL ok=ReadFile(h,mz,2,&got,NULL); CloseHandle(h); return ok&&got==2&&mz[0]=='M'&&mz[1]=='Z';
}
int wmain(int argc,wchar_t **argv){
    if(argc!=5)return 64; DWORD pid=(DWORD)wcstoul(argv[1],NULL,10); if(!pid||!valid_pe(argv[3]))return 65;
    HANDLE owner=OpenProcess(SYNCHRONIZE,FALSE,pid); if(owner){ WaitForSingleObject(owner,INFINITE); CloseHandle(owner); }
    DeleteFileW(argv[4]);
    if(!MoveFileExW(argv[2],argv[4],MOVEFILE_REPLACE_EXISTING|MOVEFILE_WRITE_THROUGH))return 66;
    if(!MoveFileExW(argv[3],argv[2],MOVEFILE_REPLACE_EXISTING|MOVEFILE_WRITE_THROUGH)){
        MoveFileExW(argv[4],argv[2],MOVEFILE_REPLACE_EXISTING|MOVEFILE_WRITE_THROUGH); return 67;
    }
    return 0;
}
