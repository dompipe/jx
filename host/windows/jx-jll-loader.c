#include "jx-jll-loader.h"

#include <string.h>

static void reset_module(jx_jll_module *module) {
    if (!module) return;
    memset(module, 0, sizeof(*module));
    module->file = INVALID_HANDLE_VALUE;
}

int jx_jll_load(const char *path, jx_jll_module *out) {
    LARGE_INTEGER size;
    HANDLE file;
    HANDLE mapping;
    void *file_map;
    jx_native_section_view code;
    jx_native_section_view unsupported;
    DWORD old_protect = 0;
    int rc;

    if (!path || !out) return -1;
    reset_module(out);

    file = CreateFileA(path, GENERIC_READ, FILE_SHARE_READ, NULL, OPEN_EXISTING, FILE_ATTRIBUTE_NORMAL, NULL);
    if (file == INVALID_HANDLE_VALUE) return -2;
    if (!GetFileSizeEx(file, &size) || size.QuadPart <= 0 || (uint64_t)size.QuadPart > (uint64_t)SIZE_MAX) {
        CloseHandle(file);
        return -3;
    }

    mapping = CreateFileMappingA(file, NULL, PAGE_READONLY, 0, 0, NULL);
    if (!mapping) {
        CloseHandle(file);
        return -4;
    }
    file_map = MapViewOfFile(mapping, FILE_MAP_READ, 0, 0, 0);
    if (!file_map) {
        CloseHandle(mapping);
        CloseHandle(file);
        return -5;
    }

    rc = jx_native_image_open(file_map, (size_t)size.QuadPart, &out->image);
    if (rc != 0) {
        UnmapViewOfFile(file_map);
        CloseHandle(mapping);
        CloseHandle(file);
        reset_module(out);
        return -6;
    }
    if ((out->image.flags & JX_NATIVE_FLAG_LIBRARY) == 0 || out->image.has_entrypoint) {
        UnmapViewOfFile(file_map);
        CloseHandle(mapping);
        CloseHandle(file);
        reset_module(out);
        return -7;
    }
    if (out->image.architecture != JX_NATIVE_ARCH_X86_64_WIN64) {
        UnmapViewOfFile(file_map);
        CloseHandle(mapping);
        CloseHandle(file);
        reset_module(out);
        return -8;
    }

    if (jx_native_image_section(&out->image, "IMPORTS", &unsupported) == 0 && unsupported.size != 0) {
        UnmapViewOfFile(file_map);
        CloseHandle(mapping);
        CloseHandle(file);
        reset_module(out);
        return -9;
    }
    if (jx_native_image_section(&out->image, "RELOCATIONS", &unsupported) == 0 && unsupported.size != 0) {
        UnmapViewOfFile(file_map);
        CloseHandle(mapping);
        CloseHandle(file);
        reset_module(out);
        return -10;
    }
    if (jx_native_image_section(&out->image, "CODE", &code) != 0 || code.size == 0) {
        UnmapViewOfFile(file_map);
        CloseHandle(mapping);
        CloseHandle(file);
        reset_module(out);
        return -11;
    }

    out->code_map = (uint8_t *)VirtualAlloc(NULL, code.size, MEM_COMMIT | MEM_RESERVE, PAGE_READWRITE);
    if (!out->code_map) {
        UnmapViewOfFile(file_map);
        CloseHandle(mapping);
        CloseHandle(file);
        reset_module(out);
        return -12;
    }
    memcpy(out->code_map, code.data, code.size);
    if (!VirtualProtect(out->code_map, code.size, PAGE_EXECUTE_READ, &old_protect)) {
        VirtualFree(out->code_map, 0, MEM_RELEASE);
        UnmapViewOfFile(file_map);
        CloseHandle(mapping);
        CloseHandle(file);
        reset_module(out);
        return -13;
    }
    FlushInstructionCache(GetCurrentProcess(), out->code_map, code.size);

    out->file = file;
    out->file_mapping = mapping;
    out->file_map = (const uint8_t *)file_map;
    out->file_size = (size_t)size.QuadPart;
    out->code_size = code.size;
    return 0;
}

void jx_jll_unload(jx_jll_module *module) {
    if (!module) return;
    if (module->code_map) VirtualFree(module->code_map, 0, MEM_RELEASE);
    if (module->file_map) UnmapViewOfFile(module->file_map);
    if (module->file_mapping) CloseHandle(module->file_mapping);
    if (module->file != INVALID_HANDLE_VALUE && module->file) CloseHandle(module->file);
    reset_module(module);
}

int jx_jll_export_info(const jx_jll_module *module, const char *name, jx_native_export_view *out) {
    if (!module || !module->file_map || !name || !out) return -1;
    return jx_native_image_find_export(&module->image, name, out);
}

void *jx_jll_export(const jx_jll_module *module, const char *name) {
    jx_native_export_view export_view;
    if (!module || !module->code_map || !name) return NULL;
    if (jx_native_image_find_export(&module->image, name, &export_view) != 0) return NULL;
    if (export_view.code_offset >= module->code_size) return NULL;
    return (void *)(module->code_map + (size_t)export_view.code_offset);
}

int jx_jll_signature(const jx_jll_module *module, uint32_t signature_id, jx_native_signature_view *out) {
    if (!module || !module->file_map || !out) return -1;
    return jx_native_image_signature_at(&module->image, signature_id, out);
}
