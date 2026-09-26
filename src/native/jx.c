#include <errno.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#define JX_VERSION "0.4.1-portable-win32-posix-emitter"

typedef struct {
    unsigned char *bytes;
    size_t length;
} JxBuffer;

typedef struct {
    char *source_path;
    char *runtime_path;
    JxBuffer bytes;
} JxBundledFile;

typedef struct {
    JxBundledFile *items;
    size_t count;
    size_t capacity;
} JxBundle;

typedef struct {
    const char *input_path;
    const char *output_path;
    const char *asset_path;
    int inferred_asset;
    int window_mode;
} JxOptions;

static void jx_die(const char *message) {
    fprintf(stderr, "jx: %s\n", message);
    exit(1);
}

static void jx_die_errno(const char *message) {
    fprintf(stderr, "jx: %s: %s\n", message, strerror(errno));
    exit(1);
}

static void jx_usage(FILE *stream) {
    fprintf(stream,
        "JX %s\n"
        "\n"
        "Usage:\n"
        "  jx -o <output.c> <input.php>\n"
        "  jx --window -o <output.c> <input.php>\n"
        "  jx emit-c <input.php> -o <output.c>\n"
        "  jx emit-c <input.php> --asset <style.css> -o <output.c>\n"
        "  jx emit-c <input.php> --asset <style.css> --window -o <output.c>\n"
        "  jx compile <input.php> -o <output.c>\n"
        "  jx --version\n"
        "\n"
        "The emitted .c file is GCC-compilable executable code.\n"
        "It contains a Windows/POSIX runtime bridge for the embedded PHP payload.\n",
        JX_VERSION);
}

static const char *jx_basename(const char *path) {
    const char *slash = strrchr(path, '/');
    const char *backslash = strrchr(path, '\\');
    const char *last = slash;
    if (!last || (backslash && backslash > last)) {
        last = backslash;
    }
    return last ? last + 1 : path;
}

static JxBuffer jx_read_file(const char *path) {
    FILE *fp = fopen(path, "rb");
    if (!fp) {
        jx_die_errno("cannot open input");
    }

    if (fseek(fp, 0, SEEK_END) != 0) {
        fclose(fp);
        jx_die_errno("cannot seek input");
    }

    long size = ftell(fp);
    if (size < 0) {
        fclose(fp);
        jx_die_errno("cannot measure input");
    }

    if (fseek(fp, 0, SEEK_SET) != 0) {
        fclose(fp);
        jx_die_errno("cannot rewind input");
    }

    unsigned char *bytes = NULL;
    if (size > 0) {
        bytes = (unsigned char *)malloc((size_t)size);
        if (!bytes) {
            fclose(fp);
            jx_die("out of memory");
        }

        size_t read_count = fread(bytes, 1, (size_t)size, fp);
        if (read_count != (size_t)size) {
            free(bytes);
            fclose(fp);
            jx_die_errno("cannot read input");
        }
    }

    fclose(fp);

    JxBuffer buffer;
    buffer.bytes = bytes;
    buffer.length = (size_t)size;
    return buffer;
}

static void jx_write_c_string(FILE *out, const char *text) {
    fputc('"', out);
    for (const unsigned char *p = (const unsigned char *)text; *p; ++p) {
        switch (*p) {
            case '\\': fputs("\\\\", out); break;
            case '"': fputs("\\\"", out); break;
            case '\n': fputs("\\n", out); break;
            case '\r': fputs("\\r", out); break;
            case '\t': fputs("\\t", out); break;
            default:
                if (*p < 32 || *p > 126) {
                    fprintf(out, "\\x%02x", *p);
                } else {
                    fputc(*p, out);
                }
        }
    }
    fputc('"', out);
}

static void jx_write_c_bytes_as_string(FILE *out, const unsigned char *bytes, size_t length) {
    fputc('"', out);
    for (size_t i = 0; i < length; ++i) {
        unsigned char c = bytes[i];
        switch (c) {
            case '\\': fputs("\\\\", out); break;
            case '"': fputs("\\\"", out); break;
            case '\n': fputs("\\n", out); break;
            case '\r': fputs("\\r", out); break;
            case '\t': fputs("\\t", out); break;
            default:
                if (c < 32 || c > 126) {
                    fprintf(out, "\\x%02x", c);
                } else {
                    fputc(c, out);
                }
        }
    }
    fputc('"', out);
}

static int jx_file_exists(const char *path) {
    FILE *fp = fopen(path, "rb");
    if (!fp) {
        return 0;
    }
    fclose(fp);
    return 1;
}

static char *jx_copy_range(const char *text, size_t length) {
    char *copy = (char *)malloc(length + 1);
    if (!copy) {
        jx_die("out of memory");
    }
    memcpy(copy, text, length);
    copy[length] = '\0';
    return copy;
}

static char *jx_copy_text(const char *text) {
    return jx_copy_range(text, strlen(text));
}

static char *jx_dirname_alloc(const char *path) {
    const char *slash = strrchr(path, '/');
    const char *backslash = strrchr(path, '\\');
    const char *last = slash;
    if (!last || (backslash && backslash > last)) {
        last = backslash;
    }
    if (!last) {
        return jx_copy_text("");
    }
    return jx_copy_range(path, (size_t)(last - path));
}

static char *jx_join_path_alloc(const char *base, const char *name) {
    if (!base || !*base) {
        return jx_copy_text(name);
    }
    size_t base_len = strlen(base);
    size_t name_len = strlen(name);
    int needs_sep = base_len > 0 && base[base_len - 1] != '/' && base[base_len - 1] != '\\';
    char *path = (char *)malloc(base_len + (needs_sep ? 1 : 0) + name_len + 1);
    if (!path) {
        jx_die("out of memory");
    }
    memcpy(path, base, base_len);
    if (needs_sep) {
        path[base_len++] = '/';
    }
    memcpy(path + base_len, name, name_len + 1);
    return path;
}

static void jx_normalize_slashes(char *path) {
    for (char *p = path; *p; ++p) {
        if (*p == '\\') {
            *p = '/';
        }
    }
}

static char *jx_normalized_path_alloc(const char *path) {
    char *scratch = jx_copy_text(path);
    jx_normalize_slashes(scratch);

    size_t len = strlen(scratch);
    const char **segments = (const char **)calloc(len + 1, sizeof(char *));
    size_t *lengths = (size_t *)calloc(len + 1, sizeof(size_t));
    if (!segments || !lengths) {
        free(segments);
        free(lengths);
        free(scratch);
        jx_die("out of memory");
    }

    size_t count = 0;
    char *p = scratch;
    while (*p) {
        while (*p == '/') {
            ++p;
        }
        char *start = p;
        while (*p && *p != '/') {
            ++p;
        }
        size_t part_len = (size_t)(p - start);
        if (part_len == 0 || (part_len == 1 && start[0] == '.')) {
            continue;
        }
        if (part_len == 2 && start[0] == '.' && start[1] == '.') {
            if (count > 0 && !(lengths[count - 1] == 2 && segments[count - 1][0] == '.' && segments[count - 1][1] == '.')) {
                --count;
            } else {
                segments[count] = start;
                lengths[count++] = part_len;
            }
            continue;
        }
        segments[count] = start;
        lengths[count++] = part_len;
    }

    size_t out_len = 0;
    for (size_t i = 0; i < count; ++i) {
        out_len += lengths[i] + (i ? 1 : 0);
    }

    char *out = (char *)malloc(out_len + 1);
    if (!out) {
        free(segments);
        free(lengths);
        free(scratch);
        jx_die("out of memory");
    }
    size_t at = 0;
    for (size_t i = 0; i < count; ++i) {
        if (i) {
            out[at++] = '/';
        }
        memcpy(out + at, segments[i], lengths[i]);
        at += lengths[i];
    }
    out[at] = '\0';

    free(segments);
    free(lengths);
    free(scratch);
    return out;
}

static int jx_is_absolute_or_url_path(const char *path) {
    if (!path || !*path) {
        return 1;
    }
    if (path[0] == '/' || path[0] == '\\') {
        return 1;
    }
    if (((path[0] >= 'a' && path[0] <= 'z') || (path[0] >= 'A' && path[0] <= 'Z')) && path[1] == ':') {
        return 1;
    }
    return strstr(path, "://") != NULL;
}

static int jx_ascii_lower(int c) {
    if (c >= 'A' && c <= 'Z') {
        return c + ('a' - 'A');
    }
    return c;
}

