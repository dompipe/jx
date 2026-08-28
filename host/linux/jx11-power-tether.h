#ifndef JX11_POWER_TETHER_H
#define JX11_POWER_TETHER_H

#include <stdint.h>
#include "../common/jx-hot-swap.h"
#include "jx11-exe-tether.h"

typedef enum {
    JX11_POWER_TETHER_OK = 0,
    JX11_POWER_TETHER_ERR_CUTOVER = -1,
    JX11_POWER_TETHER_ERR_UNPROVEN = -2,
    JX11_POWER_TETHER_ERR_PERSIST = -3
} jx11_power_tether_result;

typedef struct {
    int cutover_result;
    int persist_result;
    uint8_t live_takeover;
    uint8_t disk_persisted;
} jx11_power_tether_status;

/*
 * The explicit power-button path.
 * 1. pause/switch channel root
 * 2. prove candidate is actually powered
 * 3. retire old live program
 * 4. atomically replace installed jx11 image
 */
int jx11_power_tether_cutover(jx_hot_swap_gate *gate,
                              jx_channel_bus *bus,
                              uint32_t old_program_endpoint,
                              uint32_t new_program_endpoint,
                              const jx11_exe_tether_install *install,
                              jx11_power_tether_status *status);

#endif
