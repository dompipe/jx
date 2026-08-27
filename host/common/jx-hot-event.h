#ifndef JX_HOT_EVENT_H
#define JX_HOT_EVENT_H

#include <stdint.h>

#define JX_HOT_EVENT_VERSION 1u
#define JX_HOT_ADDRESS_MAX 0x00ffffffu

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

#endif