static int jx_ends_with_ci(const char *text, const char *suffix) {
    size_t text_len = strlen(text);
    size_t suffix_len = strlen(suffix);
    if (suffix_len > text_len) {
        return 0;
    }
    const char *start = text + text_len - suffix_len;
    for (size_t i = 0; i < suffix_len; ++i) {
        if (jx_ascii_lower((unsigned char)start[i]) != jx_ascii_lower((unsigned char)suffix[i])) {
            return 0;
        }
    }
    return 1;
}

static void jx_strip_url_suffix(char *path) {
    for (char *p = path; *p; ++p) {
        if (*p == '?' || *p == '#') {
            *p = '\0';
            return;
        }
    }
}

static int jx_is_image_asset_path(const char *path) {
    char clean[1024];
    snprintf(clean, sizeof(clean), "%s", path ? path : "");
    jx_strip_url_suffix(clean);
    return jx_ends_with_ci(clean, ".png") ||
           jx_ends_with_ci(clean, ".jpg") ||
           jx_ends_with_ci(clean, ".jpeg") ||
           jx_ends_with_ci(clean, ".gif") ||
           jx_ends_with_ci(clean, ".webp") ||
           jx_ends_with_ci(clean, ".svg") ||
           jx_ends_with_ci(clean, ".ico") ||
           jx_ends_with_ci(clean, ".bmp") ||
           jx_ends_with_ci(clean, ".avif");
}

static char *jx_alloc_sibling_path(const char *input_path, const char *name) {
    const char *slash = strrchr(input_path, '/');
    const char *backslash = strrchr(input_path, '\\');
    const char *last = slash;
    if (!last || (backslash && backslash > last)) {
        last = backslash;
    }

    size_t dir_len = last ? (size_t)(last - input_path + 1) : 0;
    size_t name_len = strlen(name);
    char *path = (char *)malloc(dir_len + name_len + 1);
    if (!path) {
        jx_die("out of memory");
    }
    if (dir_len) {
        memcpy(path, input_path, dir_len);
    }
    memcpy(path + dir_len, name, name_len + 1);
    return path;
}

static void jx_infer_asset(JxOptions *opt) {
    if (opt->asset_path || !opt->input_path) {
        return;
    }

    char *candidate = jx_alloc_sibling_path(opt->input_path, "style.css");
    if (jx_file_exists(candidate)) {
        opt->asset_path = candidate;
        opt->inferred_asset = 1;
        return;
    }
    free(candidate);
}

static void jx_write_json_value_inside_c_string(FILE *out, const unsigned char *bytes, size_t length) {
    fputs("\\\"", out);
    for (size_t i = 0; i < length; ++i) {
        unsigned char c = bytes[i];
        switch (c) {
            case '\\': fputs("\\\\\\\\", out); break;
            case '"': fputs("\\\\\\\"", out); break;
            case '\n': fputs("\\\\n", out); break;
            case '\r': fputs("\\\\r", out); break;
            case '\t': fputs("\\\\t", out); break;
            default:
                if (c < 32) {
                    fprintf(out, "\\\\u%04x", c);
                } else if (c > 126) {
                    fprintf(out, "\\\\u%04x", c);
                } else {
                    fputc(c, out);
                }
        }
    }
    fputs("\\\"", out);
}

static int jx_is_ident_char(unsigned char c) {
    return (c >= 'a' && c <= 'z') ||
           (c >= 'A' && c <= 'Z') ||
           (c >= '0' && c <= '9') ||
           c == '_';
}

static int jx_slice_equals(const unsigned char *a, size_t a_len, const unsigned char *b, size_t b_len) {
    return a_len == b_len && memcmp(a, b, a_len) == 0;
}

static char *jx_copy_range(const char *text, size_t length);
static char *jx_copy_text(const char *text);

static int jx_source_contains(const JxBuffer *source, const char *needle) {
    size_t needle_len = strlen(needle);
    if (needle_len == 0 || needle_len > source->length) {
        return 0;
    }
    for (size_t i = 0; i + needle_len <= source->length; ++i) {
        if (memcmp(source->bytes + i, needle, needle_len) == 0) {
            return 1;
        }
    }
    return 0;
}

static char *jx_find_literal_call_alloc(const JxBuffer *source, const char *name, const char *fallback) {
    size_t name_len = strlen(name);
    for (size_t i = 0; i + name_len < source->length; ++i) {
        if (memcmp(source->bytes + i, name, name_len) != 0) {
            continue;
        }
        size_t p = i + name_len;
        while (p < source->length && (source->bytes[p] == ' ' || source->bytes[p] == '\t' || source->bytes[p] == '\r' || source->bytes[p] == '\n')) {
            ++p;
        }
        if (p >= source->length || source->bytes[p] != '(') {
            continue;
        }
        ++p;
        while (p < source->length && (source->bytes[p] == ' ' || source->bytes[p] == '\t' || source->bytes[p] == '\r' || source->bytes[p] == '\n')) {
            ++p;
        }
        if (p >= source->length || (source->bytes[p] != '\'' && source->bytes[p] != '"')) {
            continue;
        }
        unsigned char quote = source->bytes[p++];
        size_t start = p;
        while (p < source->length) {
            if (source->bytes[p] == '\\' && p + 1 < source->length) {
                p += 2;
                continue;
            }
            if (source->bytes[p] == quote) {
                return jx_copy_range((const char *)(source->bytes + start), p - start);
            }
            ++p;
        }
    }
    return jx_copy_text(fallback);
}

static void jx_bundle_init(JxBundle *bundle) {
    bundle->items = NULL;
    bundle->count = 0;
    bundle->capacity = 0;
}

static void jx_bundle_free(JxBundle *bundle) {
    for (size_t i = 0; i < bundle->count; ++i) {
        free(bundle->items[i].source_path);
        free(bundle->items[i].runtime_path);
        free(bundle->items[i].bytes.bytes);
    }
    free(bundle->items);
    bundle->items = NULL;
    bundle->count = 0;
    bundle->capacity = 0;
}

static int jx_bundle_has_source(const JxBundle *bundle, const char *source_path) {
    for (size_t i = 0; i < bundle->count; ++i) {
        if (strcmp(bundle->items[i].source_path, source_path) == 0) {
            return 1;
        }
    }
    return 0;
}

static int jx_include_keyword_len(const unsigned char *bytes, size_t length, size_t pos) {
    static const char *keywords[] = { "include_once", "require_once", "include", "require", NULL };
    if (pos > 0 && jx_is_ident_char(bytes[pos - 1])) {
        return 0;
    }
    for (size_t i = 0; keywords[i]; ++i) {
        size_t keyword_len = strlen(keywords[i]);
        if (pos + keyword_len <= length &&
            memcmp(bytes + pos, keywords[i], keyword_len) == 0 &&
            (pos + keyword_len == length || !jx_is_ident_char(bytes[pos + keyword_len]))) {
            return (int)keyword_len;
        }
    }
    return 0;
}

static int jx_scan_include_literal(
    const unsigned char *bytes,
    size_t length,
    size_t pos,
    char *out,
    size_t out_size,
    size_t *next_pos,
    size_t *literal_start,
    size_t *literal_end
) {
    int keyword_len = jx_include_keyword_len(bytes, length, pos);
    if (!keyword_len) {
        return 0;
    }
    size_t p = pos + (size_t)keyword_len;
    while (p < length && (bytes[p] == ' ' || bytes[p] == '\t' || bytes[p] == '\r' || bytes[p] == '\n')) {
        ++p;
    }
    if (p < length && bytes[p] == '(') {
        ++p;
        while (p < length && (bytes[p] == ' ' || bytes[p] == '\t' || bytes[p] == '\r' || bytes[p] == '\n')) {
            ++p;
        }
    }
    if (p >= length || (bytes[p] != '\'' && bytes[p] != '"')) {
        return 0;
    }
    unsigned char quote = bytes[p++];
    size_t value_start = p;
    size_t j = 0;
    while (p < length) {
        if (bytes[p] == '\\' && p + 1 < length) {
            if (j + 1 < out_size) {
                out[j++] = (char)bytes[p + 1];
            }
            p += 2;
            continue;
        }
        if (bytes[p] == quote) {
            out[j] = '\0';
            if (next_pos) {
                *next_pos = p + 1;
            }
            if (literal_start) {
                *literal_start = value_start;
            }
            if (literal_end) {
                *literal_end = p;
            }
            return j > 0;
        }
        if (j + 1 < out_size) {
            out[j++] = (char)bytes[p];
        }
        ++p;
    }
    return 0;
}

