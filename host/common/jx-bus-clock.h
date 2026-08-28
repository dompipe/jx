#ifndef JX_BUS_CLOCK_H
#define JX_BUS_CLOCK_H

#include <stdint.h>

#define JX_BUS_CLOCK_VERSION 1u
#define JX_BUS_CLOCK_BASE_MS 250u
#define JX_BUS_CLOCK_BUSY_MS 125u
#define JX_BUS_CLOCK_BURST_MS 62u
#define JX_BUS_CLOCK_MAX_LOGICAL_BUSES 64u
#define JX_BUS_CLOCK_ACTIVITY_WINDOW 4u
#define JX_BUS_CLOCK_QUIET_DECAY 3u

typedef enum {
    JX_BUS_CLOCK_QUIET = 0,
    JX_BUS_CLOCK_NORMAL = 1,
    JX_BUS_CLOCK_BUSY = 2,
    JX_BUS_CLOCK_BURST = 3
} jx_bus_clock_gear;

typedef struct {
    uint8_t version;
    uint8_t gear;
    uint8_t active_streak;
    uint8_t quiet_streak;
    uint64_t logical_epoch;
    uint64_t active_bus_mask;
    uint32_t interval_ms;
    uint32_t last_data_count;
} jx_bus_clock;

void jx_bus_clock_init(jx_bus_clock *clock);

/* Observe the multiplexed logical-bus state after a completed collection
 * opportunity. active_bus_mask says which logical buses produced work and
 * data_count is the total number of ready codes seen across those buses.
 * The returned value is the next logical scheduling interval in ms.
 *
 * This is a JX logical clock only. It never changes physical CPU frequency.
 */
uint32_t jx_bus_clock_observe(jx_bus_clock *clock,
                              uint64_t active_bus_mask,
                              uint32_t data_count);

uint32_t jx_bus_clock_interval(const jx_bus_clock *clock);
jx_bus_clock_gear jx_bus_clock_gear_get(const jx_bus_clock *clock);

#endif
