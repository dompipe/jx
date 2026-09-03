#include "jx-native-image.h"

#include <string.h>

static uint16_t rd16(const uint8_t *p) {
    return (uint16_t)p[0] | ((uint16_t)p[1] << 8);
}

static uint32_t rd32(const uint8_t *p) {
    return (uint32_t)p[0]
        | ((uint32_t)p[1] << 8)
        | ((uint32_t)p[2] << 16)
        | ((uint32_t)p[3] << 24);
}

static uint64_t rd64(const uint8_t *p) {
    return (uint64_t)rd32(p) | ((uint64_t)rd32(p + 4) << 32);
}

static int section_name_equals(const uint8_t *field, const char *name) {
    size_t n = strlen(name);
    if (n == 0 || n > 16) return 0;
    if (memcmp(field, name, n) != 0) return 0;
    if (n == 16) return 1;
    return field[n] == 0;
}

int jx_native_image_open(const void *bytes, size_t size, jx_native_image_view *out) {
    static const uint8_t magic[8] = {'J','X','N','I',1,0,0,0};
    const uint8_t *p = (const uint8_t *)bytes;
    uint32_t version;
    uint32_t section_count;
    uint32_t dir_size;
    uint64_t entry;
    size_t payload_at;

    if (!p || !out || size < JX_NATIVE_IMAGE_HEADER_SIZE) return -1;
    if (memcmp(p, magic, sizeof(magic)) != 0) return -2;

    version = rd32(p + 8);
    if (version != JX_NATIVE_IMAGE_VERSION) return -3;

    section_count = rd32(p + 20);
    dir_size = rd32(p + 32);
    if ((uint64_t)section_count * JX_NATIVE_IMAGE_DIRECTORY_ENTRY_SIZE != dir_size) return -4;

    payload_at = JX_NATIVE_IMAGE_HEADER_SIZE + (size_t)dir_size;
    if (payload_at > size) return -5;

    entry = rd64(p + 24);

    memset(out, 0, sizeof(*out));
    out->bytes = p;
    out->size = size;
    out->version = version;
    out->architecture = rd32(p + 12);
    out->flags = rd32(p + 16);
    out->section_count = section_count;
    out->has_entrypoint = entry != UINT64_MAX;
    out->entrypoint = out->has_entrypoint ? entry : 0;
    out->directory = p + JX_NATIVE_IMAGE_HEADER_SIZE;
    out->directory_size = dir_size;
    out->payload = p + payload_at;
    out->payload_size = size - payload_at;

    /* Validate all directory rows once at admission. */
    for (uint32_t i = 0; i < section_count; ++i) {
        const uint8_t *row = out->directory + ((size_t)i * JX_NATIVE_IMAGE_DIRECTORY_ENTRY_SIZE);
        uint64_t offset = rd64(row + 16);
        uint64_t length = rd64(row + 24);
        size_t name_end = 16;

        if (row[0] == 0) return -6;
        for (size_t j = 0; j < 16; ++j) {
            uint8_t c = row[j];
            if (c == 0) { name_end = j; break; }
            if (!((c >= 'A' && c <= 'Z') || (c >= '0' && c <= '9') || c == '_')) return -6;
        }
        /* Short names are NUL padded; reject hidden bytes after the terminator. */
        if (name_end < 16) {
            for (size_t j = name_end; j < 16; ++j) if (row[j] != 0) return -6;
        }
        if (offset > out->payload_size || length > out->payload_size - (size_t)offset) return -7;

        for (uint32_t k = 0; k < i; ++k) {
            const uint8_t *other = out->directory + ((size_t)k * JX_NATIVE_IMAGE_DIRECTORY_ENTRY_SIZE);
            if (memcmp(row, other, 16) == 0) return -8;
        }
    }

    {
        jx_native_section_view code;
        if (jx_native_image_section(out, "CODE", &code) != 0) return -9;
        if (out->has_entrypoint && out->entrypoint >= code.size) return -10;
    }
    return 0;
}

int jx_native_image_section(const jx_native_image_view *image, const char *name, jx_native_section_view *out) {
    if (!image || !name || !out) return -1;
    for (uint32_t i = 0; i < image->section_count; ++i) {
        const uint8_t *row = image->directory + ((size_t)i * JX_NATIVE_IMAGE_DIRECTORY_ENTRY_SIZE);
        if (!section_name_equals(row, name)) continue;
        {
            uint64_t offset = rd64(row + 16);
            uint64_t length = rd64(row + 24);
            if (offset > image->payload_size || length > image->payload_size - (size_t)offset) return -2;
            out->data = image->payload + (size_t)offset;
            out->size = (size_t)length;
            return 0;
        }
    }
    return 1;
}