static JxBuffer jx_rewrite_static_include_paths(const JxBuffer *source, const char *runtime_path) {
    char *runtime_dir = jx_dirname_alloc(runtime_path);
    size_t cap = source->length + 1;
    unsigned char *out = (unsigned char *)malloc(cap);
    if (!out) {
        jx_die("out of memory");
    }
    size_t out_len = 0;
    size_t last = 0;

    for (size_t i = 0; i < source->length; ++i) {
        char include_path[1024];
        size_t next_pos = i + 1;
        size_t literal_start = 0;
        size_t literal_end = 0;
        if (!jx_scan_include_literal(source->bytes, source->length, i, include_path, sizeof(include_path), &next_pos, &literal_start, &literal_end) ||
            jx_is_absolute_or_url_path(include_path)) {
            continue;
        }

        char *joined = jx_join_path_alloc(runtime_dir, include_path);
        char *normalized = jx_normalized_path_alloc(joined);
        free(joined);

        size_t prefix_len = literal_start - last;
        size_t replacement_len = strlen(normalized);
        size_t need = out_len + prefix_len + replacement_len + (source->length - literal_end) + 1;
        if (need > cap) {
            while (need > cap) {
                cap *= 2;
            }
            unsigned char *next = (unsigned char *)realloc(out, cap);
            if (!next) {
                free(normalized);
                free(runtime_dir);
                free(out);
                jx_die("out of memory");
            }
            out = next;
        }
        memcpy(out + out_len, source->bytes + last, prefix_len);
        out_len += prefix_len;
        memcpy(out + out_len, normalized, replacement_len);
        out_len += replacement_len;
        last = literal_end;
        i = next_pos;
        free(normalized);
    }

    size_t tail_len = source->length - last;
    if (out_len + tail_len + 1 > cap) {
        unsigned char *next = (unsigned char *)realloc(out, out_len + tail_len + 1);
        if (!next) {
            free(runtime_dir);
            free(out);
            jx_die("out of memory");
        }
        out = next;
    }
    memcpy(out + out_len, source->bytes + last, tail_len);
    out_len += tail_len;
    out[out_len] = '\0';
    free(runtime_dir);

    JxBuffer rewritten;
    rewritten.bytes = out;
    rewritten.length = out_len;
    return rewritten;
}

static void jx_collect_includes_from_file(JxBundle *bundle, const char *source_path, const char *runtime_path, int depth);

static void jx_bundle_add_file(JxBundle *bundle, const char *source_path, const char *runtime_path, int depth);

static void jx_collect_asset_path(
    JxBundle *bundle,
    const char *source_dir,
    const char *runtime_dir,
    const char *asset_path,
    int depth
) {
    char clean[1024];
    snprintf(clean, sizeof(clean), "%s", asset_path ? asset_path : "");
    jx_strip_url_suffix(clean);
    if (!jx_is_image_asset_path(clean) || jx_is_absolute_or_url_path(clean)) {
        return;
    }

    char *source_asset = jx_join_path_alloc(source_dir, clean);
    char *runtime_asset = jx_join_path_alloc(runtime_dir, clean);
    jx_normalize_slashes(source_asset);
    jx_normalize_slashes(runtime_asset);
    if (jx_file_exists(source_asset)) {
        jx_bundle_add_file(bundle, source_asset, runtime_asset, depth);
    }
    free(source_asset);
    free(runtime_asset);
}

static void jx_collect_quoted_image_assets(
    JxBundle *bundle,
    const JxBuffer *source,
    const char *source_dir,
    const char *runtime_dir,
    int depth
) {
    for (size_t i = 0; i < source->length; ++i) {
        if (source->bytes[i] != '\'' && source->bytes[i] != '"') {
            continue;
        }
        unsigned char quote = source->bytes[i++];
        char path[1024];
        size_t j = 0;
        while (i < source->length) {
            if (source->bytes[i] == '\\' && i + 1 < source->length) {
                if (j + 1 < sizeof(path)) {
                    path[j++] = (char)source->bytes[i + 1];
                }
                i += 2;
                continue;
            }
            if (source->bytes[i] == quote) {
                path[j] = '\0';
                jx_collect_asset_path(bundle, source_dir, runtime_dir, path, depth);
                break;
            }
            if (j + 1 < sizeof(path)) {
                path[j++] = (char)source->bytes[i];
            }
            ++i;
        }
    }
}

static void jx_collect_css_url_assets(
    JxBundle *bundle,
    const JxBuffer *source,
    const char *source_dir,
    const char *runtime_dir,
    int depth
) {
    for (size_t i = 0; i + 4 < source->length; ++i) {
        if (jx_ascii_lower(source->bytes[i]) != 'u' ||
            jx_ascii_lower(source->bytes[i + 1]) != 'r' ||
            jx_ascii_lower(source->bytes[i + 2]) != 'l' ||
            source->bytes[i + 3] != '(') {
            continue;
        }
        size_t p = i + 4;
        while (p < source->length && (source->bytes[p] == ' ' || source->bytes[p] == '\t' || source->bytes[p] == '\r' || source->bytes[p] == '\n')) {
            ++p;
        }
        unsigned char quote = 0;
        if (p < source->length && (source->bytes[p] == '\'' || source->bytes[p] == '"')) {
            quote = source->bytes[p++];
        }
        char path[1024];
        size_t j = 0;
        while (p < source->length) {
            if (quote && source->bytes[p] == quote) {
                ++p;
                break;
            }
            if (!quote && source->bytes[p] == ')') {
                break;
            }
            if (j + 1 < sizeof(path)) {
                path[j++] = (char)source->bytes[p];
            }
            ++p;
        }
        path[j] = '\0';
        while (j > 0 && (path[j - 1] == ' ' || path[j - 1] == '\t' || path[j - 1] == '\r' || path[j - 1] == '\n')) {
            path[--j] = '\0';
        }
        jx_collect_asset_path(bundle, source_dir, runtime_dir, path, depth);
    }
}

static void jx_bundle_add_file(JxBundle *bundle, const char *source_path, const char *runtime_path, int depth) {
    char *source_copy = jx_normalized_path_alloc(source_path);
    char *runtime_copy = jx_normalized_path_alloc(runtime_path);

    if (jx_bundle_has_source(bundle, source_copy)) {
        free(source_copy);
        free(runtime_copy);
        return;
    }

    if (bundle->count == bundle->capacity) {
        size_t next_capacity = bundle->capacity ? bundle->capacity * 2 : 8;
        JxBundledFile *next = (JxBundledFile *)realloc(bundle->items, next_capacity * sizeof(JxBundledFile));
        if (!next) {
            jx_die("out of memory");
        }
        bundle->items = next;
        bundle->capacity = next_capacity;
    }

    JxBundledFile *file = &bundle->items[bundle->count++];
    file->source_path = source_copy;
    file->runtime_path = runtime_copy;
    if (jx_is_image_asset_path(file->runtime_path)) {
        file->bytes = jx_read_file(file->source_path);
        return;
    }

    char *file_source_path = jx_copy_text(file->source_path);
    char *file_runtime_path = jx_copy_text(file->runtime_path);
    JxBuffer original = jx_read_file(file->source_path);
    file->bytes = jx_rewrite_static_include_paths(&original, file->runtime_path);
    char *source_dir = jx_dirname_alloc(file_source_path);
    char *runtime_dir = jx_dirname_alloc(file_runtime_path);
    jx_collect_quoted_image_assets(bundle, &original, source_dir, runtime_dir, depth + 1);
    free(source_dir);
    free(runtime_dir);
    free(original.bytes);
    jx_collect_includes_from_file(bundle, file_source_path, file_runtime_path, depth + 1);
    free(file_source_path);
    free(file_runtime_path);
}

static void jx_collect_includes_from_file(JxBundle *bundle, const char *source_path, const char *runtime_path, int depth) {
    if (depth > 64) {
        jx_die("include/require graph is too deep");
    }

    JxBuffer source = jx_read_file(source_path);
    char *source_dir = jx_dirname_alloc(source_path);
    char *runtime_dir = jx_dirname_alloc(runtime_path);

    jx_collect_quoted_image_assets(bundle, &source, source_dir, runtime_dir, depth);

    for (size_t i = 0; i < source.length; ++i) {
        char include_path[1024];
        size_t next_pos = i + 1;
        if (!jx_scan_include_literal(source.bytes, source.length, i, include_path, sizeof(include_path), &next_pos, NULL, NULL)) {
            continue;
        }
        i = next_pos;
        if (jx_is_absolute_or_url_path(include_path)) {
            continue;
        }

        char *source_include = jx_join_path_alloc(source_dir, include_path);
        char *runtime_include = jx_join_path_alloc(runtime_dir, include_path);
        jx_normalize_slashes(source_include);
        jx_normalize_slashes(runtime_include);
        jx_bundle_add_file(bundle, source_include, runtime_include, depth);
        free(source_include);
        free(runtime_include);
    }

    free(source.bytes);
    free(source_dir);
    free(runtime_dir);
}

