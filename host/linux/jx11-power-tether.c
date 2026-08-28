#include "jx11-power-tether.h"
#include <string.h>

int jx11_power_tether_cutover(jx_hot_swap_gate *gate,
                              jx_channel_bus *bus,
                              uint32_t old_program_endpoint,
                              uint32_t new_program_endpoint,
                              const jx11_exe_tether_install *install,
                              jx11_power_tether_status *status) {
    jx11_power_tether_status local;
    if (!status) status = &local;
    memset(status, 0, sizeof *status);

    status->cutover_result = jx_hot_swap_button_cutover(
        gate, bus, old_program_endpoint, new_program_endpoint);
    if (status->cutover_result != JX_HOT_SWAP_OK)
        return JX11_POWER_TETHER_ERR_CUTOVER;

    if (!jx_hot_swap_takeover_proven(gate))
        return JX11_POWER_TETHER_ERR_UNPROVEN;

    status->live_takeover = 1u;
    status->persist_result = jx11_exe_tether_persist(install);
    if (status->persist_result != JX11_EXE_TETHER_OK)
        return JX11_POWER_TETHER_ERR_PERSIST;

    status->disk_persisted = 1u;
    return JX11_POWER_TETHER_OK;
}
