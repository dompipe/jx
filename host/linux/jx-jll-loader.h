#ifndef JX_JLL_LOADER_H
#define JX_JLL_LOADER_H

#include <stddef.h>
#include <stdint.h>

#include "../common/jx-native-image.h"

#ifdef __cplusplus
extern "C" {
#endif

typedef struct jx_jll_module {
    int fd;
    const uint8_t *file_map;
    size_t file_size;
    uint8_t *code_map;
    size_t code_size;
    jx_native_image_view image;
} jx_jll_module;

/*
 * Load one x86-64 SysV JLL.
 *
 * The file is mmap'd read-only for metadata, CODE is copied once into anonymous
 * writable memory, then mprotect'd RX (W^X). Current direct-encoder JLLs are
 * position-independent for their admitted instruction subset. Imports and
 * relocation sections are rejected until those native link steps are added.
 */
int jx_jll_load(const char *path, jx_jll_module *out);
void jx_jll_unload(jx_jll_module *module);

/* Resolve an exported native function address. Returns NULL when absent. */
void *jx_jll_export(const jx_jll_module *module, const char *name);

/* Resolve metadata without producing an executable pointer. */
int jx_jll_export_info(const jx_jll_module *module, const char *name, jx_native_export_view *out);
int jx_jll_signature(const jx_jll_module *module, uint32_t signature_id, jx_native_signature_view *out);

#ifdef __cplusplus
}
#endif

#endif
