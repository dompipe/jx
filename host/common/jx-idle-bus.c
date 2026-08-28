#include "jx-idle-bus.h"
#include <string.h>

void jx_idle_bus_init(jx_idle_bus *bus) {
    if (!bus) return;
    memset(bus, 0, sizeof *bus);
    bus->version = JX_IDLE_BUS_VERSION;
}

int jx_idle_bus_add(jx_idle_bus *bus, jx_idle_bus_fn fn, void *context) {
    if (!bus || bus->version != JX_IDLE_BUS_VERSION || !fn) return -1;
    for (size_t i = 0; i < JX_IDLE_BUS_MAX_LISTENERS; ++i) {
        if (bus->listeners[i].in_use && bus->listeners[i].fn == fn &&
            bus->listeners[i].context == context) return -2;
    }
    for (size_t i = 0; i < JX_IDLE_BUS_MAX_LISTENERS; ++i) {
        if (!bus->listeners[i].in_use) {
            bus->listeners[i].fn = fn;
            bus->listeners[i].context = context;
            bus->listeners[i].in_use = 1u;
            ++bus->listener_count;
            return 0;
        }
    }
    return -3;
}

int jx_idle_bus_remove(jx_idle_bus *bus, jx_idle_bus_fn fn, void *context) {
    if (!bus || !fn) return -1;
    for (size_t i = 0; i < JX_IDLE_BUS_MAX_LISTENERS; ++i) {
        jx_idle_listener *listener = &bus->listeners[i];
        if (!listener->in_use || listener->fn != fn || listener->context != context) continue;
        memset(listener, 0, sizeof *listener);
        if (bus->listener_count) --bus->listener_count;
        return 0;
    }
    return -2;
}

int jx_idle_bus_encode(uint8_t out[JX_IDLE_CALL_BYTES]) {
    if (!out) return -1;
    out[0] = JX_IDLE_CALL_SYSTEM;
    out[1] = JX_IDLE_CALL_BUS;
    out[2] = JX_IDLE_CALL_TICK;
    return 0;
}

int jx_idle_bus_is_tick(const uint8_t *code, size_t length) {
    return code && length >= JX_IDLE_CALL_BYTES &&
           code[0] == JX_IDLE_CALL_SYSTEM &&
           code[1] == JX_IDLE_CALL_BUS &&
           code[2] == JX_IDLE_CALL_TICK;
}

int jx_idle_bus_tick(jx_idle_bus *bus, uint64_t monotonic_ms) {
    if (!bus || bus->version != JX_IDLE_BUS_VERSION) return -1;
    ++bus->epoch;
    bus->last_tick_ms = monotonic_ms;
    int deliveries = 0;
    for (size_t i = 0; i < JX_IDLE_BUS_MAX_LISTENERS; ++i) {
        jx_idle_listener *listener = &bus->listeners[i];
        if (!listener->in_use || !listener->fn) continue;
        int rc = listener->fn(bus->epoch, monotonic_ms, listener->context);
        if (rc < 0) return rc;
        ++deliveries;
    }
    return deliveries;
}

int jx_idle_bus_due(const jx_idle_bus *bus, uint64_t monotonic_ms) {
    if (!bus || bus->version != JX_IDLE_BUS_VERSION) return 0;
    if (bus->last_tick_ms == 0u) return 1;
    if (monotonic_ms < bus->last_tick_ms) return 1;
    return monotonic_ms - bus->last_tick_ms >= JX_IDLE_BUS_PERIOD_MS;
}

int jx_idle_bus_maybe_tick(jx_idle_bus *bus, uint64_t monotonic_ms) {
    if (!jx_idle_bus_due(bus, monotonic_ms)) return 0;
    return jx_idle_bus_tick(bus, monotonic_ms);
}
