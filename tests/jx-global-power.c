#include "../host/common/jx-global-power.h"
#include <assert.h>
#include <stdio.h>

int main(void) {
    jx_global_power power;
    jx_global_power_init(&power, 7u);
    assert(power.anchor == JX_GLOBAL_POWER_ANCHOR_LOWER_RIGHT);
    assert(power.state == JX_GLOBAL_POWER_IDLE);
    assert(power.visible == 0u);
    assert(jx_global_power_can_press(&power) == 0);

    jx_global_power_candidate_ready(&power, 8u);
    assert(power.visible == 1u);
    assert(power.clickable == 1u);
    assert(power.candidate_generation == 8u);
    assert(jx_global_power_can_press(&power) == 1);

    jx_global_power_cutover_begin(&power);
    assert(power.state == JX_GLOBAL_POWER_CUTOVER);
    assert(power.clickable == 0u);
    assert(jx_global_power_can_press(&power) == 0);

    jx_global_power_cutover_complete(&power, 8u);
    assert(power.state == JX_GLOBAL_POWER_IDLE);
    assert(power.visible == 0u);
    assert(power.active_generation == 8u);
    assert(power.candidate_generation == 0u);

    jx_global_power_candidate_ready(&power, 9u);
    jx_global_power_error(&power);
    assert(power.state == JX_GLOBAL_POWER_ERROR);
    assert(power.visible == 1u);
    assert(power.clickable == 0u);

    puts("jx-global-power: lower-right readiness/cutover/error contract ok");
    return 0;
}
