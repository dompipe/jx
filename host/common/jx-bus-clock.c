#include "jx-bus-clock.h"
#include <string.h>

static uint32_t interval_for_gear(uint8_t gear) {
    switch ((jx_bus_clock_gear)gear) {
        case JX_BUS_CLOCK_BURST: return JX_BUS_CLOCK_BURST_MS;
        case JX_BUS_CLOCK_BUSY: return JX_BUS_CLOCK_BUSY_MS;
        case JX_BUS_CLOCK_NORMAL: return JX_BUS_CLOCK_BASE_MS;
        case JX_BUS_CLOCK_QUIET:
        default: return JX_BUS_CLOCK_BASE_MS;
    }
}

void jx_bus_clock_init(jx_bus_clock *clock) {
    if (!clock) return;
    memset(clock, 0, sizeof *clock);
    clock->version = JX_BUS_CLOCK_VERSION;
    clock->gear = JX_BUS_CLOCK_NORMAL;
    clock->interval_ms = JX_BUS_CLOCK_BASE_MS;
}

uint32_t jx_bus_clock_observe(jx_bus_clock *clock,
                              uint64_t active_bus_mask,
                              uint32_t data_count) {
    if (!clock || clock->version != JX_BUS_CLOCK_VERSION)
        return JX_BUS_CLOCK_BASE_MS;

    ++clock->logical_epoch;
    clock->active_bus_mask = active_bus_mask;
    clock->last_data_count = data_count;

    if (active_bus_mask && data_count) {
        clock->quiet_streak = 0u;
        if (clock->active_streak < UINT8_MAX) ++clock->active_streak;

        if (clock->active_streak >= (JX_BUS_CLOCK_ACTIVITY_WINDOW * 2u))
            clock->gear = JX_BUS_CLOCK_BURST;
        else if (clock->active_streak >= JX_BUS_CLOCK_ACTIVITY_WINDOW)
            clock->gear = JX_BUS_CLOCK_BUSY;
        else
            clock->gear = JX_BUS_CLOCK_NORMAL;
    } else {
        clock->active_streak = 0u;
        if (clock->quiet_streak < UINT8_MAX) ++clock->quiet_streak;

        if (clock->quiet_streak >= JX_BUS_CLOCK_QUIET_DECAY)
            clock->gear = JX_BUS_CLOCK_QUIET;
        else if (clock->gear > JX_BUS_CLOCK_NORMAL)
            --clock->gear;
        else
            clock->gear = JX_BUS_CLOCK_NORMAL;
    }

    clock->interval_ms = interval_for_gear(clock->gear);
    return clock->interval_ms;
}

uint32_t jx_bus_clock_interval(const jx_bus_clock *clock) {
    if (!clock || clock->version != JX_BUS_CLOCK_VERSION)
        return JX_BUS_CLOCK_BASE_MS;
    return clock->interval_ms;
}

jx_bus_clock_gear jx_bus_clock_gear_get(const jx_bus_clock *clock) {
    if (!clock || clock->version != JX_BUS_CLOCK_VERSION)
        return JX_BUS_CLOCK_NORMAL;
    return (jx_bus_clock_gear)clock->gear;
}
