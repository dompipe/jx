#include "jx-hot-swap.h"
#include <string.h>

static int digest_equal(const uint8_t a[JX_HOT_SWAP_DIGEST_BYTES],
                        const uint8_t b[JX_HOT_SWAP_DIGEST_BYTES]) {
    uint8_t diff = 0u;
    for (size_t i = 0; i < JX_HOT_SWAP_DIGEST_BYTES; ++i) diff |= (uint8_t)(a[i] ^ b[i]);
    return diff == 0u;
}

void jx_hot_swap_init(jx_hot_swap_gate *gate,
                      const jx_hot_swap_program *active,
                      void *shared_state) {
    if (!gate) return;
    memset(gate, 0, sizeof *gate);
    if (active) gate->active = *active;
    gate->shared_state = shared_state;
}

int jx_hot_swap_prepare(jx_hot_swap_gate *gate,
                        const jx_hot_swap_program *candidate) {
    if (!gate || !candidate) return JX_HOT_SWAP_ERR_ARGUMENT;
    if (gate->active.version != JX_HOT_SWAP_VERSION || candidate->version != JX_HOT_SWAP_VERSION)
        return JX_HOT_SWAP_ERR_VERSION;
    if (!digest_equal(gate->active.data_source_digest, candidate->data_source_digest))
        return JX_HOT_SWAP_ERR_DATA_SOURCE;
    if (!digest_equal(gate->active.state_abi_digest, candidate->state_abi_digest))
        return JX_HOT_SWAP_ERR_STATE_ABI;
    if (candidate->generation <= gate->active.generation)
        return JX_HOT_SWAP_ERR_GENERATION;
    gate->candidate = *candidate;
    if (gate->candidate.start && gate->candidate.start(gate->shared_state, gate->candidate.context) != 0) {
        memset(&gate->candidate, 0, sizeof gate->candidate);
        return JX_HOT_SWAP_ERR_START;
    }
    gate->candidate_ready = 1u;
    return JX_HOT_SWAP_OK;
}

int jx_hot_swap_commit(jx_hot_swap_gate *gate) {
    if (!gate) return JX_HOT_SWAP_ERR_ARGUMENT;
    if (!gate->candidate_ready) return JX_HOT_SWAP_ERR_NOT_READY;
    jx_hot_swap_program old = gate->active;
    gate->active = gate->candidate;
    memset(&gate->candidate, 0, sizeof gate->candidate);
    gate->candidate_ready = 0u;
    if (old.stop) old.stop(gate->shared_state, old.context);
    return JX_HOT_SWAP_OK;
}

int jx_hot_swap_call(jx_hot_swap_gate *gate, uint32_t event, void *payload) {
    if (!gate || !gate->active.call) return JX_HOT_SWAP_ERR_ARGUMENT;
    return gate->active.call(gate->shared_state, gate->active.context, event, payload);
}
