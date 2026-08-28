#ifndef JX_SECURITY_HASH_H
#define JX_SECURITY_HASH_H

#include <stddef.h>
#include <stdint.h>

#define JX_SECURITY_MD5_BYTES 16u
#define JX_SECURITY_SHA1_BYTES 20u
#define JX_SECURITY_SHA256_BYTES 32u

int jx_security_md5(const uint8_t *data, size_t length,
                    uint8_t out[JX_SECURITY_MD5_BYTES]);
int jx_security_sha1(const uint8_t *data, size_t length,
                     uint8_t out[JX_SECURITY_SHA1_BYTES]);
int jx_security_sha256(const uint8_t *data, size_t length,
                       uint8_t out[JX_SECURITY_SHA256_BYTES]);

#endif
