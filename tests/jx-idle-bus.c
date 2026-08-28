#include "../host/common/jx-idle-bus.h"
#include <assert.h>
#include <stdio.h>
#include <string.h>

typedef struct {
    unsigned calls;
    uint64_t last_epoch;
    uint64_t last_ms;
} probe;

static int on_tick(uint64_t epoch, uint64_t monotonic_ms, void *context) {
    probe *p = (probe *)context;
    ++p->calls;
    p->last_epoch = epoch;
    p->last_ms = monotonic_ms;
    return 0;
}

int main(void) {
    jx_idle_bus bus;
    jx_idle_bus_init(&bus);
    assert(bus.version == JX_IDLE_BUS_VERSION);

    uint8_t code[JX_IDLE_CALL_BYTES] = {0};
    assert(jx_idle_bus_encode(code) == 0);
    const uint8_t expected[JX_IDLE_CALL_BYTES] = {0x7f, 0x00, 0x01};
    assert(memcmp(code, expected, sizeof expected) == 0);
    assert(jx_idle_bus_is_tick(code, sizeof code));
    assert(!jx_idle_bus_is_tick(code, 2u));

    probe a = {0}, b = {0};
    assert(jx_idle_bus_add(&bus, on_tick, &a) == 0);
    assert(jx_idle_bus_add(&bus, on_tick, &b) == 0);
    assert(jx_idle_bus_add(&bus, on_tick, &a) == -2);

    assert(jx_idle_bus_maybe_tick(&bus, 1000u) == 2);
    assert(a.calls == 1u && b.calls == 1u);
    assert(a.last_epoch == 1u && a.last_ms == 1000u);

    assert(jx_idle_bus_maybe_tick(&bus, 1499u) == 0);
    assert(a.calls == 1u && b.calls == 1u);

    assert(jx_idle_bus_maybe_tick(&bus, 1500u) == 2);
    assert(a.calls == 2u && b.calls == 2u);
    assert(a.last_epoch == 2u && a.last_ms == 1500u);

    assert(jx_idle_bus_remove(&bus, on_tick, &b) == 0);
    assert(jx_idle_bus_tick(&bus, 2000u) == 1);
    assert(a.calls == 3u && b.calls == 2u);

    puts("jx-idle-bus: 3-byte tick + shared 500ms fanout ok");
    return 0;
}
