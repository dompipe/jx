#ifndef JX_IDLE_BUS_H
#define JX_IDLE_BUS_H

#include <stddef.h>
#include <stdint.h>

#define JX_IDLE_BUS_VERSION 3u
#define JX_IDLE_BUS_MAX_SYSTEM_LISTENERS 32u
#define JX_IDLE_BUS_MAX_PROGRAMS 256u
#define JX_IDLE_BUS_PERIOD_MS 250u
#define JX_IDLE_CALL_BYTES 3u

/* One byte beyond the ordinary two-byte cold-call form.
 * This is a fixed system envelope, not an ASM family lookup:
 *   0x7f 0x00 0x01 = SYSTEM | IDLE-BUS | TICK
 *
 * The host emits this once per 250 ms epoch. It grants update permission to
 * every currently registered program by advancing that program's mailbox.
 * It does NOT synchronously invoke every program. Programs consume the newest
 * permission epoch when they next run; multiple unconsumed ticks coalesce.
 */
#define JX_IDLE_CALL_SYSTEM 0x7fu
#define JX_IDLE_CALL_BUS    0x00u
#define JX_IDLE_CALL_TICK   0x01u

typedef int (*jx_idle_system_fn)(uint64_t epoch,
                                 uint64_t monotonic_ms,
                                 void *context);

typedef struct {
    jx_idle_system_fn fn;
    void *context;
    uint8_t in_use;
} jx_idle_system_listener;

typedef struct {
    uint32_t program_id;
    uint64_t permission_epoch;
    uint64_t consumed_epoch;
    uint8_t in_use;
} jx_idle_program;

typedef struct {
    uint8_t version;
    uint64_t epoch;
    uint64_t last_tick_ms;
    size_t system_listener_count;
    size_t program_count;
    jx_idle_system_listener system_listeners[JX_IDLE_BUS_MAX_SYSTEM_LISTENERS];
    jx_idle_program programs[JX_IDLE_BUS_MAX_PROGRAMS];
} jx_idle_bus;

void jx_idle_bus_init(jx_idle_bus *bus);
int jx_idle_bus_add_system(jx_idle_bus *bus, jx_idle_system_fn fn, void *context);
int jx_idle_bus_remove_system(jx_idle_bus *bus, jx_idle_system_fn fn, void *context);
int jx_idle_bus_add_program(jx_idle_bus *bus, uint32_t program_id);
int jx_idle_bus_remove_program(jx_idle_bus *bus, uint32_t program_id);
int jx_idle_bus_take_permission(jx_idle_bus *bus,
                                uint32_t program_id,
                                uint64_t *epoch_out);
int jx_idle_bus_encode(uint8_t out[JX_IDLE_CALL_BYTES]);
int jx_idle_bus_is_tick(const uint8_t *code, size_t length);
int jx_idle_bus_tick(jx_idle_bus *bus, uint64_t monotonic_ms);
int jx_idle_bus_due(const jx_idle_bus *bus, uint64_t monotonic_ms);
int jx_idle_bus_maybe_tick(jx_idle_bus *bus, uint64_t monotonic_ms);

#endif
