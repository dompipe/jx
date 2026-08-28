#include "../host/common/jx-hot-swap.h"
#include <assert.h>
#include <stdint.h>
#include <stdio.h>
#include <string.h>

typedef struct {
    int value;
    int old_started;
    int old_stopped;
    int new_started;
    int new_stopped;
} shared_state;

static int old_start(void *state, void *context) {
    (void)context;
    ((shared_state *)state)->old_started++;
    return 0;
}
static void old_stop(void *state, void *context) {
    (void)context;
    ((shared_state *)state)->old_stopped++;
}
static int old_call(void *state, void *context, uint32_t event, void *payload) {
    (void)context; (void)event; (void)payload;
    shared_state *s = (shared_state *)state;
    s->value += 1;
    return s->value;
}
static int new_start(void *state, void *context) {
    (void)context;
    ((shared_state *)state)->new_started++;
    return 0;
}
static void new_stop(void *state, void *context) {
    (void)context;
    ((shared_state *)state)->new_stopped++;
}
static int new_call(void *state, void *context, uint32_t event, void *payload) {
    (void)context; (void)event; (void)payload;
    shared_state *s = (shared_state *)state;
    s->value += 10;
    return s->value;
}

static void fill(uint8_t d[JX_HOT_SWAP_DIGEST_BYTES], uint8_t v) {
    memset(d, v, JX_HOT_SWAP_DIGEST_BYTES);
}

int main(void) {
    shared_state state = {0};
    jx_hot_swap_program oldp = {0};
    oldp.version = JX_HOT_SWAP_VERSION;
    oldp.generation = 1u;
    fill(oldp.data_source_digest, 0x11u);
    fill(oldp.state_abi_digest, 0x22u);
    oldp.start = old_start; oldp.stop = old_stop; oldp.call = old_call;

    assert(oldp.start(&state, oldp.context) == 0);
    jx_hot_swap_gate gate;
    jx_hot_swap_init(&gate, &oldp, &state);
    assert(jx_hot_swap_call(&gate, 1u, NULL) == 1);
    assert(state.value == 1);

    jx_hot_swap_program next = oldp;
    next.generation = 2u;
    next.start = new_start; next.stop = new_stop; next.call = new_call;

    assert(jx_hot_swap_prepare(&gate, &next) == JX_HOT_SWAP_OK);
    assert(state.new_started == 1);
    assert(state.old_stopped == 0);
    assert(gate.active.generation == 1u);

    /* Interim traffic still uses the old program and the same shared state. */
    assert(jx_hot_swap_call(&gate, 2u, NULL) == 2);
    assert(state.value == 2);

    /* One safe-point switch changes dispatch, then retires the old program. */
    assert(jx_hot_swap_commit(&gate) == JX_HOT_SWAP_OK);
    assert(gate.active.generation == 2u);
    assert(state.old_stopped == 1);
    assert(state.value == 2);
    assert(jx_hot_swap_call(&gate, 3u, NULL) == 12);
    assert(state.value == 12);

    /* Changed data sources must not use the zero-migration path. */
    jx_hot_swap_program bad = next;
    bad.generation = 3u;
    bad.data_source_digest[0] ^= 0xffu;
    assert(jx_hot_swap_prepare(&gate, &bad) == JX_HOT_SWAP_ERR_DATA_SOURCE);

    /* Changed state ABI must also fall back to migration/bytecode handling. */
    bad = next;
    bad.generation = 3u;
    bad.state_abi_digest[0] ^= 0xffu;
    assert(jx_hot_swap_prepare(&gate, &bad) == JX_HOT_SWAP_ERR_STATE_ABI);

    puts("jx-hot-swap: same sources/state => parallel start, safe switch, old stop, state preserved");
    return 0;
}
