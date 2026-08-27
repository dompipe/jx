#include <stdio.h>
#include <stdint.h>
#include "../host/common/jx-api-dispatch.h"

int main(void) {
    if (jx_api_address(9u, 1u, JX_API_SHADOW_REQUEST) != 0x090100u) return 2;
    if (jx_api_address(9u, 1u, JX_API_SHADOW_SUCCESS) != 0x090101u) return 3;
    if (!jx_api_shadow_valid(JX_API_SHADOW_CANCEL) || jx_api_shadow_valid(5u)) return 4;

    jx_api_header h = { 0x10203040u, 503u, JX_API_CONTENT_JSON, 0x80u };
    uint8_t bytes[JX_API_HEADER_BYTES];
    jx_api_header_write(bytes, &h);
    const uint8_t expected[JX_API_HEADER_BYTES] = { 0x10,0x20,0x30,0x40,0x01,0xf7,0x01,0x80 };
    for (size_t i = 0; i < JX_API_HEADER_BYTES; ++i) if (bytes[i] != expected[i]) return 5;

    jx_api_header round = {0};
    if (jx_api_header_read(bytes, sizeof bytes, &round) != 0) return 6;
    if (round.call_id != h.call_id || round.status != h.status ||
        round.content_type != h.content_type || round.flags != h.flags) return 7;

    puts("jx-api-dispatch-abi: ok");
    return 0;
}
