#ifndef JX11_PATCH_MODULE_H
#define JX11_PATCH_MODULE_H

#include <stddef.h>
#include <stdint.h>

#define JX11_PATCH_MODULE_ABI_VERSION 1u
#define JX11_PATCH_MODULE_SYMBOL "jx11_patch_module_v1"

typedef struct jx11_patch_host_v1 {
    uint32_t abi_version;
    uint32_t struct_size;
    void (*log)(int level, const char *message);
    void (*set_background_rgb)(uint32_t rgb);
    void (*invalidate_desktop)(void);
    uint64_t (*active_generation)(void);
} jx11_patch_host_v1;

typedef struct jx11_patch_module_v1 {
    uint32_t abi_version;
    uint32_t struct_size;
    const char *name;
    uint64_t flags;
    void (*activate)(const jx11_patch_host_v1 *host);
    void (*deactivate)(const jx11_patch_host_v1 *host);
    void (*safe_point)(const jx11_patch_host_v1 *host);
    /* Return nonzero to deliver the event to the canonical JX11 core, zero to drop it. */
    int (*filter_x_event)(const jx11_patch_host_v1 *host, uint8_t response_type, void *event);
} jx11_patch_module_v1;

typedef const jx11_patch_module_v1 *(*jx11_patch_module_entry_fn)(void);

typedef struct {
    void *handle;
    const jx11_patch_module_v1 *module;
} jx11_loaded_patch_module;

int jx11_patch_module_load(const uint8_t *bytes, size_t length, jx11_loaded_patch_module *out);
void jx11_patch_module_unload(jx11_loaded_patch_module *loaded);

#endif