static const char *string_at(const jx_native_section_view *strings, uint32_t offset) {
    const uint8_t *end;
    if (!strings || offset >= strings->size) return NULL;
    end = memchr(strings->data + offset, 0, strings->size - offset);
    if (!end) return NULL;
    return (const char *)(strings->data + offset);
}

int jx_native_image_export_count(const jx_native_image_view *image, uint32_t *out_count) {
    jx_native_section_view exports;
    uint32_t count;
    if (!out_count) return -1;
    if (jx_native_image_section(image, "EXPORTS", &exports) != 0) {
        *out_count = 0;
        return 0;
    }
    if (exports.size < 4) return -2;
    count = rd32(exports.data);
    if ((uint64_t)4 + ((uint64_t)count * 24u) != exports.size) return -3;
    *out_count = count;
    return 0;
}

int jx_native_image_export_at(const jx_native_image_view *image, uint32_t index, jx_native_export_view *out) {
    jx_native_section_view exports;
    jx_native_section_view strings;
    jx_native_section_view code;
    uint32_t count;
    const uint8_t *row;
    const char *name;

    if (!image || !out) return -1;
    if (jx_native_image_section(image, "EXPORTS", &exports) != 0) return 1;
    if (jx_native_image_section(image, "STRINGS", &strings) != 0) return -2;
    if (jx_native_image_section(image, "CODE", &code) != 0) return -3;
    if (exports.size < 4) return -4;

    count = rd32(exports.data);
    if (index >= count || (uint64_t)4 + ((uint64_t)count * 24u) != exports.size) return -5;
    row = exports.data + 4 + ((size_t)index * 24u);
    name = string_at(&strings, rd32(row));
    if (!name) return -6;

    out->name = name;
    out->signature_id = rd32(row + 4);
    out->code_offset = rd64(row + 8);
    out->flags = rd32(row + 16);
    if (out->code_offset >= code.size) return -7;
    return 0;
}

int jx_native_image_find_export(const jx_native_image_view *image, const char *name, jx_native_export_view *out) {
    uint32_t count = 0;
    if (!image || !name || !out) return -1;
    if (jx_native_image_export_count(image, &count) != 0) return -2;
    for (uint32_t i = 0; i < count; ++i) {
        jx_native_export_view candidate;
        if (jx_native_image_export_at(image, i, &candidate) != 0) return -3;
        if (strcmp(candidate.name, name) == 0) {
            *out = candidate;
            return 0;
        }
    }
    return 1;
}

int jx_native_image_signature_at(const jx_native_image_view *image, uint32_t signature_id, jx_native_signature_view *out) {
    jx_native_section_view signatures;
    jx_native_section_view strings;
    const uint8_t *p;
    const uint8_t *end;
    uint32_t count;

    if (!image || !out) return -1;
    if (jx_native_image_section(image, "SIGNATURES", &signatures) != 0) return 1;
    if (jx_native_image_section(image, "STRINGS", &strings) != 0) return -2;
    if (signatures.size < 4) return -3;
    count = rd32(signatures.data);
    if (signature_id >= count) return 1;

    p = signatures.data + 4;
    end = signatures.data + signatures.size;
    for (uint32_t i = 0; i < count; ++i) {
        uint32_t return_offset;
        uint16_t param_count;
        size_t bytes_needed;
        if ((size_t)(end - p) < 8) return -4;
        return_offset = rd32(p);
        param_count = rd16(p + 4);
        bytes_needed = 8u + ((size_t)param_count * 4u);
        if ((size_t)(end - p) < bytes_needed) return -5;
        if (i == signature_id) {
            const char *ret = string_at(&strings, return_offset);
            if (!ret) return -6;
            out->record = p;
            out->strings = strings.data;
            out->strings_size = strings.size;
            out->return_type = ret;
            out->param_count = param_count;
            return 0;
        }
        p += bytes_needed;
    }
    return 1;
}

const char *jx_native_signature_param(const jx_native_signature_view *signature, uint16_t index) {
    uint32_t offset;
    const uint8_t *start;
    const uint8_t *end;
    if (!signature || !signature->record || index >= signature->param_count) return NULL;
    offset = rd32(signature->record + 8 + ((size_t)index * 4u));
    if (offset >= signature->strings_size) return NULL;
    start = signature->strings + offset;
    end = memchr(start, 0, signature->strings_size - offset);
    if (!end) return NULL;
    return (const char *)start;
}
