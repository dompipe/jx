#include "../host/common/jx-hot-swap.h"
#include <assert.h>
#include <stdint.h>
#include <stdio.h>
#include <string.h>

#define OLD_EP 0x80000001u
#define NEW_EP 0x80000002u
#define SOURCE_EP 1u
#define CLICK_CH 7u

typedef struct {
    int value;
    int old_started;
    int old_stopped;
    int new_started;
    int new_stopped;
    int old_channel_hits;
    int new_channel_hits;
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

static int old_receive(uint16_t channel, uint32_t type, void *payload, void *context) {
    (void)channel; (void)type; (void)payload;
    ((shared_state *)context)->old_channel_hits++;
    return 0;
}
static int new_receive(uint16_t channel, uint32_t type, void *payload, void *context) {
    (void)channel; (void)type; (void)payload;
    ((shared_state *)context)->new_channel_hits++;
    return 0;
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

    jx_channel_bus bus;
    jx_channel_bus_init(&bus, OLD_EP);
    assert(jx_channel_bus_add_endpoint(&bus, SOURCE_EP, NULL, NULL) == 0);
    assert(jx_channel_bus_add_endpoint(&bus, OLD_EP, old_receive, &state) == 0);
    assert(jx_channel_bus_add_endpoint(&bus, NEW_EP, new_receive, &state) == 0);
    assert(jx_channel_bus_bind(&bus, SOURCE_EP, CLICK_CH, JX_CHANNEL_DIR_OUT) == 0);
    assert(jx_channel_bus_bind(&bus, OLD_EP, CLICK_CH, JX_CHANNEL_DIR_IN) == 0);
    assert(jx_channel_bus_bind(&bus, NEW_EP, CLICK_CH, JX_CHANNEL_DIR_IN) == 0);

    assert(jx_channel_bus_publish(&bus, SOURCE_EP, CLICK_CH, 1u, NULL) == 1);
    assert(state.old_channel_hits == 1 && state.new_channel_hits == 0);

    jx_hot_swap_program next = oldp;
    next.generation = 2u;
    next.start = new_start; next.stop = new_stop; next.call = new_call;
    assert(jx_hot_swap_prepare(&gate, &next) == JX_HOT_SWAP_OK);

    /* Merely compiling/loading the new program must not start or route to it. */
    assert(state.new_started == 0);
    assert(gate.active.generation == 1u);
    assert(bus.active_program_endpoint == OLD_EP);
    assert(jx_channel_bus_publish(&bus, SOURCE_EP, CLICK_CH, 2u, NULL) == 1);
    assert(state.old_channel_hits == 2 && state.new_channel_hits == 0);

    /* Shared program state also continues under the old compiled shadow. */
    assert(jx_hot_swap_call(&gate, 2u, NULL) == 1);
    assert(state.value == 1);

    /* Explicit button press performs the cutover, and only now. */
    assert(jx_hot_swap_button_cutover(&gate, &bus, OLD_EP, NEW_EP) == JX_HOT_SWAP_OK);
    assert(state.new_started == 1);
    assert(state.old_stopped == 1);
    assert(gate.active.generation == 2u);
    assert(bus.active_program_endpoint == NEW_EP);
    assert(state.value == 1); /* interim state was preserved, not copied/reset */

    assert(jx_channel_bus_publish(&bus, SOURCE_EP, CLICK_CH, 3u, NULL) == 1);
    assert(state.old_channel_hits == 2 && state.new_channel_hits == 1);
    assert(jx_hot_swap_call(&gate, 3u, NULL) == 11);

    /* The cable bus queues data while paused and releases it to the new root. */
    jx_channel_bus_pause(&bus);
    assert(jx_channel_bus_publish(&bus, SOURCE_EP, CLICK_CH, 4u, NULL) == 0);
    assert(state.new_channel_hits == 1);
    assert(jx_channel_bus_resume(&bus) == 1);
    assert(state.new_channel_hits == 2);

    /* Contract changes cannot use this zero-migration button path. */
    jx_hot_swap_program bad = next;
    bad.generation = 3u;
    bad.data_source_digest[0] ^= 0xffu;
    assert(jx_hot_swap_prepare(&gate, &bad) == JX_HOT_SWAP_ERR_DATA_SOURCE);
    bad = next;
    bad.generation = 3u;
    bad.state_abi_digest[0] ^= 0xffu;
    assert(jx_hot_swap_prepare(&gate, &bad) == JX_HOT_SWAP_ERR_STATE_ABI);

    puts("jx-hot-swap: prepared stays dormant; button pauses channel root, switches program, preserves state, resumes data");
    return 0;
}
