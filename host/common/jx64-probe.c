#include "jx64-probe.h"
#include <stdio.h>
#include <string.h>

static uint16_t le16(const uint8_t *p) {
    return (uint16_t)((uint16_t)p[0] | ((uint16_t)p[1] << 8));
}

static uint32_t le32(const uint8_t *p) {
    return (uint32_t)p[0] |
           ((uint32_t)p[1] << 8) |
           ((uint32_t)p[2] << 16) |
           ((uint32_t)p[3] << 24);
}

int jx64_probe_file(const char *path, jx64_identity *identity) {
    if (!path || !*path || !identity) return -1;
    FILE *fp = fopen(path, "rb");
    if (!fp) return -2;

    uint8_t local[30];
    size_t got = fread(local, 1, sizeof local, fp);
    if (got != sizeof local) {
        fclose(fp);
        return got == 0 ? -3 : 0;
    }

    /* ZIP local-file header signature PK\x03\x04. */
    if (le32(local) != 0x04034b50u) {
        fclose(fp);
        return 0;
    }

    const uint16_t flags = le16(local + 6);
    const uint16_t method = le16(local + 8);
    const uint32_t compressed = le32(local + 18);
    const uint32_t uncompressed = le32(local + 22);
    const uint16_t name_len = le16(local + 26);
    const uint16_t extra_len = le16(local + 28);

    /* Deterministic JX64 uses STORE and no deferred data descriptor. */
    if ((flags & 0x0008u) != 0u || method != 0u ||
        compressed != JX64_HEADER_BYTES || uncompressed != JX64_HEADER_BYTES ||
        name_len == 0u || name_len > 1024u) {
        fclose(fp);
        return 0;
    }

    char name[1025];
    if (fread(name, 1, name_len, fp) != name_len) {
        fclose(fp);
        return -4;
    }
    name[name_len] = '\0';
    if (strcmp(name, JX64_HEADER_ENTRY) != 0) {
        fclose(fp);
        return 0;
    }

    if (extra_len && fseek(fp, (long)extra_len, SEEK_CUR) != 0) {
        fclose(fp);
        return -5;
    }

    uint8_t header[JX64_HEADER_BYTES];
    if (fread(header, 1, sizeof header, fp) != sizeof header) {
        fclose(fp);
        return -6;
    }
    fclose(fp);

    if (memcmp(header, JX64_MAGIC, JX64_MAGIC_BYTES) != 0) return 0;

    identity->major = le16(header + 8);
    identity->minor = le16(header + 10);
    identity->sections = le32(header + 12);
    memcpy(identity->manifest_sha256, header + 16, 32);

    if (identity->major != 1u || identity->minor != 0u || identity->sections == 0u) return 0;
    return 1;
}
