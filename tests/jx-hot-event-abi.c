#include <stdint.h>
#include <stdio.h>
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
    printf("jx-hot-event-abi: ok W3:[12:1]=0x%06x\n", address);
    return 0;
}
