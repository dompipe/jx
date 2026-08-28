#ifndef JX_HOT_SWAP_H
#define JX_HOT_SWAP_H

#include <stddef.h>
#include <stdint.h>
#include "jx-channel-bus.h"

#define JX_HOT_SWAP_VERSION 1u
#define JX_HOT_SWAP_DIGEST_BYTES 32u

typedef int (*jx_hot_swap_start_fn)(void *shared_state, void *context);
typedef void (*jx_hot_swap_stop_fn)(void *shared_state, void *context);
typedef int (*jx_hot_swap_call_fn)(void *shared_state, void *context, uint32_t event, void *payload);

typedef struct {
    uint8_t version;
    uint8_t data_source_digest[JX_HOT_SWAP_DIGEST_BYTES];
    uint8_t state_abi_digest[JX_HOT_SWAP_DIGEST_BYTES];
    uint64_t generation;
    jx_hot_swap_start_fn start;
    jx_hot_swap_stop_fn stop;
    jx_hot_swap_call_fn call;
    void *context;
} jx_hot_swap_program;

typedef struct {
    jx_hot_swap_program active;
    jx_hot_swap_program candidate;
    void *shared_state;
    uint8_t candidate_ready;
    uint8_t cutover_requested;
} jx_hot_swap_gate;

typedef enum {
    JX_HOT_SWAP_OK = 0,
    JX_HOT_SWAP_ERR_ARGUMENT = -1,
    JX_HOT_SWAP_ERR_VERSION = -2,
    JX_HOT_SWAP_ERR_DATA_SOURCE = -3,
    JX_HOT_SWAP_ERR_STATE_ABI = -4,
    JX_HOT_SWAP_ERR_GENERATION = -5,
    JX_HOT_SWAP_ERR_START = -6,
    JX_HOT_SWAP_ERR_NOT_READY = -7,
    JX_HOT_SWAP_ERR_CHANNEL = -8
} jx_hot_swap_result;

void jx_hot_swap_init(jx_hot_swap_gate *gate,
                      const jx_hot_swap_program *active,
                      void *shared_state);

/**
 * Compile/load-time preparation only. It must not start the candidate or alter routing.
 */
int jx_hot_swap_prepare(jx_hot_swap_gate *gate,
                        const jx_hot_swap_program *candidate);

/**
 * Explicit UI/user cutover. The channel root pauses, the candidate starts on the same
 * shared state, routing flips, the old program stops, then queued channel traffic resumes.
 */
int jx_hot_swap_button_cutover(jx_hot_swap_gate *gate,
                               jx_channel_bus *bus,
                               uint32_t old_program_endpoint,
                               uint32_t new_program_endpoint);

/** Route an event through whichever compiled shadow is active now. */
int jx_hot_swap_call(jx_hot_swap_gate *gate, uint32_t event, void *payload);

#endif