static void jx_emit_function_family_json(FILE *out, const JxBuffer *input) {
    const unsigned char *bytes = input->bytes;
    size_t count = 0;
    const unsigned char *names[128];
    size_t lengths[128];

    for (size_t i = 0; i + 3 <= input->length; ++i) {
        if (bytes[i] != 'j' || bytes[i + 1] != 'x' || bytes[i + 2] != '_') {
            continue;
        }
        if (i > 0 && jx_is_ident_char(bytes[i - 1])) {
            continue;
        }

        size_t end = i + 3;
        while (end < input->length && jx_is_ident_char(bytes[end])) {
            ++end;
        }
        if (end == i + 3) {
            continue;
        }

        int seen = 0;
        for (size_t j = 0; j < count; ++j) {
            if (jx_slice_equals(names[j], lengths[j], bytes + i, end - i)) {
                seen = 1;
                break;
            }
        }
        if (!seen && count < 128) {
            names[count] = bytes + i;
            lengths[count] = end - i;
            ++count;
        }
    }

    fputs("static const char *JX_FUNCTION_FAMILY_JSON = \"[", out);
    for (size_t i = 0; i < count; ++i) {
        if (i) {
            fputs(",", out);
        }
        fputs("\\\"", out);
        for (size_t j = 0; j < lengths[i]; ++j) {
            fputc((int)names[i][j], out);
        }
        fputs("\\\"", out);
    }
    fputs("]\";\n", out);
}

static int jx_match_php_key(const unsigned char *bytes, size_t length, size_t pos, const char *key) {
    size_t key_len = strlen(key);
    if (pos + key_len + 2 > length) {
        return 0;
    }
    return bytes[pos] == '\'' &&
           memcmp(bytes + pos + 1, key, key_len) == 0 &&
           bytes[pos + key_len + 1] == '\'';
}

static int jx_find_php_string_after_key(
    const JxBuffer *input,
    size_t key_pos,
    const char *key,
    const unsigned char **value,
    size_t *value_len
) {
    const unsigned char *bytes = input->bytes;
    size_t length = input->length;
    size_t p = key_pos + strlen(key) + 2;

    while (p < length && (bytes[p] == ' ' || bytes[p] == '\t' || bytes[p] == '\r' || bytes[p] == '\n')) {
        ++p;
    }
    if (p + 1 >= length || bytes[p] != '=' || bytes[p + 1] != '>') {
        return 0;
    }
    p += 2;
    while (p < length && (bytes[p] == ' ' || bytes[p] == '\t' || bytes[p] == '\r' || bytes[p] == '\n')) {
        ++p;
    }
    if (p >= length || (bytes[p] != '\'' && bytes[p] != '"')) {
        return 0;
    }

    unsigned char quote = bytes[p++];
    size_t start = p;
    while (p < length) {
        if (bytes[p] == '\\' && p + 1 < length) {
            p += 2;
            continue;
        }
        if (bytes[p] == quote) {
            *value = bytes + start;
            *value_len = p - start;
            return 1;
        }
        ++p;
    }
    return 0;
}

static int jx_find_previous_php_string_key(
    const JxBuffer *input,
    size_t before,
    const char *key,
    const unsigned char **value,
    size_t *value_len
) {
    size_t window = before > 320 ? before - 320 : 0;
    for (size_t p = before; p > window; --p) {
        size_t pos = p - 1;
        if (jx_match_php_key(input->bytes, input->length, pos, key) &&
            jx_find_php_string_after_key(input, pos, key, value, value_len)) {
            return 1;
        }
    }
    return 0;
}

static void jx_emit_zindex_items_json(FILE *out, const JxBuffer *input) {
    size_t z = 0;
    int first = 1;

    fputs("static const char *JX_ZINDEX_SORTED_ITEMS_JSON = \"[", out);
    for (size_t i = 0; i < input->length; ++i) {
        if (!jx_match_php_key(input->bytes, input->length, i, "id")) {
            continue;
        }

        const unsigned char *id = NULL;
        const unsigned char *type = NULL;
        size_t id_len = 0;
        size_t type_len = 0;
        if (!jx_find_php_string_after_key(input, i, "id", &id, &id_len)) {
            continue;
        }
        (void)jx_find_previous_php_string_key(input, i, "type", &type, &type_len);

        if (!first) {
            fputs(",", out);
        }
        first = 0;
        fprintf(out, "{\\\"zIndex\\\":%zu,\\\"id\\\":", z++);
        jx_write_json_value_inside_c_string(out, id, id_len);
        if (type) {
            fputs(",\\\"type\\\":", out);
            jx_write_json_value_inside_c_string(out, type, type_len);
        }
        fputs("}", out);
    }
    fputs("]\";\n", out);
}

static void jx_emit_include_reference(FILE *out, const JxOptions *opt) {
    fputs("static const char *JX_INCLUDE_REFERENCE =\n", out);
    fputs("\"JX generated C include/link reference\\n\"\n", out);
    fputs("\"mode: single-file PHP bridge C, GCC/cc compilable\\n\"\n", out);
    fputs("\"standard includes: errno.h, stdio.h, stdlib.h, string.h\\n\"\n", out);
    fputs("\"Windows bridge includes: windows.h; link: none for bridge-only executable\\n\"\n", out);
    fputs("\"POSIX bridge includes: sys/types.h, sys/wait.h, unistd.h; link: none beyond libc\\n\"\n", out);
    fputs("\"embedded PHP payload symbol: jx_php_payload / jx_php_payload_len\\n\"\n", out);
    fputs("\"embedded CSS payload symbol: jx_css_asset / jx_css_asset_len; exported to PHP as JX_CSS_FILE and JX_CSS_NAME\\n\"\n", out);
    fputs("\"embedded include/image asset table: JX_EMBEDDED_FILES materialized under the temp source directory\\n\"\n", out);
    fputs("\"jx_* page family manifest source: data/php_function_manifest.csv\\n\"\n", out);
    fputs("\"native page renderer reference includes: src/window/jx_php_page_data.h, src/runtime/jx_css_runtime.h\\n\"\n", out);
    fputs("\"native page renderer sources: src/window/jx-page-win32-from-php.c, src/runtime/jx_css_runtime.c\\n\"\n", out);
    fputs("\"native page renderer Windows libraries: ws2_32, gdi32, user32\\n\"\n", out);
    fputs("\"native page manifest reference: examples/native-page.manifest.json\\n\"", out);
    if (opt->inferred_asset) {
        fputs("\n\"CSS asset: inferred from input directory style.css\\n\"", out);
    }
    fputs(";\n", out);
}

static void jx_emit_bytes(FILE *out, const char *symbol, const JxBuffer *input) {
    fprintf(out, "static const unsigned char %s[%zu] = {\n", symbol, input->length == 0 ? 1 : input->length);
    if (input->length == 0) {
        fputs("    0x00\n", out);
    } else {
        for (size_t i = 0; i < input->length; ++i) {
            if (i % 12 == 0) {
                fputs("    ", out);
            }
            fprintf(out, "0x%02x", input->bytes[i]);
            if (i + 1 < input->length) {
                fputs(", ", out);
            }
            if (i % 12 == 11 || i + 1 == input->length) {
                fputc('\n', out);
            }
        }
    }
    fputs("};\n", out);
}

static void jx_emit_bundle(FILE *out, const JxBundle *bundle) {
    for (size_t i = 0; i < bundle->count; ++i) {
        char symbol[64];
        snprintf(symbol, sizeof(symbol), "jx_php_include_%zu", i);
        jx_emit_bytes(out, symbol, &bundle->items[i].bytes);
    }

    fputs("typedef struct { const char *path; const unsigned char *bytes; size_t len; } JxEmbeddedFile;\n", out);
    if (bundle->count == 0) {
        fputs("static const JxEmbeddedFile JX_EMBEDDED_FILES[1] = {{ \"\", NULL, 0 }};\n", out);
        fputs("static const size_t JX_EMBEDDED_FILE_COUNT = 0;\n", out);
        fputs("static const char *JX_EMBEDDED_DIRS[] = { \"\" };\n", out);
        fputs("static const size_t JX_EMBEDDED_DIR_COUNT = 1;\n", out);
        return;
    }

    fputs("static const JxEmbeddedFile JX_EMBEDDED_FILES[] = {\n", out);
    for (size_t i = 0; i < bundle->count; ++i) {
        fprintf(out, "    { ");
        jx_write_c_string(out, bundle->items[i].runtime_path);
        fprintf(out, ", jx_php_include_%zu, %zu }%s\n", i, bundle->items[i].bytes.length, i + 1 == bundle->count ? "" : ",");
    }
    fputs("};\n", out);
    fprintf(out, "static const size_t JX_EMBEDDED_FILE_COUNT = %zu;\n", bundle->count);

    size_t dir_count = 1;
    fputs("static const char *JX_EMBEDDED_DIRS[] = {\n", out);
    fputs("    \"\"", out);
    for (size_t i = 0; i < bundle->count; ++i) {
        char *dir = jx_dirname_alloc(bundle->items[i].runtime_path);
        if (dir[0] != '\0') {
            fputs(",\n    ", out);
            jx_write_c_string(out, dir);
            ++dir_count;
        }
        free(dir);
    }
    fputs("\n};\n", out);
    fprintf(out, "static const size_t JX_EMBEDDED_DIR_COUNT = %zu;\n", dir_count);
}

