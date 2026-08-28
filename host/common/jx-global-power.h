#ifndef JX_GLOBAL_POWER_H
#define JX_GLOBAL_POWER_H

#include <stdint.h>

#define JX_GLOBAL_POWER_VERSION 1u

typedef enum {
    JX_GLOBAL_POWER_IDLE = 0,
    JX_GLOBAL_POWER_READY = 1,
    JX_GLOBAL_POWER_CUTOVER = 2,
    JX_GLOBAL_POWER_ERROR = 3
} jx_global_power_state;

typedef struct {
    uint8_t version;
    uint8_t state;
    uint8_t visible;
    uint8_t clickable;
    uint32_t anchor; /* host constant: lower-right */
    uint64_t active_generation;
    uint64_t candidate_generation;
} jx_global_power;

#define JX_GLOBAL_POWER_ANCHOR_LOWER_RIGHT 4u

void jx_global_power_init(jx_global_power *power, uint64_t active_generation);
void jx_global_power_candidate_ready(jx_global_power *power, uint64_t candidate_generation);
void jx_global_power_cutover_begin(jx_global_power *power);
void jx_global_power_cutover_complete(jx_global_power *power, uint64_t active_generation);
void jx_global_power_error(jx_global_power *power);
int jx_global_power_can_press(const jx_global_power *power);

#endif
