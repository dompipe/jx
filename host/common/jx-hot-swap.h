#ifndef JX_HOT_SWAP_H
#define JX_HOT_SWAP_H

#include <stddef.h>
#include <stdint.h>

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
} jx_hot_swap_gate;

typedef enum {
    JX_HOT_SWAP_OK = 0,
    JX_HOT_SWAP_ERR_ARGUMENT = -1,
    JX_HOT_SWAP_ERR_VERSION = -2,
    JX_HOT_SWAP_ERR_DATA_SOURCE = -3,
    JX_HOT_SWAP_ERR_STATE_ABI = -4,
    JX_HOT_SWAP_ERR_GENERATION = -5,
    JX_HOT_SWAP_ERR_START = -6,
    JX_HOT_SWAP_ERR_NOT_READY = -7
} jx_hot_swap_result;

void jx_hot_swap_init(jx_hot_swap_gate *gate,
                      const jx_hot_swap_program *active,
                      void *shared_state);

/**
 * Prepare a newly compiled shadow alongside the active one.
 * The candidate must consume the exact same data-source and state ABI contracts.
 * start() runs against the same shared state before the active program is changed.
 */
int jx_hot_swap_prepare(jx_hot_swap_gate *gate,
                        const jx_hot_swap_program *candidate);

/**
 * Safe-point operation: switch dispatch to the prepared candidate, then stop the old program.
 * Shared state is never copied or replaced.
 */
int jx_hot_swap_commit(jx_hot_swap_gate *gate);

/** Route an event through whichever compiled shadow is active now. */
int jx_hot_swap_call(jx_hot_swap_gate *gate, uint32_t event, void *payload);

#endif
