#ifndef JX_LIVE_PATCH_WIRE_H
#define JX_LIVE_PATCH_WIRE_H
#include <stddef.h>
#include <stdint.h>
#include "jx-live-patch.h"
#define JX_PATCH_MANIFEST_WIRE_BYTES 116u
int jx_patch_manifest_write(uint8_t out[JX_PATCH_MANIFEST_WIRE_BYTES], const jx_patch_manifest *m);
int jx_patch_manifest_read(const uint8_t *in, size_t length, jx_patch_manifest *m);
#endif
