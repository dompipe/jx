#include <stdint.h>
#include <stdio.h>
#include <string.h>
#include "../host/common/jx-patch-ipc.h"

int main(void) {
    jx_patch_ipc_header h;
    memset(&h, 0, sizeof h);
    h.magic = JX_PATCH_IPC_MAGIC;
    h.version = JX_PATCH_IPC_VERSION;
    h.operation = JX_PATCH_IPC_OP_PUSH;
    h.manifest_length = 96u;
    h.signature_length = 64u;
    h.patch_length = 4096u;
    h.request_id = 0x1020304050607080ULL;
    if (!jx_patch_ipc_header_valid(&h)) return 2;

    uint8_t raw[JX_PATCH_IPC_HEADER_BYTES];
    jx_patch_ipc_header_write(raw, &h);
    const uint8_t prefix[] = {0x4a,0x58,0x50,0x31,0x01,0x02,0x00,0x00,0x00,0x00,0x00,0x60,0x00,0x00,0x00,0x40};
    if (memcmp(raw, prefix, sizeof prefix) != 0) return 3;

    jx_patch_ipc_header round;
    if (jx_patch_ipc_header_read(raw, sizeof raw, &round) != 0) return 4;
    if (round.operation != JX_PATCH_IPC_OP_PUSH || round.manifest_length != 96u ||
        round.signature_length != 64u || round.patch_length != 4096u ||
        round.request_id != 0x1020304050607080ULL) return 5;

    round.operation = 99u;
    if (jx_patch_ipc_header_valid(&round)) return 6;
    round = h; round.patch_length = JX_PATCH_MAX_BYTES + 1u;
    if (jx_patch_ipc_header_valid(&round)) return 7;
    round = h; round.operation = JX_PATCH_IPC_OP_STATUS;
    if (jx_patch_ipc_header_valid(&round)) return 8;

    puts("jx-patch-ipc: ok");
    return 0;
}
