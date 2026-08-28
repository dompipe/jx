#ifndef JX_CHANNEL_BUS_H
#define JX_CHANNEL_BUS_H

#include <stddef.h>
#include <stdint.h>

#define JX_CHANNEL_BUS_VERSION 1u
#define JX_CHANNEL_BUS_MAX_CHANNELS 64u
#define JX_CHANNEL_BUS_MAX_ENDPOINTS 64u
#define JX_CHANNEL_BUS_QUEUE_DEPTH 64u

typedef enum {
    JX_CHANNEL_DIR_IN = 1,
    JX_CHANNEL_DIR_OUT = 2,
    JX_CHANNEL_DIR_INOUT = 3
} jx_channel_direction;

typedef int (*jx_channel_receive_fn)(uint16_t channel_id,
                                     uint32_t message_type,
                                     void *payload,
                                     void *context);

typedef struct {
    uint16_t channel_id;
    uint8_t direction;
    uint8_t in_use;
} jx_channel_binding;

typedef struct {
    uint32_t endpoint_id;
    jx_channel_receive_fn receive;
    void *context;
    jx_channel_binding bindings[JX_CHANNEL_BUS_MAX_CHANNELS];
    size_t binding_count;
    uint8_t in_use;
} jx_channel_endpoint;

typedef struct {
    uint16_t channel_id;
    uint32_t message_type;
    void *payload;
    uint32_t source_endpoint;
} jx_channel_message;

typedef struct {
    uint8_t version;
    uint8_t paused;
    uint32_t active_program_endpoint;
    jx_channel_endpoint endpoints[JX_CHANNEL_BUS_MAX_ENDPOINTS];
    size_t endpoint_count;
    jx_channel_message queue[JX_CHANNEL_BUS_QUEUE_DEPTH];
    size_t queue_head;
    size_t queue_count;
} jx_channel_bus;

void jx_channel_bus_init(jx_channel_bus *bus, uint32_t active_program_endpoint);
int jx_channel_bus_add_endpoint(jx_channel_bus *bus,
                                uint32_t endpoint_id,
                                jx_channel_receive_fn receive,
                                void *context);
int jx_channel_bus_bind(jx_channel_bus *bus,
                        uint32_t endpoint_id,
                        uint16_t channel_id,
                        uint8_t direction);
int jx_channel_bus_publish(jx_channel_bus *bus,
                           uint32_t source_endpoint,
                           uint16_t channel_id,
                           uint32_t message_type,
                           void *payload);
void jx_channel_bus_pause(jx_channel_bus *bus);
int jx_channel_bus_resume(jx_channel_bus *bus);
int jx_channel_bus_switch_program(jx_channel_bus *bus,
                                  uint32_t expected_old_endpoint,
                                  uint32_t new_endpoint);

#endif
