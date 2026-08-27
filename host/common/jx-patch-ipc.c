#include "jx-patch-ipc.h"
#include <string.h>

static void write_u32(uint8_t *out, uint32_t v) {
    out[0] = (uint8_t)(v >> 24); out[1] = (uint8_t)(v >> 16); out[2] = (uint8_t)(v >> 8); out[3] = (uint8_t)v;
}
static uint32_t read_u32(const uint8_t *in) {
    return ((uint32_t)in[0] << 24) | ((uint32_t)in[1] << 16) | ((uint32_t)in[2] << 8) | (uint32_t)in[3];
}
static void write_u64(uint8_t *out, uint64_t v) {
    for (int i = 7; i >= 0; --i) { out[i] = (uint8_t)v; v >>= 8; }
}
static uint64_t read_u64(const uint8_t *in) {
    uint64_t v = 0u; for (size_t i = 0; i < 8u; ++i) v = (v << 8) | in[i]; return v;
}

int jx_patch_ipc_operation_valid(uint8_t operation) {
    return operation == JX_PATCH_IPC_OP_STATUS || operation == JX_PATCH_IPC_OP_PUSH || operation == JX_PATCH_IPC_OP_ROLLBACK;
}

int jx_patch_ipc_header_valid(const jx_patch_ipc_header *h) {
    if (!h || h->magic != JX_PATCH_IPC_MAGIC || h->version != JX_PATCH_IPC_VERSION || !jx_patch_ipc_operation_valid(h->operation)) return 0;
    if (h->operation == JX_PATCH_IPC_OP_PUSH) {
        if (h->manifest_length == 0u || h->signature_length == 0u || h->patch_length == 0u || h->patch_length > JX_PATCH_MAX_BYTES) return 0;
    } else if (h->manifest_length != 0u || h->signature_length != 0u || h->patch_length != 0u) return 0;
    return h->reserved == 0u;
}

void jx_patch_ipc_header_write(uint8_t out[JX_PATCH_IPC_HEADER_BYTES], const jx_patch_ipc_header *h) {
    memset(out, 0, JX_PATCH_IPC_HEADER_BYTES);
    write_u32(out + 0, h->magic); out[4] = h->version; out[5] = h->operation;
    out[6] = (uint8_t)(h->flags >> 8); out[7] = (uint8_t)h->flags;
    write_u32(out + 8, h->manifest_length); write_u32(out + 12, h->signature_length); write_u32(out + 16, h->patch_length);
    write_u64(out + 20, h->request_id); write_u32(out + 28, h->reserved);
}

int jx_patch_ipc_header_read(const uint8_t *data, size_t length, jx_patch_ipc_header *out) {
    if (!data || !out || length < JX_PATCH_IPC_HEADER_BYTES) return -1;
    memset(out, 0, sizeof *out);
    out->magic = read_u32(data + 0); out->version = data[4]; out->operation = data[5]; out->flags = (uint16_t)(((uint16_t)data[6] << 8) | data[7]);
    out->manifest_length = read_u32(data + 8); out->signature_length = read_u32(data + 12); out->patch_length = read_u32(data + 16);
    out->request_id = read_u64(data + 20); out->reserved = read_u32(data + 28);
    return jx_patch_ipc_header_valid(out) ? 0 : -2;
}
