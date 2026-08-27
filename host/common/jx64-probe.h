#ifndef JX64_PROBE_H
#define JX64_PROBE_H

#include <stdint.h>

#define JX64_MAGIC "JX64B001"
#define JX64_MAGIC_BYTES 8u
#define JX64_HEADER_BYTES 48u
#define JX64_HEADER_ENTRY "JX64/header.bin"

typedef struct {
    uint16_t major;
    uint16_t minor;
    uint32_t sections;
    uint8_t manifest_sha256[32];
} jx64_identity;

/*
 * Recognize a deterministic JX compiled Book by bytes, never by extension.
 *
 * Returns:
 *   1  recognized JX64 Book; identity filled
 *   0  readable file but not JX64
 *  <0  I/O or malformed-container error
 *
 * The .64B packer stores JX64/header.bin as the first, uncompressed ZIP entry,
 * allowing a native launcher to identify the package without a ZIP library or
 * PHP runtime. Full manifest/section checksum validation belongs to the loader.
 */
int jx64_probe_file(const char *path, jx64_identity *identity);

#endif