static void jx_emit_runtime(FILE *out, const JxOptions *opt, const JxBuffer *input, const JxBuffer *asset, const JxBundle *bundle) {
    int has_asset = opt->asset_path != NULL && asset != NULL;

    fputs("/* Generated by JX native Oracle C emitter. */\n", out);
    fputs("#include <errno.h>\n", out);
    fputs("#include <stdio.h>\n", out);
    fputs("#include <stdlib.h>\n", out);
    fputs("#include <string.h>\n", out);
    fputs("#ifdef _WIN32\n", out);
    fputs("#define WIN32_LEAN_AND_MEAN\n", out);
    fputs("#include <windows.h>\n", out);
    fputs("#include <shellapi.h>\n", out);
    fputs("#else\n", out);
    fputs("#include <sys/types.h>\n", out);
    fputs("#include <sys/stat.h>\n", out);
    fputs("#include <sys/wait.h>\n", out);
    fputs("#include <unistd.h>\n", out);
    fputs("extern int mkstemp(char *);\n", out);
    fputs("extern char *mkdtemp(char *);\n", out);
    fputs("extern int setenv(const char *, const char *, int);\n", out);
    fputs("#endif\n\n", out);
    fputs("#ifdef _WIN32\n", out);
    fputs("#define JX_INCLUDE_PATH_SEP ';'\n", out);
    fputs("#else\n", out);
    fputs("#define JX_INCLUDE_PATH_SEP ':'\n", out);
    fputs("#endif\n\n", out);

    fputs("static const char *JX_ORACLE_NAME = \"php-payload-exec-oracle\";\n", out);
    fputs("static const char *JX_ORACLE_VERSION = \"0.4.1\";\n", out);
    fputs("static const char *JX_SOURCE_PATH = ", out);
    jx_write_c_string(out, opt->input_path);
    fputs(";\n", out);
    fprintf(out, "static const int jx_window_mode = %d;\n", opt->window_mode ? 1 : 0);
    fputs("static const char *JX_SOURCE_RUNTIME_PATH = ", out);
    jx_write_c_string(out, jx_basename(opt->input_path));
    fputs(";\n", out);
    fprintf(out, "static const size_t jx_php_payload_len = %zu;\n", input->length);
    jx_emit_bytes(out, "jx_php_payload", input);

    fprintf(out, "static const int jx_has_css_asset = %d;\n", has_asset ? 1 : 0);
    fputs("static const char *JX_CSS_ASSET_NAME = ", out);
    jx_write_c_string(out, has_asset ? jx_basename(opt->asset_path) : "");
    fputs(";\n", out);
    fprintf(out, "static const size_t jx_css_asset_len = %zu;\n", has_asset ? asset->length : 0);
    if (has_asset) {
        jx_emit_bytes(out, "jx_css_asset", asset);
    } else {
        JxBuffer empty;
        empty.bytes = NULL;
        empty.length = 0;
        jx_emit_bytes(out, "jx_css_asset", &empty);
    }
    jx_emit_bundle(out, bundle);
    jx_emit_function_family_json(out, input);
    jx_emit_zindex_items_json(out, input);
    jx_emit_include_reference(out, opt);

    fputs("\nstatic void jx_fail(const char *message) {\n", out);
    fputs("    fprintf(stderr, \"jx generated executable: %s: %s\\n\", message, strerror(errno));\n", out);
    fputs("    exit(1);\n", out);
    fputs("}\n\n", out);

    fputs("static char *jx_copy_string(const char *text) {\n", out);
    fputs("    size_t len = strlen(text) + 1;\n", out);
    fputs("    char *copy = (char *)malloc(len);\n", out);
    fputs("    if (!copy) { jx_fail(\"out of memory\"); }\n", out);
    fputs("    memcpy(copy, text, len);\n", out);
    fputs("    return copy;\n", out);
    fputs("}\n\n", out);

    fputs("static char *jx_join_runtime_path(const char *dir, const char *path) {\n", out);
    fputs("    size_t dir_len = strlen(dir); size_t path_len = strlen(path);\n", out);
    fputs("    int needs_sep = dir_len > 0 && dir[dir_len - 1] != '/' && dir[dir_len - 1] != '\\\\';\n", out);
    fputs("    char *joined = (char *)malloc(dir_len + (needs_sep ? 1 : 0) + path_len + 1);\n", out);
    fputs("    if (!joined) { jx_fail(\"out of memory\"); }\n", out);
    fputs("    memcpy(joined, dir, dir_len); if (needs_sep) { joined[dir_len++] = '/'; }\n", out);
    fputs("    memcpy(joined + dir_len, path, path_len + 1); return joined;\n", out);
    fputs("}\n\n", out);

    fputs("static char *jx_html_output_path(const char *source_dir) {\n", out);
    fputs("    return jx_join_runtime_path(source_dir, \"jx-window-output.html\");\n", out);
    fputs("}\n\n", out);

    fputs("static char *jx_build_include_path(const char *source_dir) {\n", out);
    fputs("    size_t cap = 4096; char *paths = (char *)calloc(1, cap); if (!paths) { jx_fail(\"out of memory\"); }\n", out);
    fputs("    for (size_t i = 0; i < JX_EMBEDDED_DIR_COUNT; ++i) {\n", out);
    fputs("        char *dir = jx_join_runtime_path(source_dir, JX_EMBEDDED_DIRS[i]);\n", out);
    fputs("        size_t need = strlen(paths) + strlen(dir) + 3;\n", out);
    fputs("        if (need > cap) { while (need > cap) { cap *= 2; } char *next = (char *)realloc(paths, cap); if (!next) { free(dir); free(paths); jx_fail(\"out of memory\"); } paths = next; }\n", out);
    fputs("        if (paths[0] != '\\0') { size_t len = strlen(paths); paths[len] = JX_INCLUDE_PATH_SEP; paths[len + 1] = '\\0'; }\n", out);
    fputs("        strcat(paths, dir); free(dir);\n", out);
    fputs("    }\n", out);
    fputs("    return paths;\n", out);
    fputs("}\n\n", out);

    fputs("#ifdef _WIN32\n", out);
    fputs("static char *jx_make_temp_dir(const char *prefix) {\n", out);
    fputs("    char temp_dir[MAX_PATH]; char temp_path[MAX_PATH];\n", out);
    fputs("    if (GetTempPathA((DWORD)sizeof(temp_dir), temp_dir) == 0) { jx_fail(\"cannot get temp path\"); }\n", out);
    fputs("    if (GetTempFileNameA(temp_dir, prefix, 0, temp_path) == 0) { jx_fail(\"cannot create temp directory name\"); }\n", out);
    fputs("    DeleteFileA(temp_path);\n", out);
    fputs("    if (!CreateDirectoryA(temp_path, NULL) && GetLastError() != ERROR_ALREADY_EXISTS) { jx_fail(\"cannot create temp source directory\"); }\n", out);
    fputs("    return jx_copy_string(temp_path);\n", out);
    fputs("}\n\n", out);

    fputs("static void jx_mkdir_parents_for_file(const char *path) {\n", out);
    fputs("    char *copy = jx_copy_string(path);\n", out);
    fputs("    for (char *p = copy; *p; ++p) {\n", out);
    fputs("        if ((*p == '/' || *p == '\\\\') && p != copy && p[-1] != ':') { char saved = *p; *p = '\\0'; CreateDirectoryA(copy, NULL); *p = saved; }\n", out);
    fputs("    }\n", out);
    fputs("    free(copy);\n", out);
    fputs("}\n\n", out);

    fputs("char *jx_materialize_bytes(const char *prefix, const unsigned char *bytes, size_t len) {\n", out);
    fputs("    char temp_dir[MAX_PATH];\n", out);
    fputs("    char temp_path[MAX_PATH];\n", out);
    fputs("    if (GetTempPathA((DWORD)sizeof(temp_dir), temp_dir) == 0) { jx_fail(\"cannot get temp path\"); }\n", out);
    fputs("    if (GetTempFileNameA(temp_dir, prefix, 0, temp_path) == 0) { jx_fail(\"cannot create temp file name\"); }\n", out);
    fputs("    HANDLE file = CreateFileA(temp_path, GENERIC_WRITE, 0, NULL, CREATE_ALWAYS, FILE_ATTRIBUTE_TEMPORARY, NULL);\n", out);
    fputs("    if (file == INVALID_HANDLE_VALUE) { jx_fail(\"cannot create temp materialized file\"); }\n", out);
    fputs("    size_t total = 0;\n", out);
    fputs("    while (total < len) {\n", out);
    fputs("        DWORD chunk = (DWORD)((len - total) > 1048576 ? 1048576 : (len - total));\n", out);
    fputs("        DWORD written = 0;\n", out);
    fputs("        if (!WriteFile(file, bytes + total, chunk, &written, NULL) || written == 0) {\n", out);
    fputs("            CloseHandle(file); DeleteFileA(temp_path); jx_fail(\"cannot write temp materialized file\");\n", out);
    fputs("        }\n", out);
    fputs("        total += (size_t)written;\n", out);
    fputs("    }\n", out);
    fputs("    CloseHandle(file);\n", out);
    fputs("    return jx_copy_string(temp_path);\n", out);
    fputs("}\n\n", out);

    fputs("static char *jx_materialize_named_bytes(const char *dir, const char *path, const unsigned char *bytes, size_t len) {\n", out);
    fputs("    char *full_path = jx_join_runtime_path(dir, path); jx_mkdir_parents_for_file(full_path);\n", out);
    fputs("    HANDLE file = CreateFileA(full_path, GENERIC_WRITE, 0, NULL, CREATE_ALWAYS, FILE_ATTRIBUTE_TEMPORARY, NULL);\n", out);
    fputs("    if (file == INVALID_HANDLE_VALUE) { jx_fail(\"cannot create bundled PHP file\"); }\n", out);
    fputs("    size_t total = 0; while (total < len) { DWORD chunk = (DWORD)((len - total) > 1048576 ? 1048576 : (len - total)); DWORD written = 0; if (!WriteFile(file, bytes + total, chunk, &written, NULL) || written == 0) { CloseHandle(file); DeleteFileA(full_path); jx_fail(\"cannot write bundled PHP file\"); } total += (size_t)written; }\n", out);
    fputs("    CloseHandle(file); return full_path;\n", out);
    fputs("}\n\n", out);

    fputs("static char *jx_quote_arg(const char *arg) {\n", out);
    fputs("    size_t len = strlen(arg);\n", out);
    fputs("    char *out = (char *)malloc(len * 2 + 3);\n", out);
    fputs("    if (!out) { jx_fail(\"out of memory\"); }\n", out);
    fputs("    size_t j = 0; out[j++] = '\"';\n", out);
    fputs("    for (size_t i = 0; i < len; ++i) { if (arg[i] == '\"' || arg[i] == '\\\\') out[j++] = '\\\\'; out[j++] = arg[i]; }\n", out);
    fputs("    out[j++] = '\"'; out[j] = '\\0'; return out;\n", out);
    fputs("}\n\n", out);

    fputs("static char *jx_build_command_line(const char *php, const char *payload_path, const char *include_path, int argc, char **argv) {\n", out);
    fputs("    size_t cap = 4096; char *cmd = (char *)calloc(1, cap); if (!cmd) { jx_fail(\"out of memory\"); }\n", out);
    fputs("    size_t setting_len = strlen(include_path) + 32; char *setting = (char *)malloc(setting_len); if (!setting) { jx_fail(\"out of memory\"); }\n", out);
    fputs("    snprintf(setting, setting_len, \"include_path=%s\", include_path);\n", out);
    fputs("    char *qphp = jx_quote_arg(php); char *qsetting = jx_quote_arg(setting); char *qpayload = jx_quote_arg(payload_path);\n", out);
    fputs("    strcat(cmd, qphp); strcat(cmd, \" -d \" ); strcat(cmd, qsetting); strcat(cmd, \" \" ); strcat(cmd, qpayload); free(qphp); free(qsetting); free(qpayload); free(setting);\n", out);
    fputs("    for (int i = 1; i < argc; ++i) {\n", out);
    fputs("        char *q = jx_quote_arg(argv[i]); size_t need = strlen(cmd) + strlen(q) + 2;\n", out);
    fputs("        if (need > cap) { while (need > cap) cap *= 2; char *next = (char *)realloc(cmd, cap); if (!next) { free(q); free(cmd); jx_fail(\"out of memory\"); } cmd = next; }\n", out);
    fputs("        strcat(cmd, \" \" ); strcat(cmd, q); free(q);\n", out);
    fputs("    }\n", out);
    fputs("    return cmd;\n", out);
    fputs("}\n\n", out);

    fputs("static int jx_exec_php(int argc, char **argv, const char *payload_path, const char *source_dir, const char *css_path) {\n", out);
    fputs("    const char *php = getenv(\"JX_PHP\"); if (!php || !*php) { php = \"php\"; }\n", out);
    fputs("    char *include_path = jx_build_include_path(source_dir);\n", out);
    fputs("    if (css_path && jx_has_css_asset) { SetEnvironmentVariableA(\"JX_CSS_FILE\", css_path); SetEnvironmentVariableA(\"JX_CSS_NAME\", JX_CSS_ASSET_NAME); }\n", out);
    fputs("    char *cmd = jx_build_command_line(php, payload_path, include_path, argc, argv);\n", out);
    fputs("    STARTUPINFOA si; PROCESS_INFORMATION pi; ZeroMemory(&si, sizeof(si)); ZeroMemory(&pi, sizeof(pi)); si.cb = sizeof(si);\n", out);
    fputs("    HANDLE output_file = INVALID_HANDLE_VALUE;\n", out);
    fputs("    char *html_path = NULL;\n", out);
    fputs("    if (jx_window_mode) {\n", out);
    fputs("        html_path = jx_html_output_path(source_dir);\n", out);
    fputs("        output_file = CreateFileA(html_path, GENERIC_WRITE, FILE_SHARE_READ, NULL, CREATE_ALWAYS, FILE_ATTRIBUTE_NORMAL, NULL);\n", out);
    fputs("        if (output_file == INVALID_HANDLE_VALUE) { free(cmd); free(include_path); free(html_path); jx_fail(\"cannot create window html output\"); }\n", out);
    fputs("        si.dwFlags |= STARTF_USESTDHANDLES; si.hStdInput = GetStdHandle(STD_INPUT_HANDLE); si.hStdOutput = output_file; si.hStdError = GetStdHandle(STD_ERROR_HANDLE);\n", out);
    fputs("    }\n", out);
    fputs("    BOOL ok = CreateProcessA(NULL, cmd, NULL, NULL, TRUE, 0, source_dir, &si, &pi);\n", out);
    fputs("    free(cmd); free(include_path);\n", out);
    fputs("    if (!ok) { if (output_file != INVALID_HANDLE_VALUE) { CloseHandle(output_file); } free(html_path); fprintf(stderr, \"jx generated executable: cannot exec PHP runtime '%s': Windows error %lu\\n\", php, (unsigned long)GetLastError()); return 127; }\n", out);
    fputs("    WaitForSingleObject(pi.hProcess, INFINITE); DWORD code = 1; GetExitCodeProcess(pi.hProcess, &code); CloseHandle(pi.hThread); CloseHandle(pi.hProcess);\n", out);
    fputs("    if (output_file != INVALID_HANDLE_VALUE) { CloseHandle(output_file); }\n", out);
    fputs("    if ((int)code == 0 && jx_window_mode && html_path) { printf(\"JX_WINDOW_FILE=%s\\n\", html_path); if (!getenv(\"JX_NO_OPEN\")) { ShellExecuteA(NULL, \"open\", html_path, NULL, source_dir, SW_SHOWNORMAL); } }\n", out);
    fputs("    free(html_path); return (int)code;\n", out);
    fputs("}\n", out);
    fputs("#else\n", out);

    fputs("static char *jx_make_temp_dir(const char *prefix) {\n", out);
    fputs("    char template_path[160];\n", out);
    fputs("    int count = snprintf(template_path, sizeof(template_path), \"/tmp/%s_XXXXXX\", prefix);\n", out);
    fputs("    if (count < 0 || (size_t)count >= sizeof(template_path)) { errno = ENAMETOOLONG; jx_fail(\"cannot create temporary source directory path\"); }\n", out);
    fputs("    if (!mkdtemp(template_path)) { jx_fail(\"cannot create temporary source directory\"); }\n", out);
    fputs("    return jx_copy_string(template_path);\n", out);
    fputs("}\n\n", out);

    fputs("static void jx_mkdir_parents_for_file(const char *path) {\n", out);
    fputs("    char *copy = jx_copy_string(path);\n", out);
    fputs("    for (char *p = copy; *p; ++p) {\n", out);
    fputs("        if (*p == '/' && p != copy) { char saved = *p; *p = '\\0'; if (mkdir(copy, 0700) != 0 && errno != EEXIST) { free(copy); jx_fail(\"cannot create bundled PHP directory\"); } *p = saved; }\n", out);
    fputs("    }\n", out);
    fputs("    free(copy);\n", out);
    fputs("}\n\n", out);

    fputs("char *jx_materialize_bytes(const char *prefix, const unsigned char *bytes, size_t len) {\n", out);
    fputs("    char template_path[160];\n", out);
    fputs("    int count = snprintf(template_path, sizeof(template_path), \"/tmp/%s_XXXXXX\", prefix);\n", out);
    fputs("    if (count < 0 || (size_t)count >= sizeof(template_path)) { errno = ENAMETOOLONG; jx_fail(\"cannot create temporary template path\"); }\n", out);
    fputs("    int fd = mkstemp(template_path); if (fd < 0) { jx_fail(\"cannot create temporary materialized file\"); }\n", out);
    fputs("    size_t total = 0; while (total < len) { ssize_t written = write(fd, bytes + total, len - total); if (written <= 0) { close(fd); unlink(template_path); errno = EIO; jx_fail(\"cannot write temp materialized file\"); } total += (size_t)written; }\n", out);
    fputs("    if (close(fd) != 0) { unlink(template_path); jx_fail(\"cannot close materialized file\"); }\n", out);
    fputs("    return jx_copy_string(template_path);\n", out);
    fputs("}\n\n", out);

    fputs("static char *jx_materialize_named_bytes(const char *dir, const char *path, const unsigned char *bytes, size_t len) {\n", out);
    fputs("    char *full_path = jx_join_runtime_path(dir, path); jx_mkdir_parents_for_file(full_path);\n", out);
    fputs("    FILE *fp = fopen(full_path, \"wb\"); if (!fp) { jx_fail(\"cannot create bundled PHP file\"); }\n", out);
    fputs("    if (len > 0 && fwrite(bytes, 1, len, fp) != len) { fclose(fp); unlink(full_path); jx_fail(\"cannot write bundled PHP file\"); }\n", out);
    fputs("    if (fclose(fp) != 0) { unlink(full_path); jx_fail(\"cannot close bundled PHP file\"); }\n", out);
    fputs("    return full_path;\n", out);
    fputs("}\n\n", out);

    fputs("static int jx_exec_php(int argc, char **argv, const char *payload_path, const char *source_dir, const char *css_path) {\n", out);
    fputs("    const char *php = getenv(\"JX_PHP\"); if (!php || !*php) { php = \"php\"; }\n", out);
    fputs("    char *html_path = jx_window_mode ? jx_html_output_path(source_dir) : NULL;\n", out);
    fputs("    char *include_path = jx_build_include_path(source_dir);\n", out);
    fputs("    size_t include_setting_len = strlen(include_path) + 32;\n", out);
    fputs("    char *include_setting = (char *)malloc(include_setting_len); if (!include_setting) { jx_fail(\"out of memory\"); }\n", out);
    fputs("    snprintf(include_setting, include_setting_len, \"include_path=%s\", include_path);\n", out);
    fputs("    if (css_path && jx_has_css_asset) { if (setenv(\"JX_CSS_FILE\", css_path, 1) != 0) { jx_fail(\"cannot export JX_CSS_FILE\"); } if (setenv(\"JX_CSS_NAME\", JX_CSS_ASSET_NAME, 1) != 0) { jx_fail(\"cannot export JX_CSS_NAME\"); } }\n", out);
    fputs("    char **child_argv = (char **)calloc((size_t)argc + 4, sizeof(char *)); if (!child_argv) { jx_fail(\"out of memory\"); }\n", out);
    fputs("    child_argv[0] = (char *)php; child_argv[1] = \"-d\"; child_argv[2] = include_setting; child_argv[3] = (char *)payload_path; for (int i = 1; i < argc; ++i) { child_argv[i + 3] = argv[i]; }\n", out);
    fputs("    pid_t pid = fork(); if (pid < 0) { free(child_argv); free(include_setting); free(include_path); free(html_path); jx_fail(\"cannot fork PHP runtime\"); }\n", out);
    fputs("    if (pid == 0) { if (chdir(source_dir) != 0) { _exit(126); } if (html_path && !freopen(html_path, \"wb\", stdout)) { _exit(126); } execvp(php, child_argv); fprintf(stderr, \"jx generated executable: cannot exec PHP runtime '%s': %s\\n\", php, strerror(errno)); _exit(127); }\n", out);
    fputs("    free(child_argv); free(include_setting); free(include_path); int status = 0; while (waitpid(pid, &status, 0) < 0) { if (errno != EINTR) { jx_fail(\"cannot wait for PHP runtime\"); } }\n", out);
    fputs("    int code = 1; if (WIFEXITED(status)) { code = WEXITSTATUS(status); } else if (WIFSIGNALED(status)) { code = 128 + WTERMSIG(status); }\n", out);
    fputs("    if (code == 0 && jx_window_mode && html_path) {\n", out);
    fputs("        printf(\"JX_WINDOW_FILE=%s\\n\", html_path);\n", out);
    fputs("        if (!getenv(\"JX_NO_OPEN\")) {\n", out);
    fputs("            pid_t opener = fork();\n", out);
    fputs("            if (opener == 0) { execlp(\"xdg-open\", \"xdg-open\", html_path, (char *)NULL); execlp(\"wslview\", \"wslview\", html_path, (char *)NULL); execlp(\"open\", \"open\", html_path, (char *)NULL); _exit(127); }\n", out);
    fputs("        }\n", out);
    fputs("    }\n", out);
    fputs("    free(html_path); return code;\n", out);
    fputs("}\n", out);
    fputs("#endif\n\n", out);

    fputs("int main(int argc, char **argv) {\n", out);
    fputs("    (void)JX_ORACLE_NAME; (void)JX_ORACLE_VERSION; (void)JX_SOURCE_PATH; (void)jx_window_mode;\n", out);
    fputs("    (void)JX_FUNCTION_FAMILY_JSON; (void)JX_ZINDEX_SORTED_ITEMS_JSON; (void)JX_INCLUDE_REFERENCE;\n", out);
    fputs("    char *source_dir = jx_make_temp_dir(\"jxsrc\");\n", out);
    fputs("    char *payload_path = jx_materialize_named_bytes(source_dir, JX_SOURCE_RUNTIME_PATH, jx_php_payload, jx_php_payload_len);\n", out);
    fputs("    char **embedded_paths = (char **)calloc(JX_EMBEDDED_FILE_COUNT ? JX_EMBEDDED_FILE_COUNT : 1, sizeof(char *));\n", out);
    fputs("    if (!embedded_paths) { jx_fail(\"out of memory\"); }\n", out);
    fputs("    for (size_t i = 0; i < JX_EMBEDDED_FILE_COUNT; ++i) { embedded_paths[i] = jx_materialize_named_bytes(source_dir, JX_EMBEDDED_FILES[i].path, JX_EMBEDDED_FILES[i].bytes, JX_EMBEDDED_FILES[i].len); }\n", out);
    fputs("    char *css_path = NULL; if (jx_has_css_asset) { css_path = jx_materialize_named_bytes(source_dir, JX_CSS_ASSET_NAME, jx_css_asset, jx_css_asset_len); }\n", out);
    fputs("    int code = jx_exec_php(argc, argv, payload_path, source_dir, css_path);\n", out);
    fputs("#ifdef _WIN32\n", out);
    fputs("    DeleteFileA(payload_path); for (size_t i = 0; i < JX_EMBEDDED_FILE_COUNT; ++i) { DeleteFileA(embedded_paths[i]); } if (css_path) DeleteFileA(css_path);\n", out);
    fputs("#else\n", out);
    fputs("    unlink(payload_path); for (size_t i = 0; i < JX_EMBEDDED_FILE_COUNT; ++i) { unlink(embedded_paths[i]); } if (css_path) unlink(css_path);\n", out);
    fputs("#endif\n", out);
    fputs("    for (size_t i = 0; i < JX_EMBEDDED_FILE_COUNT; ++i) { free(embedded_paths[i]); }\n", out);
    fputs("    free(embedded_paths); free(payload_path); free(source_dir); if (css_path) free(css_path); return code;\n", out);
    fputs("}\n", out);
}

