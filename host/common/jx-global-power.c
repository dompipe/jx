#include "jx-global-power.h"
#include <string.h>

void jx_global_power_init(jx_global_power *power, uint64_t active_generation) {
    if (!power) return;
    memset(power, 0, sizeof *power);
    power->version = JX_GLOBAL_POWER_VERSION;
    power->anchor = JX_GLOBAL_POWER_ANCHOR_LOWER_RIGHT;
    power->active_generation = active_generation;
}

void jx_global_power_candidate_ready(jx_global_power *power, uint64_t candidate_generation) {
    if (!power) return;
    power->state = JX_GLOBAL_POWER_READY;
    power->visible = 1u;
    power->clickable = 1u;
    power->candidate_generation = candidate_generation;
}

void jx_global_power_cutover_begin(jx_global_power *power) {
    if (!power) return;
    power->state = JX_GLOBAL_POWER_CUTOVER;
    power->visible = 1u;
    power->clickable = 0u;
}

void jx_global_power_cutover_complete(jx_global_power *power, uint64_t active_generation) {
    if (!power) return;
    power->state = JX_GLOBAL_POWER_IDLE;
    power->visible = 0u;
    power->clickable = 0u;
    power->active_generation = active_generation;
    power->candidate_generation = 0u;
}

void jx_global_power_error(jx_global_power *power) {
    if (!power) return;
    power->state = JX_GLOBAL_POWER_ERROR;
    power->visible = 1u;
    power->clickable = 0u;
}

int jx_global_power_can_press(const jx_global_power *power) {
    return power && power->version == JX_GLOBAL_POWER_VERSION &&
           power->state == JX_GLOBAL_POWER_READY && power->visible && power->clickable;
}
