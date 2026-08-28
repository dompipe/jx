#define _GNU_SOURCE
#include "jx11-patch-module.h"
#include <dlfcn.h>
#include <errno.h>
#include <fcntl.h>
#include <stdio.h>
#include <string.h>
#include <sys/mman.h>
#include <unistd.h>

static int write_all_fd(int fd, const uint8_t *bytes, size_t length) {
    size_t at = 0u;
    while (at < length) {
        ssize_t n = write(fd, bytes + at, length - at);
        if (n < 0) { if (errno == EINTR) continue; return -1; }
        if (n == 0) return -1;
        at += (size_t)n;
    }
    return 0;
}

int jx11_patch_module_load(const uint8_t *bytes, size_t length, jx11_loaded_patch_module *out) {
    if (!bytes || length == 0u || !out) return -1;
    memset(out, 0, sizeof *out);
    int fd = memfd_create("jx11-live-patch", MFD_CLOEXEC);
    if (fd < 0) return -2;
    if (write_all_fd(fd, bytes, length) != 0) { close(fd); return -3; }
    if (lseek(fd, 0, SEEK_SET) < 0) { close(fd); return -3; }

    char path[64];
    int n = snprintf(path, sizeof path, "/proc/self/fd/%d", fd);
    if (n <= 0 || (size_t)n >= sizeof path) { close(fd); return -4; }
    void *handle = dlopen(path, RTLD_NOW | RTLD_LOCAL);
    close(fd);
    if (!handle) return -5;

    dlerror();
    jx11_patch_module_entry_fn entry = (jx11_patch_module_entry_fn)dlsym(handle, JX11_PATCH_MODULE_SYMBOL);
    const char *error = dlerror();
    if (error || !entry) { dlclose(handle); return -6; }
    const jx11_patch_module_v1 *module = entry();
    if (!module || module->abi_version != JX11_PATCH_MODULE_ABI_VERSION ||
        module->struct_size < sizeof(jx11_patch_module_v1) || !module->name || !*module->name) {
        dlclose(handle);
        return -7;
    }
    out->handle = handle;
    out->module = module;
    return 0;
}

void jx11_patch_module_unload(jx11_loaded_patch_module *loaded) {
    if (!loaded) return;
    if (loaded->handle) dlclose(loaded->handle);
    loaded->handle = NULL;
    loaded->module = NULL;
}
