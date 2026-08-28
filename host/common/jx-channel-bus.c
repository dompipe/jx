#include "jx-channel-bus.h"
#include <string.h>

static jx_channel_endpoint *find_endpoint(jx_channel_bus *bus, uint32_t endpoint_id) {
    if (!bus) return NULL;
    for (size_t i = 0; i < JX_CHANNEL_BUS_MAX_ENDPOINTS; ++i) {
        if (bus->endpoints[i].in_use && bus->endpoints[i].endpoint_id == endpoint_id)
            return &bus->endpoints[i];
    }
    return NULL;
}

static int endpoint_has_channel(const jx_channel_endpoint *ep, uint16_t channel_id, uint8_t dir_mask) {
    if (!ep) return 0;
    for (size_t i = 0; i < ep->binding_count; ++i) {
        const jx_channel_binding *b = &ep->bindings[i];
        if (b->in_use && b->channel_id == channel_id && (b->direction & dir_mask)) return 1;
    }
    return 0;
}

void jx_channel_bus_init(jx_channel_bus *bus, uint32_t active_program_endpoint) {
    if (!bus) return;
    memset(bus, 0, sizeof *bus);
    bus->version = JX_CHANNEL_BUS_VERSION;
    bus->active_program_endpoint = active_program_endpoint;
}

int jx_channel_bus_add_endpoint(jx_channel_bus *bus,
                                uint32_t endpoint_id,
                                jx_channel_receive_fn receive,
                                void *context) {
    if (!bus || !endpoint_id) return -1;
    if (find_endpoint(bus, endpoint_id)) return -2;
    for (size_t i = 0; i < JX_CHANNEL_BUS_MAX_ENDPOINTS; ++i) {
        if (!bus->endpoints[i].in_use) {
            jx_channel_endpoint *ep = &bus->endpoints[i];
            memset(ep, 0, sizeof *ep);
            ep->endpoint_id = endpoint_id;
            ep->receive = receive;
            ep->context = context;
            ep->in_use = 1u;
            bus->endpoint_count++;
            return 0;
        }
    }
    return -3;
}

int jx_channel_bus_bind(jx_channel_bus *bus,
                        uint32_t endpoint_id,
                        uint16_t channel_id,
                        uint8_t direction) {
    jx_channel_endpoint *ep = find_endpoint(bus, endpoint_id);
    if (!ep || !channel_id || direction < JX_CHANNEL_DIR_IN || direction > JX_CHANNEL_DIR_INOUT) return -1;
    if (ep->binding_count >= JX_CHANNEL_BUS_MAX_CHANNELS) return -2;
    jx_channel_binding *b = &ep->bindings[ep->binding_count++];
    b->channel_id = channel_id;
    b->direction = direction;
    b->in_use = 1u;
    return 0;
}

static int deliver(jx_channel_bus *bus, const jx_channel_message *msg) {
    int deliveries = 0;
    for (size_t i = 0; i < JX_CHANNEL_BUS_MAX_ENDPOINTS; ++i) {
        jx_channel_endpoint *ep = &bus->endpoints[i];
        if (!ep->in_use || ep->endpoint_id == msg->source_endpoint) continue;
        if (!endpoint_has_channel(ep, msg->channel_id, JX_CHANNEL_DIR_IN)) continue;
        /* Program endpoints are mutually exclusive: only the active generation receives live data. */
        if ((ep->endpoint_id & 0x80000000u) && ep->endpoint_id != bus->active_program_endpoint) continue;
        if (ep->receive) {
            int rc = ep->receive(msg->channel_id, msg->message_type, msg->payload, ep->context);
            if (rc < 0) return rc;
        }
        deliveries++;
    }
    return deliveries;
}

int jx_channel_bus_publish(jx_channel_bus *bus,
                           uint32_t source_endpoint,
                           uint16_t channel_id,
                           uint32_t message_type,
                           void *payload) {
    if (!bus || !channel_id) return -1;
    jx_channel_endpoint *source = find_endpoint(bus, source_endpoint);
    if (source && !endpoint_has_channel(source, channel_id, JX_CHANNEL_DIR_OUT)) return -2;
    jx_channel_message msg = {channel_id, message_type, payload, source_endpoint};
    if (!bus->paused) return deliver(bus, &msg);
    if (bus->queue_count >= JX_CHANNEL_BUS_QUEUE_DEPTH) return -3;
    size_t at = (bus->queue_head + bus->queue_count) % JX_CHANNEL_BUS_QUEUE_DEPTH;
    bus->queue[at] = msg;
    bus->queue_count++;
    return 0;
}

void jx_channel_bus_pause(jx_channel_bus *bus) {
    if (bus) bus->paused = 1u;
}

int jx_channel_bus_resume(jx_channel_bus *bus) {
    if (!bus) return -1;
    bus->paused = 0u;
    int delivered = 0;
    while (bus->queue_count) {
        jx_channel_message msg = bus->queue[bus->queue_head];
        bus->queue_head = (bus->queue_head + 1u) % JX_CHANNEL_BUS_QUEUE_DEPTH;
        bus->queue_count--;
        int rc = deliver(bus, &msg);
        if (rc < 0) return rc;
        delivered += rc;
    }
    return delivered;
}

int jx_channel_bus_switch_program(jx_channel_bus *bus,
                                  uint32_t expected_old_endpoint,
                                  uint32_t new_endpoint) {
    if (!bus || !bus->paused) return -1;
    if (bus->active_program_endpoint != expected_old_endpoint) return -2;
    if (!find_endpoint(bus, new_endpoint)) return -3;
    bus->active_program_endpoint = new_endpoint;
    return 0;
}