static int jx_is_native_page_source(const JxBuffer *input) {
    return jx_source_contains(input, "jx_page_title") ||
           jx_source_contains(input, "jx_page_body") ||
           jx_source_contains(input, "jx_page_json") ||
           jx_source_contains(input, "jx_local_api");
}

static void jx_emit_native_page_c(FILE *out, const JxOptions *opt, const JxBuffer *input, const JxBuffer *asset) {
    int has_asset = opt->asset_path != NULL && asset != NULL;
    char *title = jx_find_literal_call_alloc(input, "jx_page_title", "JX Native Page");
    char *badge = jx_find_literal_call_alloc(input, "jx_page_badge", "BUILT FROM PHP");
    char *body = jx_find_literal_call_alloc(input, "jx_page_body", "Native window generated by jx.");
    char *modal_title = jx_find_literal_call_alloc(input, "jx_modal_title", "JX Native Modal");
    char *modal_body = jx_find_literal_call_alloc(input, "jx_modal_body", "This modal was declared in PHP and compiled into the native executable.");
    char *iframe_title = jx_find_literal_call_alloc(input, "jx_iframe_title", "Native Iframe");
    char *iframe_html = jx_find_literal_call_alloc(input, "jx_iframe_html", "<p>Iframe HTML declared in PHP.</p>");
    char *page_json = jx_find_literal_call_alloc(input, "jx_page_json", "{\"renderer\":\"win32-native\",\"api\":[\"/update\",\"/modal\",\"/iframe\",\"/json\"]}");

    fputs("/* Generated by JX native page window emitter. */\n", out);
    fputs("/* Compile on Windows/MinGW: x86_64-w64-mingw32-gcc -O2 -Wall -Wextra -mwindows -I . -o page.exe page.c -lws2_32 -lgdi32 -luser32 */\n", out);
    fputs("#define JX_PAGE_DATA_EMBEDDED 1\n", out);
    fputs("#define JX_PAGE_SOURCE ", out);
    jx_write_c_string(out, opt->input_path);
    fputs("\n#define JX_PAGE_TITLE ", out);
    jx_write_c_string(out, title);
    fputs("\n#define JX_PAGE_BADGE ", out);
    jx_write_c_string(out, badge);
    fputs("\n#define JX_PAGE_BODY ", out);
    jx_write_c_string(out, body);
    fputs("\n#define JX_PAGE_MODAL_TITLE ", out);
    jx_write_c_string(out, modal_title);
    fputs("\n#define JX_PAGE_MODAL_BODY ", out);
    jx_write_c_string(out, modal_body);
    fputs("\n#define JX_PAGE_IFRAME_TITLE ", out);
    jx_write_c_string(out, iframe_title);
    fputs("\n#define JX_PAGE_IFRAME_HTML ", out);
    jx_write_c_string(out, iframe_html);
    fputs("\n#define JX_PAGE_ATTACHMENT_JSON ", out);
    jx_write_c_string(out, page_json);
    fputs("\n#define JX_PAGE_CSS ", out);
    if (has_asset) {
        jx_write_c_bytes_as_string(out, asset->bytes, asset->length);
    } else {
        jx_write_c_string(out, "");
    }
    fputs("\n\n", out);
    fputs("#include \"src/runtime/jx_css_runtime.c\"\n", out);
    fputs("#include \"src/window/jx-page-win32-from-php.c\"\n", out);

    free(title);
    free(badge);
    free(body);
    free(modal_title);
    free(modal_body);
    free(iframe_title);
    free(iframe_html);
    free(page_json);
}

