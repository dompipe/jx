#include "../common/jx-native-image.h"

#include <stdint.h>
#include <stdio.h>
#include <string.h>
#include <windows.h>

/* Minimal Win64 JXL launcher for the current no-import/no-relocation profile. */
int main(int argc, char **argv) {
    HANDLE file = INVALID_HANDLE_VALUE;
    HANDLE mapping = NULL;
    void *file_map = NULL;
    uint8_t *code_map = NULL;
    LARGE_INTEGER size;
    jx_native_image_view image;
    jx_native_section_view code;
    jx_native_section_view unsupported;
    DWORD old_protect = 0;
    int64_t (__cdecl *entry)(void);
    int64_t result;
    int rc = 1;

    if (argc != 2) {
        fprintf(stderr, "usage: %s program.jxl\n", argv[0]);
        return 2;
    }

    file = CreateFileA(argv[1], GENERIC_READ, FILE_SHARE_READ, NULL, OPEN_EXISTING, FILE_ATTRIBUTE_NORMAL, NULL);
    if (file == INVALID_HANDLE_VALUE) { fprintf(stderr, "cannot open JXL\n"); goto done; }
    if (!GetFileSizeEx(file, &size) || size.QuadPart <= 0 || (uint64_t)size.QuadPart > (uint64_t)SIZE_MAX) {
        fprintf(stderr, "invalid JXL file size\n"); goto done;
    }

    mapping = CreateFileMappingA(file, NULL, PAGE_READONLY, 0, 0, NULL);
    if (!mapping) { fprintf(stderr, "cannot map JXL\n"); goto done; }
    file_map = MapViewOfFile(mapping, FILE_MAP_READ, 0, 0, 0);
    if (!file_map) { fprintf(stderr, "cannot map JXL view\n"); goto done; }

    if (jx_native_image_open(file_map, (size_t)size.QuadPart, &image) != 0) {
        fprintf(stderr, "invalid JXL native image\n"); goto done;
    }
    if ((image.flags & JX_NATIVE_FLAG_EXECUTABLE) == 0 || !image.has_entrypoint) {
        fprintf(stderr, "image has no executable entrypoint\n"); goto done;
    }
    if (image.architecture != JX_NATIVE_ARCH_X86_64_WIN64) {
        fprintf(stderr, "unsupported JXL architecture %u\n", image.architecture); goto done;
    }
    if (jx_native_image_section(&image, "IMPORTS", &unsupported) == 0 && unsupported.size != 0) {
        fprintf(stderr, "imports are not admitted by this launcher yet\n"); goto done;
    }
    if (jx_native_image_section(&image, "RELOCATIONS", &unsupported) == 0 && unsupported.size != 0) {
        fprintf(stderr, "relocations are not admitted by this launcher yet\n"); goto done;
    }
    if (jx_native_image_section(&image, "CODE", &code) != 0 || code.size == 0 || image.entrypoint >= code.size) {
        fprintf(stderr, "invalid CODE/entrypoint\n"); goto done;
    }

    code_map = (uint8_t *)VirtualAlloc(NULL, code.size, MEM_COMMIT | MEM_RESERVE, PAGE_READWRITE);
    if (!code_map) { fprintf(stderr, "cannot allocate CODE\n"); goto done; }
    memcpy(code_map, code.data, code.size);
    if (!VirtualProtect(code_map, code.size, PAGE_EXECUTE_READ, &old_protect)) {
        fprintf(stderr, "cannot protect CODE RX\n"); goto done;
    }
    FlushInstructionCache(GetCurrentProcess(), code_map, code.size);

    entry = (int64_t (__cdecl *)(void))(code_map + (size_t)image.entrypoint);
    result = entry();
    printf("%lld\n", (long long)result);
    rc = 0;

done:
    if (code_map) VirtualFree(code_map, 0, MEM_RELEASE);
    if (file_map) UnmapViewOfFile(file_map);
    if (mapping) CloseHandle(mapping);
    if (file != INVALID_HANDLE_VALUE) CloseHandle(file);
    return rc;
}
