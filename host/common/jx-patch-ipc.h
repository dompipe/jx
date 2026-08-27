#ifndef JX_PATCH_IPC_H
#define JX_PATCH_IPC_H

#include <stddef.h>
#include <stdint.h>
#include "jx-live-patch.h"

#define JX_PATCH_IPC_VERSION 1u
#define JX_PATCH_IPC_MAGIC 0x4a585031u /* JXP1 */
#define JX_PATCH_IPC_HEADER_BYTES 32u
#define JX_PATCH_IPC_SOCKET_PATH_MAX 103u

#define JX_PATCH_IPC_OP_STATUS   1u
#define JX_PATCH_IPC_OP_PUSH     2u
#define JX_PATCH_IPC_OP_ROLLBACK 3u

typedef struct {
    uint32_t magic;
    uint8_t version;
    uint8_t operation;
    uint16_t flags;
    uint32_t manifest_length;
    uint32_t signature_length;
    uint32_t patch_length;
    uint64_t request_id;
    uint32_t reserved;
} jx_patch_ipc_header;

int jx_patch_ipc_operation_valid(uint8_t operation);
int jx_patch_ipc_header_valid(const jx_patch_ipc_header *header);
void jx_patch_ipc_header_write(uint8_t out[JX_PATCH_IPC_HEADER_BYTES], const jx_patch_ipc_header *header);
int jx_patch_ipc_header_read(const uint8_t *data, size_t length, jx_patch_ipc_header *out);

#endif
