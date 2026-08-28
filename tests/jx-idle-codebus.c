#include "../host/common/jx-idle-codebus.h"
#include <assert.h>
#include <stdio.h>

typedef struct {
    uint32_t count;
    uint32_t program[8];
    uint32_t one[8];
    uint8_t length[8];
    uint16_t code[8];
} collected_codes;

static int collect_code(jx_idle_domain_id domain,
                        uint32_t program_ordinal,
                        uint32_t one_ordinal,
                        uint64_t epoch,
                        uint8_t code_length,
                        uint16_t code,
                        void *context) {
    collected_codes *out = (collected_codes *)context;
    assert(domain == JX_IDLE_DOMAIN_WINDOW);
    assert(epoch == 7u);
    assert(out->count < 8u);
    uint32_t n = out->count++;
    out->program[n] = program_ordinal;
    out->one[n] = one_ordinal;
    out->length[n] = code_length;
    out->code[n] = code;
    return 0;
}

int main(void) {
    jx_idle_codebus bus;
    jx_idle_codebus_init(&bus);
    assert(jx_idle_codebus_begin(&bus, 7u, 2u, 8u) == 0);

    /* Core and window barriers are independent. */
    assert(jx_idle_codebus_reply(&bus, JX_IDLE_DOMAIN_CORE, 7u, 0u, 0u, 0u) == 0);
    assert(jx_idle_codebus_reply(&bus, JX_IDLE_DOMAIN_CORE, 7u, 1u, 0u, 0u) == 0);
    assert(jx_idle_codebus_complete(&bus, JX_IDLE_DOMAIN_CORE));
    assert(!jx_idle_codebus_complete(&bus, JX_IDLE_DOMAIN_WINDOW));

    /* Fixed program order encodes 01011001. Reply arrival is deliberately
     * scrambled; bus #2 must still emit program positions 1,3,4,7. */
    assert(jx_idle_codebus_reply(&bus, JX_IDLE_DOMAIN_WINDOW, 7u, 7u, 2u, 0x1234u) == 2);
    assert(jx_idle_codebus_reply(&bus, JX_IDLE_DOMAIN_WINDOW, 7u, 0u, 0u, 0u) == 0);
    assert(jx_idle_codebus_reply(&bus, JX_IDLE_DOMAIN_WINDOW, 7u, 4u, 1u, 0x44u) == 1);
    assert(jx_idle_codebus_reply(&bus, JX_IDLE_DOMAIN_WINDOW, 7u, 2u, 0u, 0u) == 0);
    assert(jx_idle_codebus_reply(&bus, JX_IDLE_DOMAIN_WINDOW, 7u, 1u, 1u, 0x11u) == 1);
    assert(jx_idle_codebus_reply(&bus, JX_IDLE_DOMAIN_WINDOW, 7u, 6u, 0u, 0u) == 0);
    assert(jx_idle_codebus_reply(&bus, JX_IDLE_DOMAIN_WINDOW, 7u, 3u, 2u, 0xabcd) == 1);
    assert(jx_idle_codebus_reply(&bus, JX_IDLE_DOMAIN_WINDOW, 7u, 5u, 0u, 0u) == 0);
    assert(jx_idle_codebus_complete(&bus, JX_IDLE_DOMAIN_WINDOW));

    collected_codes out = {0};
    assert(jx_idle_codebus_collect(&bus, JX_IDLE_DOMAIN_WINDOW, collect_code, &out) == 4);
    assert(out.count == 4u);

    const uint32_t expected_program[] = {1u, 3u, 4u, 7u};
    const uint8_t expected_length[] = {1u, 2u, 1u, 2u};
    const uint16_t expected_code[] = {0x11u, 0xabcdu, 0x44u, 0x1234u};
    for (uint32_t i = 0; i < 4u; ++i) {
        assert(out.program[i] == expected_program[i]);
        assert(out.one[i] == i);
        assert(out.length[i] == expected_length[i]);
        assert(out.code[i] == expected_code[i]);
    }

    /* Bus #2 is single-consume for the completed epoch. */
    assert(jx_idle_codebus_collect(&bus, JX_IDLE_DOMAIN_WINDOW, collect_code, &out) == 0);

    puts("jx-idle-codebus: ordered 1-2 byte ownership codes collected from bitstrings ok");
    return 0;
}
