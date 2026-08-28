#include "jx-idle-bus.h"
#include <string.h>

void jx_idle_bus_init(jx_idle_bus *bus) {
    if (!bus) return;
    memset(bus, 0, sizeof *bus);
    bus->version = JX_IDLE_BUS_VERSION;
}

int jx_idle_bus_add_system(jx_idle_bus *bus, jx_idle_system_fn fn, void *context) {
    if (!bus || bus->version != JX_IDLE_BUS_VERSION || !fn) return -1;
    for (size_t i = 0; i < JX_IDLE_BUS_MAX_SYSTEM_LISTENERS; ++i) {
        if (bus->system_listeners[i].in_use && bus->system_listeners[i].fn == fn &&
            bus->system_listeners[i].context == context) return -2;
    }
    for (size_t i = 0; i < JX_IDLE_BUS_MAX_SYSTEM_LISTENERS; ++i) {
        if (!bus->system_listeners[i].in_use) {
            bus->system_listeners[i].fn = fn;
            bus->system_listeners[i].context = context;
            bus->system_listeners[i].in_use = 1u;
            ++bus->system_listener_count;
            return 0;
        }
    }
    return -3;
}

int jx_idle_bus_remove_system(jx_idle_bus *bus, jx_idle_system_fn fn, void *context) {
    if (!bus || !fn) return -1;
    for (size_t i = 0; i < JX_IDLE_BUS_MAX_SYSTEM_LISTENERS; ++i) {
        jx_idle_system_listener *listener = &bus->system_listeners[i];
        if (!listener->in_use || listener->fn != fn || listener->context != context) continue;
        memset(listener, 0, sizeof *listener);
        if (bus->system_listener_count) --bus->system_listener_count;
        return 0;
    }
    return -2;
}

int jx_idle_bus_add_program(jx_idle_bus *bus, uint32_t program_id) {
    if (!bus || bus->version != JX_IDLE_BUS_VERSION || !program_id) return -1;
    size_t free_slot = JX_IDLE_BUS_MAX_PROGRAMS;
    for (size_t i = 0; i < JX_IDLE_BUS_MAX_PROGRAMS; ++i) {
        if (bus->programs[i].in_use && bus->programs[i].program_id == program_id) return -2;
        if (!bus->programs[i].in_use && free_slot == JX_IDLE_BUS_MAX_PROGRAMS) free_slot = i;
    }
    if (free_slot == JX_IDLE_BUS_MAX_PROGRAMS) return -3;
    jx_idle_program *program = &bus->programs[free_slot];
    memset(program, 0, sizeof *program);
    program->program_id = program_id;
    program->permission_epoch = bus->epoch;
    program->consumed_epoch = bus->epoch;
    program->in_use = 1u;
    ++bus->program_count;
    return 0;
}

int jx_idle_bus_remove_program(jx_idle_bus *bus, uint32_t program_id) {
    if (!bus || !program_id) return -1;
    for (size_t i = 0; i < JX_IDLE_BUS_MAX_PROGRAMS; ++i) {
        jx_idle_program *program = &bus->programs[i];
        if (!program->in_use || program->program_id != program_id) continue;
        memset(program, 0, sizeof *program);
        if (bus->program_count) --bus->program_count;
        return 0;
    }
    return -2;
}

int jx_idle_bus_take_permission(jx_idle_bus *bus,
                                uint32_t program_id,
                                uint64_t *epoch_out) {
    if (!bus || !program_id) return -1;
    for (size_t i = 0; i < JX_IDLE_BUS_MAX_PROGRAMS; ++i) {
        jx_idle_program *program = &bus->programs[i];
        if (!program->in_use || program->program_id != program_id) continue;
        if (program->permission_epoch == program->consumed_epoch) return 0;
        program->consumed_epoch = program->permission_epoch;
        if (epoch_out) *epoch_out = program->consumed_epoch;
        return 1;
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

    /* Program delivery is permission-only: no program callback, no handshake,
     * no per-program queue. One new epoch overwrites the prior unconsumed one. */
    for (size_t i = 0; i < JX_IDLE_BUS_MAX_PROGRAMS; ++i) {
        jx_idle_program *program = &bus->programs[i];
        if (program->in_use) program->permission_epoch = bus->epoch;
    }

    int system_deliveries = 0;
    for (size_t i = 0; i < JX_IDLE_BUS_MAX_SYSTEM_LISTENERS; ++i) {
        jx_idle_system_listener *listener = &bus->system_listeners[i];
        if (!listener->in_use || !listener->fn) continue;
        int rc = listener->fn(bus->epoch, monotonic_ms, listener->context);
        if (rc < 0) return rc;
        ++system_deliveries;
    }
    return system_deliveries;
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