static void jx_emit_c_file(const JxOptions *opt) {
    JxBuffer input = jx_read_file(opt->input_path);
    JxBuffer rewritten_input;
    JxBundle bundle;
    JxBuffer asset;
    rewritten_input.bytes = NULL;
    rewritten_input.length = 0;
    jx_bundle_init(&bundle);
    asset.bytes = NULL;
    asset.length = 0;

    if (opt->asset_path) {
        asset = jx_read_file(opt->asset_path);
        char *css_source_dir = jx_dirname_alloc(opt->asset_path);
        char *css_runtime_dir = jx_dirname_alloc(jx_basename(opt->asset_path));
        jx_collect_css_url_assets(&bundle, &asset, css_source_dir, css_runtime_dir, 0);
        jx_collect_quoted_image_assets(&bundle, &asset, css_source_dir, css_runtime_dir, 0);
        free(css_source_dir);
        free(css_runtime_dir);
    }

    if (jx_is_native_page_source(&input)) {
        FILE *out = fopen(opt->output_path, "wb");
        if (!out) {
            free(input.bytes);
            jx_bundle_free(&bundle);
            free(asset.bytes);
            jx_die_errno("cannot open output");
        }

        jx_emit_native_page_c(out, opt, &input, opt->asset_path ? &asset : NULL);

        if (fclose(out) != 0) {
            free(input.bytes);
            jx_bundle_free(&bundle);
            free(asset.bytes);
            jx_die_errno("cannot close output");
        }

        free(input.bytes);
        jx_bundle_free(&bundle);
        free(asset.bytes);
        return;
    }

    jx_collect_includes_from_file(&bundle, opt->input_path, jx_basename(opt->input_path), 0);
    rewritten_input = jx_rewrite_static_include_paths(&input, jx_basename(opt->input_path));

    FILE *out = fopen(opt->output_path, "wb");
    if (!out) {
        free(input.bytes);
        free(rewritten_input.bytes);
        jx_bundle_free(&bundle);
        free(asset.bytes);
        jx_die_errno("cannot open output");
    }

    jx_emit_runtime(out, opt, &rewritten_input, opt->asset_path ? &asset : NULL, &bundle);

    if (fclose(out) != 0) {
        free(input.bytes);
        free(rewritten_input.bytes);
        jx_bundle_free(&bundle);
        free(asset.bytes);
        jx_die_errno("cannot close output");
    }

    free(input.bytes);
    free(rewritten_input.bytes);
    jx_bundle_free(&bundle);
    free(asset.bytes);
}

