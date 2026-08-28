#include "jx-win32-power-tether.h"
#include <string.h>
int jx_win32_power_tether_cutover(jx_hot_swap_gate *gate,
                                  jx_channel_bus *bus,
                                  uint32_t old_program_endpoint,
                                  uint32_t new_program_endpoint,
                                  const jx_win32_exe_tether *tether,
                                  DWORD owner_pid,
                                  jx_win32_power_tether_status *status){
    jx_win32_power_tether_status local; if(!status)status=&local; memset(status,0,sizeof *status);
    status->cutover_result=jx_hot_swap_button_cutover(gate,bus,old_program_endpoint,new_program_endpoint);
    if(status->cutover_result!=JX_HOT_SWAP_OK)return JX_WIN32_POWER_TETHER_ERR_CUTOVER;
    if(!jx_hot_swap_takeover_proven(gate))return JX_WIN32_POWER_TETHER_ERR_UNPROVEN;
    status->live_takeover=1u;
    status->arm_result=jx_win32_exe_tether_arm(tether,owner_pid);
    if(status->arm_result!=0)return JX_WIN32_POWER_TETHER_ERR_ARM;
    status->persistence_armed=1u; return JX_WIN32_POWER_TETHER_OK;
}
