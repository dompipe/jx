#ifndef JX_API_DISPATCH_H
#define JX_API_DISPATCH_H

#include <stddef.h>
#include <stdint.h>
#include "jx-hot-event.h"

#define JX_API_ABI_VERSION 1u
#define JX_API_HEADER_BYTES 8u

#define JX_API_SHADOW_REQUEST 0u
#define JX_API_SHADOW_SUCCESS 1u
#define JX_API_SHADOW_ERROR   2u
#define JX_API_SHADOW_STREAM  3u
#define JX_API_SHADOW_CANCEL  4u

#define JX_API_CONTENT_BINARY 0u
#define JX_API_CONTENT_JSON   1u
#define JX_API_CONTENT_UTF8   2u

typedef enum {
    JX_API_TRANSPORT_DIRECT = 0,
    JX_API_TRANSPORT_NATIVE = 1,
    JX_API_TRANSPORT_UNIX = 2,
    JX_API_TRANSPORT_UDP = 3,
    JX_API_TRANSPORT_HTTP = 4,
    JX_API_TRANSPORT_HTTPS = 5,
    JX_API_TRANSPORT_SSH = 6,
    JX_API_TRANSPORT_DEVICE = 7
} jx_api_transport;

typedef struct {
    uint32_t call_id;
    uint16_t status;
    uint8_t content_type;
    uint8_t flags;
} jx_api_header;

static inline int jx_api_shadow_valid(uint8_t shadow) {
    return shadow <= JX_API_SHADOW_CANCEL;
}

static inline int jx_api_transport_valid(uint8_t transport) {
    return transport <= (uint8_t)JX_API_TRANSPORT_DEVICE;
}

static inline int jx_api_transport_secure_remote(uint8_t transport) {
    return transport == (uint8_t)JX_API_TRANSPORT_HTTPS ||
           transport == (uint8_t)JX_API_TRANSPORT_SSH;
}

static inline uint32_t jx_api_address(uint8_t reg, uint8_t slot, uint8_t shadow) {
    return jx_hot_address_make(reg, slot, shadow);
}

static inline void jx_api_header_write(uint8_t out[JX_API_HEADER_BYTES], const jx_api_header *header) {
    uint32_t id = header ? header->call_id : 0u;
    uint16_t status = header ? header->status : 0u;
    out[0] = (uint8_t)(id >> 24);
    out[1] = (uint8_t)(id >> 16);
    out[2] = (uint8_t)(id >> 8);
    out[3] = (uint8_t)id;
    out[4] = (uint8_t)(status >> 8);
    out[5] = (uint8_t)status;
    out[6] = header ? header->content_type : 0u;
    out[7] = header ? header->flags : 0u;
}

static inline int jx_api_header_read(const uint8_t *bytes, size_t length, jx_api_header *out) {
    if (!bytes || !out || length < JX_API_HEADER_BYTES) return -1;
    out->call_id = ((uint32_t)bytes[0] << 24) |
                   ((uint32_t)bytes[1] << 16) |
                   ((uint32_t)bytes[2] << 8) |
                   (uint32_t)bytes[3];
    out->status = (uint16_t)(((uint16_t)bytes[4] << 8) | bytes[5]);
    out->content_type = bytes[6];
    out->flags = bytes[7];
    return 0;
}

#endif
