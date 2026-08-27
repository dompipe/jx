#include <stdint.h>
#include <stdio.h>
#include <string.h>
#include "../host/common/jx-hot-event.h"

int main(void) {
    const jx_hot_address address = jx_hot_address_make(3u, 12u, 1u);
    if (address != 0x030c01u) {
        fputs("jx-hot-event-abi: packed address mismatch\n", stderr);
        return 1;
    }
    jx_hot_route route = jx_hot_address_unpack(address);
    if (route.reg != 3u || route.slot != 12u || route.shadow != 1u) {
        fputs("jx-hot-event-abi: unpack mismatch\n", stderr);
        return 2;
    }
    if (jx_hot_address_ref(address) != 0x0c01u) {
        fputs("jx-hot-event-abi: ref mismatch\n", stderr);
        return 3;
    }
    if (!jx_hot_delivery_valid(JX_HOT_DELIVERY_LATEST) ||
        !jx_hot_delivery_valid(JX_HOT_DELIVERY_QUEUE) ||
        !jx_hot_delivery_valid(JX_HOT_DELIVERY_ONCE) ||
        !jx_hot_delivery_valid(JX_HOT_DELIVERY_COUNT) ||
        !jx_hot_delivery_valid(JX_HOT_DELIVERY_ACCUMULATE)) {
        fputs("jx-hot-event-abi: delivery policy mismatch\n", stderr);
        return 4;
    }

    const uint8_t payload[] = { 0x01u, 0x02u, 'c', 'l', 'i', 'c', 'k' };
    uint8_t packet[64];
    size_t n = jx_hot_packet_encode(packet, sizeof packet, address,
                                    JX_HOT_DELIVERY_COUNT, 0x80u,
                                    payload, (uint16_t)sizeof payload);
    const uint8_t expected_header[] = { 1u, 3u, 12u, 1u, 3u, 128u, 0u, 7u };
    if (n != JX_HOT_PACKET_HEADER_BYTES + sizeof payload ||
        memcmp(packet, expected_header, sizeof expected_header) != 0 ||
        memcmp(packet + JX_HOT_PACKET_HEADER_BYTES, payload, sizeof payload) != 0) {
        fputs("jx-hot-event-abi: encoded packet mismatch\n", stderr);
        return 5;
    }

    jx_hot_packet_view view;
    if (!jx_hot_packet_decode(packet, n, &view) ||
        view.address != address || view.delivery != JX_HOT_DELIVERY_COUNT ||
        view.flags != 0x80u || view.payload_length != sizeof payload ||
        memcmp(view.payload, payload, sizeof payload) != 0) {
        fputs("jx-hot-event-abi: packet round-trip mismatch\n", stderr);
        return 6;
    }

    printf("jx-hot-event-abi: ok W3:[12:1]=0x%06x packet=%zu bytes\n", address, n);
    return 0;
}