static JxOptions jx_parse_options(int argc, char **argv, int first_arg) {
    JxOptions opt;
    opt.input_path = NULL;
    opt.output_path = NULL;
    opt.asset_path = NULL;
    opt.inferred_asset = 0;
    opt.window_mode = 0;

    if (argc <= first_arg) {
        jx_usage(stderr);
        exit(1);
    }

    if (first_arg == 2) {
        opt.input_path = argv[2];
    }

    int scan_start = first_arg == 2 ? 3 : first_arg;
    for (int i = scan_start; i < argc; ++i) {
        if (strcmp(argv[i], "-o") == 0 || strcmp(argv[i], "--output") == 0) {
            if (i + 1 >= argc) { jx_usage(stderr); exit(1); }
            opt.output_path = argv[++i];
        } else if (strcmp(argv[i], "--asset") == 0) {
            if (i + 1 >= argc) { jx_usage(stderr); exit(1); }
            opt.asset_path = argv[++i];
        } else if (strcmp(argv[i], "--window") == 0 || strcmp(argv[i], "--window=browser") == 0) {
            opt.window_mode = 1;
        } else if (!opt.input_path) {
            opt.input_path = argv[i];
        } else {
            jx_usage(stderr);
            exit(1);
        }
    }

    if (!opt.input_path || !opt.output_path) {
        jx_usage(stderr);
        exit(1);
    }

    jx_infer_asset(&opt);
    return opt;
}

int main(int argc, char **argv) {
    if (argc == 2 && strcmp(argv[1], "--version") == 0) {
        printf("JX %s\n", JX_VERSION);
        return 0;
    }

    if (argc < 2 || strcmp(argv[1], "--help") == 0 || strcmp(argv[1], "-h") == 0) {
        jx_usage(argc < 2 ? stderr : stdout);
        return argc < 2 ? 1 : 0;
    }

    int first_arg = 1;
    const char *command = argv[1];
    if (strcmp(command, "emit-c") == 0 || strcmp(command, "compile") == 0) {
        first_arg = 2;
    } else if (strcmp(command, "-o") == 0 || strcmp(command, "--output") == 0 || strcmp(command, "--window") == 0 || strcmp(command, "--window=browser") == 0) {
        first_arg = 1;
    } else {
        jx_usage(stderr);
        return 1;
    }

    JxOptions opt = jx_parse_options(argc, argv, first_arg);
    jx_emit_c_file(&opt);
    printf("JX emitted GCC-compilable C: %s -> %s\n", opt.input_path, opt.output_path);
    if (opt.inferred_asset) {
        free((char *)opt.asset_path);
    }
    return 0;
}
