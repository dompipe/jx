#include "jx-security-import.h"
#include <ctype.h>
#include <string.h>

static int hex_value(uint8_t c) {
    if (c >= '0' && c <= '9') return (int)(c - '0');
    if (c >= 'a' && c <= 'f') return (int)(c - 'a' + 10);
    if (c >= 'A' && c <= 'F') return (int)(c - 'A' + 10);
    return -1;
}

static int parse_digest(const uint8_t *s, size_t n,
                        jx_security_hash_algorithm *algorithm,
                        uint8_t digest[JX_SECURITY_DIGEST_MAX]) {
    size_t bytes, i;
    if (!s || !algorithm || !digest) return -1;
    if (n == 32u) {
        *algorithm = JX_SECURITY_HASH_MD5;
        bytes = 16u;
    } else if (n == 40u) {
        *algorithm = JX_SECURITY_HASH_SHA1;
        bytes = 20u;
    } else if (n == 64u) {
        *algorithm = JX_SECURITY_HASH_SHA256;
        bytes = 32u;
    } else {
        return -2;
    }
    memset(digest, 0, JX_SECURITY_DIGEST_MAX);
    for (i = 0u; i < bytes; ++i) {
        int hi = hex_value(s[i * 2u]);
        int lo = hex_value(s[i * 2u + 1u]);
        if (hi < 0 || lo < 0) return -3;
        digest[i] = (uint8_t)((hi << 4) | lo);
    }
    return 0;
}

static int parse_u64(const uint8_t *s, size_t n, uint64_t *value) {
    size_t i;
    uint64_t v = 0u;
    if (!s || !value || n == 0u) return -1;
    if (n == 1u && s[0] == '*') {
        *value = JX_SECURITY_SIZE_ANY;
        return 0;
    }
    for (i = 0u; i < n; ++i) {
        uint8_t c = s[i];
        uint64_t digit;
        if (c < '0' || c > '9') return -2;
        digit = (uint64_t)(c - '0');
        if (v > (UINT64_MAX - digit) / 10u) return -3;
        v = v * 10u + digit;
    }
    *value = v;
    return 0;
}

static int all_decimal(const uint8_t *s, size_t n) {
    size_t i;
    if (!s || n == 0u) return 0;
    for (i = 0u; i < n; ++i) if (!isdigit((unsigned char)s[i])) return 0;
    return 1;
}

static int import_line(const uint8_t *line, size_t n,
                       jx_security_signature *sig,
                       uint32_t id) {
    size_t c1 = SIZE_MAX, c2 = SIZE_MAX, c3 = SIZE_MAX, i;
    size_t hash_n, size_n, name_n;
    jx_security_hash_algorithm algorithm;
    uint64_t file_size;

    if (!line || !sig || n == 0u) return -1;
    for (i = 0u; i < n; ++i) {
        if (line[i] == ':') {
            if (c1 == SIZE_MAX) c1 = i;
            else if (c2 == SIZE_MAX) c2 = i;
            else if (c3 == SIZE_MAX) c3 = i;
            else return -2;
        }
    }
    if (c1 == SIZE_MAX || c2 == SIZE_MAX) return -3;
    hash_n = c1;
    size_n = c2 - c1 - 1u;
    name_n = (c3 == SIZE_MAX ? n : c3) - c2 - 1u;
    if (hash_n == 0u || size_n == 0u || name_n == 0u || name_n > JX_SECURITY_NAME_MAX) return -4;
    if (c3 != SIZE_MAX) {
        size_t extra_n = n - c3 - 1u;
        if (!all_decimal(line + c3 + 1u, extra_n)) return -5;
    }

    memset(sig, 0, sizeof *sig);
    if (parse_digest(line, hash_n, &algorithm, sig->digest) != 0) return -6;
    if (parse_u64(line + c1 + 1u, size_n, &file_size) != 0) return -7;

    sig->id = id;
    sig->type = JX_SECURITY_SIG_HASH;
    sig->verdict = JX_SECURITY_MALWARE;
    sig->hash_algorithm = (uint8_t)algorithm;
    sig->offset = JX_SECURITY_OFFSET_ANY;
    sig->file_size = file_size;
    memcpy(sig->name, line + c2 + 1u, name_n);
    sig->name[name_n] = '\0';
    return 0;
}

int jx_security_import_hash_text(const uint8_t *text,
                                 size_t text_length,
                                 jx_security_signature *out,
                                 size_t capacity,
                                 uint32_t first_id,
                                 jx_security_import_report *report) {
    size_t pos = 0u, count = 0u;
    jx_security_import_report local;
    if ((!text && text_length != 0u) || (!out && capacity != 0u)) return -1;
    memset(&local, 0, sizeof local);

    /* phpMussel signature files begin with a one-line phpMussel header. The
     * importer is specifically for Hash signature files, so the caller owns
     * file-type selection; we only strip that framing line here. */
    if (text_length >= 9u && memcmp(text, "phpMussel", 9u) == 0) {
        while (pos < text_length && text[pos] != '\n') ++pos;
        if (pos < text_length) ++pos;
    }

    while (pos < text_length) {
        size_t start = pos, end;
        while (pos < text_length && text[pos] != '\n') ++pos;
        end = pos;
        if (pos < text_length) ++pos;
        if (end > start && text[end - 1u] == '\r') --end;
        ++local.lines_seen;

        while (start < end && (text[start] == ' ' || text[start] == '\t')) ++start;
        while (end > start && (text[end - 1u] == ' ' || text[end - 1u] == '\t')) --end;
        if (start == end || text[start] == '#' || text[start] == ';') {
            ++local.ignored;
            continue;
        }
        if (count >= capacity) {
            if (report) *report = local;
            return -2;
        }
        if (import_line(text + start, end - start, &out[count], first_id + (uint32_t)count) != 0) {
            ++local.errors;
            continue;
        }
        ++count;
        ++local.imported;
    }
    if (report) *report = local;
    return (int)count;
}
