#include <assert.h>
#include <stdint.h>
#include <stdio.h>
#include <string.h>
#include "../host/common/jx-security.h"
#include "../host/common/jx-idle-codebus.h"

static void sig_init(jx_security_signature *sig,
                     uint32_t id,
                     const char *name,
                     jx_security_verdict verdict,
                     uint64_t offset,
                     const uint8_t *bytes,
                     const uint8_t *mask,
                     uint8_t length) {
    memset(sig, 0, sizeof *sig);
    sig->id = id;
    sig->type = mask ? JX_SECURITY_SIG_BYTES_MASKED : JX_SECURITY_SIG_BYTES;
    sig->verdict = (uint8_t)verdict;
    sig->offset = offset;
    sig->length = length;
    memcpy(sig->bytes, bytes, length);
    if (mask) memcpy(sig->mask, mask, length);
    strncpy(sig->name, name, JX_SECURITY_NAME_MAX);
}

typedef struct {
    unsigned calls;
    uint16_t codes[4];
    uint8_t lengths[4];
    uint32_t ordinals[4];
} collect_ctx;

static int collect_code(jx_idle_domain_id domain,
                        uint32_t program_ordinal,
                        uint32_t one_ordinal,
                        uint64_t epoch,
                        uint8_t code_length,
                        uint16_t code,
                        void *opaque) {
    collect_ctx *ctx = (collect_ctx *)opaque;
    assert(domain == JX_IDLE_DOMAIN_SECURITY);
    assert(epoch == 9u);
    assert(one_ordinal == ctx->calls);
    ctx->codes[ctx->calls] = code;
    ctx->lengths[ctx->calls] = code_length;
    ctx->ordinals[ctx->calls] = program_ordinal;
    ctx->calls++;
    return 0;
}

int main(void) {
    static const uint8_t object[] = {
        0x4a, 0x58, 0x00, 0x10, 0xde, 0xad, 0xbe, 0xef,
        0x41, 0x42, 0x77, 0x44, 0x00
    };
    const uint8_t anchored[] = {0x4a, 0x58};
    const uint8_t suspicious[] = {0x41, 0x42, 0x00, 0x44};
    const uint8_t suspicious_mask[] = {0xff, 0xff, 0x00, 0xff};
    const uint8_t malware[] = {0xde, 0xad, 0xbe, 0xef};
    jx_security_signature signatures[3];
    jx_security_result result;
    uint8_t code_length;
    uint16_t code;
    jx_security_verdict verdict;
    uint16_t slot;
    jx_idle_codebus bus;
    collect_ctx collected;

    sig_init(&signatures[0], 10u, "JX.Header", JX_SECURITY_SUSPICIOUS,
             0u, anchored, NULL, sizeof anchored);
    sig_init(&signatures[1], 11u, "Masked.Marker", JX_SECURITY_SUSPICIOUS,
             JX_SECURITY_OFFSET_ANY, suspicious, suspicious_mask, sizeof suspicious);
    sig_init(&signatures[2], 12u, "Test.Malware.Pattern", JX_SECURITY_MALWARE,
             JX_SECURITY_OFFSET_ANY, malware, NULL, sizeof malware);

    assert(jx_security_scan_buffer(object, sizeof object, signatures, 3u, &result) == 1);
    assert(result.verdict == JX_SECURITY_MALWARE);
    assert(result.signature_id == 12u);
    assert(result.match_offset == 4u);
    assert(result.bytes_scanned == sizeof object);
    assert(strcmp(result.signature_name, "Test.Malware.Pattern") == 0);

    assert(jx_security_scan_buffer((const uint8_t *)"clean", 5u,
                                   signatures, 3u, &result) == 0);
    assert(result.verdict == JX_SECURITY_CLEAN);

    assert(jx_security_code_encode(JX_SECURITY_MALWARE, 37u, &code_length, &code) == 0);
    assert(code_length == 1u);
    assert(jx_security_code_decode(code_length, code, &verdict, &slot) == 0);
    assert(verdict == JX_SECURITY_MALWARE && slot == 37u);

    assert(jx_security_code_encode(JX_SECURITY_SUSPICIOUS, 4096u, &code_length, &code) == 0);
    assert(code_length == 2u);
    assert(jx_security_code_decode(code_length, code, &verdict, &slot) == 0);
    assert(verdict == JX_SECURITY_SUSPICIOUS && slot == 4096u);

    jx_idle_codebus_init(&bus);
    assert(jx_idle_codebus_begin(&bus, 9u, 2u, 2u) == 0);
    assert(jx_idle_codebus_begin_security(&bus, 9u, 3u) == 0);

    /* Fixed program positions are the order authority, not arrival order. */
    assert(jx_security_code_encode(JX_SECURITY_MALWARE, 7u, &code_length, &code) == 0);
    assert(jx_idle_codebus_reply(&bus, JX_IDLE_DOMAIN_SECURITY, 9u, 2u,
                                 code_length, code) >= 0);
    assert(jx_idle_codebus_reply(&bus, JX_IDLE_DOMAIN_SECURITY, 9u, 0u,
                                 JX_IDLE_CODEBUS_CODE_NONE, 0u) >= 0);
    assert(jx_security_code_encode(JX_SECURITY_SUSPICIOUS, 70u, &code_length, &code) == 0);
    assert(jx_idle_codebus_reply(&bus, JX_IDLE_DOMAIN_SECURITY, 9u, 1u,
                                 code_length, code) >= 0);

    assert(jx_idle_codebus_complete(&bus, JX_IDLE_DOMAIN_SECURITY) == 1);
    memset(&collected, 0, sizeof collected);
    assert(jx_idle_codebus_collect(&bus, JX_IDLE_DOMAIN_SECURITY,
                                   collect_code, &collected) == 2);
    assert(collected.calls == 2u);
    assert(collected.ordinals[0] == 1u);
    assert(collected.ordinals[1] == 2u);

    assert(jx_security_code_decode(collected.lengths[0], collected.codes[0],
                                   &verdict, &slot) == 0);
    assert(verdict == JX_SECURITY_SUSPICIOUS && slot == 70u);
    assert(jx_security_code_decode(collected.lengths[1], collected.codes[1],
                                   &verdict, &slot) == 0);
    assert(verdict == JX_SECURITY_MALWARE && slot == 7u);

    puts("PASS JX native security scanner, compact result codes and SECURITY bus ordering");
    return 0;
}
