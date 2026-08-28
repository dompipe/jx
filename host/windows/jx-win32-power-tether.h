#ifndef JX_WIN32_POWER_TETHER_H
#define JX_WIN32_POWER_TETHER_H
#include <stdint.h>
#include "../common/jx-hot-swap.h"
#include "jx-win32-exe-tether.h"

typedef enum {
    JX_WIN32_POWER_TETHER_OK = 0,
    JX_WIN32_POWER_TETHER_ERR_CUTOVER = -1,
    JX_WIN32_POWER_TETHER_ERR_UNPROVEN = -2,
    JX_WIN32_POWER_TETHER_ERR_ARM = -3
} jx_win32_power_tether_result;

typedef struct {
    int cutover_result;
    int arm_result;
    uint8_t live_takeover;
    uint8_t persistence_armed;
} jx_win32_power_tether_status;

int jx_win32_power_tether_cutover(jx_hot_swap_gate *gate,
                                  jx_channel_bus *bus,
                                  uint32_t old_program_endpoint,
                                  uint32_t new_program_endpoint,
                                  const jx_win32_exe_tether *tether,
                                  DWORD owner_pid,
                                  jx_win32_power_tether_status *status);
#endif
