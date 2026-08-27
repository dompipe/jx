#ifndef JX_HOT_EVENT_H
#define JX_HOT_EVENT_H

#include <stddef.h>
#include <stdint.h>
#include <string.h>

#define JX_HOT_EVENT_VERSION 1u
#define JX_HOT_ADDRESS_MAX 0x00ffffffu
#define JX_HOT_PACKET_HEADER_BYTES 8u
#define JX_HOT_PACKET_MAX_PAYLOAD 65535u

typedef uint32_t jx_hot_address;

typedef enum {
    JX_HOT_DELIVERY_LATEST = 0,
    JX_HOT_DELIVERY_QUEUE = 1,
    JX_HOT_DELIVERY_ONCE = 2,
    JX_HOT_DELIVERY_COUNT = 3,
    JX_HOT_DELIVERY_ACCUMULATE = 4
} jx_hot_delivery;

typedef struct {
    uint8_t reg;
    uint8_t slot;
    uint8_t shadow;
} jx_hot_route;

typedef struct {
    jx_hot_address address;
    jx_hot_delivery delivery;
    uint8_t flags;
    const uint8_t *payload;
    uint16_t payload_length;
} jx_hot_packet_view;

static inline jx_hot_address jx_hot_address_make(uint8_t reg, uint8_t slot, uint8_t shadow) {
    return ((uint32_t)reg << 16) | ((uint32_t)slot << 8) | (uint32_t)shadow;
}

static inline jx_hot_route jx_hot_address_unpack(jx_hot_address address) {
    jx_hot_route r;
    r.reg = (uint8_t)((address >> 16) & 0xffu);
    r.slot = (uint8_t)((address >> 8) & 0xffu);
    r.shadow = (uint8_t)(address & 0xffu);
    return r;
}

static inline uint16_t jx_hot_address_ref(jx_hot_address address) {
    return (uint16_t)(address & 0xffffu);
}

static inline int jx_hot_delivery_valid(jx_hot_delivery delivery) {
    return delivery >= JX_HOT_DELIVERY_LATEST && delivery <= JX_HOT_DELIVERY_ACCUMULATE;
}

static inline size_t jx_hot_packet_size(uint16_t payload_length) {
    return JX_HOT_PACKET_HEADER_BYTES + (size_t)payload_length;
}

/* Returns encoded byte count, or 0 when the output buffer is too small or the
 * delivery policy is invalid. The route is always bytes 1..3. */
static inline size_t jx_hot_packet_encode(
    uint8_t *out,
    size_t capacity,
    jx_hot_address address,
    jx_hot_delivery delivery,
    uint8_t flags,
    const void *payload,
    uint16_t payload_length
) {
    size_t need = jx_hot_packet_size(payload_length);
    if (!out || capacity < need || address > JX_HOT_ADDRESS_MAX || !jx_hot_delivery_valid(delivery)) return 0u;
    if (payload_length > 0u && !payload) return 0u;
    jx_hot_route route = jx_hot_address_unpack(address);
    out[0] = JX_HOT_EVENT_VERSION;
    out[1] = route.reg;
    out[2] = route.slot;
    out[3] = route.shadow;
    out[4] = (uint8_t)delivery;
    out[5] = flags;
    out[6] = (uint8_t)((payload_length >> 8) & 0xffu);
    out[7] = (uint8_t)(payload_length & 0xffu);
    if (payload_length > 0u) memcpy(out + JX_HOT_PACKET_HEADER_BYTES, payload, payload_length);
    return need;
}

/* Returns 1 for a valid packet, 0 otherwise. The returned payload view points
 * into the caller-owned packet buffer. */
static inline int jx_hot_packet_decode(const uint8_t *packet, size_t length, jx_hot_packet_view *out) {
    if (!packet || !out || length < JX_HOT_PACKET_HEADER_BYTES) return 0;
    if (packet[0] != JX_HOT_EVENT_VERSION) return 0;
    jx_hot_delivery delivery = (jx_hot_delivery)packet[4];
    if (!jx_hot_delivery_valid(delivery)) return 0;
    uint16_t payload_length = (uint16_t)(((uint16_t)packet[6] << 8) | (uint16_t)packet[7]);
    if (length != jx_hot_packet_size(payload_length)) return 0;
    out->address = jx_hot_address_make(packet[1], packet[2], packet[3]);
    out->delivery = delivery;
    out->flags = packet[5];
    out->payload_length = payload_length;
    out->payload = packet + JX_HOT_PACKET_HEADER_BYTES;
    return 1;
}

#endif
