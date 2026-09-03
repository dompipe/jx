#ifndef JX_NATIVE_IMAGE_H
#define JX_NATIVE_IMAGE_H

#include <stddef.h>
#include <stdint.h>

#ifdef __cplusplus
extern "C" {
#endif

#define JX_NATIVE_IMAGE_VERSION 1u
#define JX_NATIVE_IMAGE_HEADER_SIZE 40u
#define JX_NATIVE_IMAGE_DIRECTORY_ENTRY_SIZE 32u

#define JX_NATIVE_FLAG_EXECUTABLE 0x0001u
#define JX_NATIVE_FLAG_LIBRARY 0x0002u
#define JX_NATIVE_FLAG_EXPORTS 0x0004u
#define JX_NATIVE_FLAG_IMPORTS 0x0008u
#define JX_NATIVE_FLAG_RELOCATABLE 0x0010u

#define JX_NATIVE_ARCH_X86_64_SYSV 1u
#define JX_NATIVE_ARCH_X86_64_WIN64 2u
#define JX_NATIVE_ARCH_AARCH64 3u

typedef struct jx_native_image_view {
    const uint8_t *bytes;
    size_t size;
    uint32_t version;
    uint32_t architecture;
    uint32_t flags;
    uint32_t section_count;
    int has_entrypoint;
    uint64_t entrypoint;
    const uint8_t *directory;
    size_t directory_size;
    const uint8_t *payload;
    size_t payload_size;
} jx_native_image_view;

typedef struct jx_native_section_view {
    const uint8_t *data;
    size_t size;
} jx_native_section_view;

typedef struct jx_native_export_view {
    const char *name;
    uint64_t code_offset;
    uint32_t signature_id;
    uint32_t flags;
} jx_native_export_view;

typedef struct jx_native_signature_view {
    const uint8_t *record;
    const uint8_t *strings;
    size_t strings_size;
    const char *return_type;
    uint16_t param_count;
} jx_native_signature_view;

/* Returns 0 on success, negative on malformed/unsupported image bytes. */
int jx_native_image_open(const void *bytes, size_t size, jx_native_image_view *out);

/* Find a named section such as CODE, DATA, EXPORTS, SIGNATURES, STRINGS. */
int jx_native_image_section(const jx_native_image_view *image, const char *name, jx_native_section_view *out);

/* Public function metadata. */
int jx_native_image_export_count(const jx_native_image_view *image, uint32_t *out_count);
int jx_native_image_export_at(const jx_native_image_view *image, uint32_t index, jx_native_export_view *out);
int jx_native_image_find_export(const jx_native_image_view *image, const char *name, jx_native_export_view *out);

/* Public signature metadata. Signature ids are the ids stored in export rows. */
int jx_native_image_signature_at(const jx_native_image_view *image, uint32_t signature_id, jx_native_signature_view *out);
const char *jx_native_signature_param(const jx_native_signature_view *signature, uint16_t index);

#ifdef __cplusplus
}
#endif

#endif
