#ifndef JX_IDLE_BUS_H
#define JX_IDLE_BUS_H

#include <stddef.h>
#include <stdint.h>

#define JX_IDLE_BUS_VERSION 1u
#define JX_IDLE_BUS_MAX_LISTENERS 64u
#define JX_IDLE_BUS_PERIOD_MS 500u
#define JX_IDLE_CALL_BYTES 3u

/* One byte beyond the ordinary two-byte cold-call form.
 * This is a system envelope, not an ASM family lookup:
 *   0x7f 0x00 0x01 = SYSTEM | IDLE-BUS | TICK
 */
#define JX_IDLE_CALL_SYSTEM 0x7fu
#define JX_IDLE_CALL_BUS    0x00u
#define JX_IDLE_CALL_TICK   0x01u

typedef int (*jx_idle_bus_fn)(uint64_t epoch,
                              uint64_t monotonic_ms,
                              void *context);

typedef struct {
    jx_idle_bus_fn fn;
    void *context;
    uint8_t in_use;
} jx_idle_listener;

typedef struct {
    uint8_t version;
    uint64_t epoch;
    uint64_t last_tick_ms;
    size_t listener_count;
    jx_idle_listener listeners[JX_IDLE_BUS_MAX_LISTENERS];
} jx_idle_bus;

void jx_idle_bus_init(jx_idle_bus *bus);
int jx_idle_bus_add(jx_idle_bus *bus, jx_idle_bus_fn fn, void *context);
int jx_idle_bus_remove(jx_idle_bus *bus, jx_idle_bus_fn fn, void *context);
int jx_idle_bus_encode(uint8_t out[JX_IDLE_CALL_BYTES]);
int jx_idle_bus_is_tick(const uint8_t *code, size_t length);
int jx_idle_bus_tick(jx_idle_bus *bus, uint64_t monotonic_ms);
int jx_idle_bus_due(const jx_idle_bus *bus, uint64_t monotonic_ms);
int jx_idle_bus_maybe_tick(jx_idle_bus *bus, uint64_t monotonic_ms);

#endif
