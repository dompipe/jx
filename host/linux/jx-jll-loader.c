#include "jx-jll-loader.h"

#include <fcntl.h>
#include <string.h>
#include <sys/mman.h>
#include <sys/stat.h>
#include <unistd.h>

static void reset_module(jx_jll_module *module) {
    if (!module) return;
    memset(module, 0, sizeof(*module));
    module->fd = -1;
}

int jx_jll_load(const char *path, jx_jll_module *out) {
    struct stat st;
    jx_native_section_view code;
    jx_native_section_view unsupported;
    void *file_map;
    void *code_map;
    int fd;
    int rc;

    if (!path || !out) return -1;
    reset_module(out);

    fd = open(path, O_RDONLY);
    if (fd < 0) return -2;
    if (fstat(fd, &st) != 0 || st.st_size <= 0) {
        close(fd);
        return -3;
    }

    file_map = mmap(NULL, (size_t)st.st_size, PROT_READ, MAP_PRIVATE, fd, 0);
    if (file_map == MAP_FAILED) {
        close(fd);
        return -4;
    }

    rc = jx_native_image_open(file_map, (size_t)st.st_size, &out->image);
    if (rc != 0) {
        munmap(file_map, (size_t)st.st_size);
        close(fd);
        reset_module(out);
        return -5;
    }
    if ((out->image.flags & JX_NATIVE_FLAG_LIBRARY) == 0 || out->image.has_entrypoint) {
        munmap(file_map, (size_t)st.st_size);
        close(fd);
        reset_module(out);
        return -6;
    }
    if (out->image.architecture != JX_NATIVE_ARCH_X86_64_SYSV) {
        munmap(file_map, (size_t)st.st_size);
        close(fd);
        reset_module(out);
        return -7;
    }

    /* Relocations/imports are deliberately fail-closed until native linking is admitted. */
    if (jx_native_image_section(&out->image, "IMPORTS", &unsupported) == 0 && unsupported.size != 0) {
        munmap(file_map, (size_t)st.st_size);
        close(fd);
        reset_module(out);
        return -8;
    }
    if (jx_native_image_section(&out->image, "RELOCATIONS", &unsupported) == 0 && unsupported.size != 0) {
        munmap(file_map, (size_t)st.st_size);
        close(fd);
        reset_module(out);
        return -9;
    }

    if (jx_native_image_section(&out->image, "CODE", &code) != 0 || code.size == 0) {
        munmap(file_map, (size_t)st.st_size);
        close(fd);
        reset_module(out);
        return -10;
    }

    code_map = mmap(NULL, code.size, PROT_READ | PROT_WRITE, MAP_PRIVATE | MAP_ANONYMOUS, -1, 0);
    if (code_map == MAP_FAILED) {
        munmap(file_map, (size_t)st.st_size);
        close(fd);
        reset_module(out);
        return -11;
    }
    memcpy(code_map, code.data, code.size);
    if (mprotect(code_map, code.size, PROT_READ | PROT_EXEC) != 0) {
        munmap(code_map, code.size);
        munmap(file_map, (size_t)st.st_size);
        close(fd);
        reset_module(out);
        return -12;
    }

    out->fd = fd;
    out->file_map = (const uint8_t *)file_map;
    out->file_size = (size_t)st.st_size;
    out->code_map = (uint8_t *)code_map;
    out->code_size = code.size;
    return 0;
}

void jx_jll_unload(jx_jll_module *module) {
    if (!module) return;
    if (module->code_map && module->code_size) munmap(module->code_map, module->code_size);
    if (module->file_map && module->file_size) munmap((void *)module->file_map, module->file_size);
    if (module->fd >= 0) close(module->fd);
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
