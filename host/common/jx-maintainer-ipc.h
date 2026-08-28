#ifndef JX_MAINTAINER_IPC_H
#define JX_MAINTAINER_IPC_H

#include <stddef.h>
#include <stdint.h>

#define JX_MAINTAINER_IPC_HEADER_BYTES 16u
#define JX_MAINTAINER_IPC_VERSION 1u
#define JX_MAINTAINER_IPC_OP_BAG 1u

typedef struct {
    uint8_t version;
    uint8_t operation;
    uint16_t flags;
    uint32_t request_length;
    uint32_t json_length;
} jx_maintainer_ipc_header;

int jx_maintainer_ipc_write(uint8_t out[JX_MAINTAINER_IPC_HEADER_BYTES],
                            const jx_maintainer_ipc_header *header);
int jx_maintainer_ipc_read(const uint8_t *in, size_t length,
                           jx_maintainer_ipc_header *header);

#endif
