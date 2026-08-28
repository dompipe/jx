#include "jx-idle-codebus.h"
#include <string.h>

typedef struct {
    jx_idle_codebus *bus;
    jx_idle_domain_id domain;
    jx_idle_codebus_collect_fn collect;
    void *context;
} collect_context;

static jx_idle_bitmap *domain_bitmap(jx_idle_codebus *bus, jx_idle_domain_id domain) {
    if (!bus || domain >= JX_IDLE_DOMAIN_COUNT) return 0;
    return &bus->replies.domain[domain];
}

static const jx_idle_bitmap *domain_bitmap_const(const jx_idle_codebus *bus,
                                                  jx_idle_domain_id domain) {
    if (!bus || domain >= JX_IDLE_DOMAIN_COUNT) return 0;
    return &bus->replies.domain[domain];
}

static void clear_payload_domain(jx_idle_codebus *bus, jx_idle_domain_id domain) {
    for (size_t i = 0; i < JX_IDLE_BITMAP_MAX_PROGRAMS; ++i) {
        atomic_store_explicit(&bus->payload[domain].code[i], 0u, memory_order_relaxed);
        atomic_store_explicit(&bus->payload[domain].length[i], 0u, memory_order_relaxed);
    }
}

void jx_idle_codebus_init(jx_idle_codebus *bus) {
    if (!bus) return;
    memset(bus, 0, sizeof *bus);
    bus->version = JX_IDLE_CODEBUS_VERSION;
    jx_idle_domains_init(&bus->replies);
    for (size_t d = 0; d < JX_IDLE_DOMAIN_COUNT; ++d) {
        for (size_t i = 0; i < JX_IDLE_BITMAP_MAX_PROGRAMS; ++i) {
            atomic_init(&bus->payload[d].code[i], 0u);
            atomic_init(&bus->payload[d].length[i], 0u);
        }
    }
}

int jx_idle_codebus_begin(jx_idle_codebus *bus,
                          uint64_t epoch,
                          uint32_t core_count,
                          uint32_t window_count) {
    if (!bus || bus->version != JX_IDLE_CODEBUS_VERSION) return -1;
    int rc = jx_idle_bitmap_begin(&bus->replies.domain[JX_IDLE_DOMAIN_CORE], epoch, core_count);
    if (rc < 0) return rc;
    rc = jx_idle_bitmap_begin(&bus->replies.domain[JX_IDLE_DOMAIN_WINDOW], epoch, window_count);
    if (rc < 0) return rc;
    rc = jx_idle_bitmap_begin(&bus->replies.domain[JX_IDLE_DOMAIN_SECURITY], epoch, 0u);
    if (rc < 0) return rc;

    for (size_t d = 0; d < JX_IDLE_DOMAIN_COUNT; ++d)
        clear_payload_domain(bus, (jx_idle_domain_id)d);
    return 0;
}

int jx_idle_codebus_begin_security(jx_idle_codebus *bus,
                                   uint64_t epoch,
                                   uint32_t security_count) {
    if (!bus || bus->version != JX_IDLE_CODEBUS_VERSION) return -1;
    int rc = jx_idle_bitmap_begin(&bus->replies.domain[JX_IDLE_DOMAIN_SECURITY],
                                  epoch, security_count);
    if (rc < 0) return rc;
    clear_payload_domain(bus, JX_IDLE_DOMAIN_SECURITY);
    return 0;
}

int jx_idle_codebus_reply(jx_idle_codebus *bus,
                          jx_idle_domain_id domain,
                          uint64_t epoch,
                          uint32_t program_ordinal,
                          uint8_t code_length,
                          uint16_t code) {
    if (!bus || bus->version != JX_IDLE_CODEBUS_VERSION || domain >= JX_IDLE_DOMAIN_COUNT)
        return -1;
    if (code_length > JX_IDLE_CODEBUS_CODE_2BYTE) return -2;
    if (code_length == JX_IDLE_CODEBUS_CODE_NONE && code != 0u) return -3;
    if (code_length == JX_IDLE_CODEBUS_CODE_1BYTE && code > 0xffu) return -4;

    jx_idle_bitmap *bitmap = domain_bitmap(bus, domain);
    int rc = jx_idle_bitmap_claim(bitmap, epoch, program_ordinal);
    if (rc < 0) return rc;

    if (code_length != JX_IDLE_CODEBUS_CODE_NONE) {
        atomic_store_explicit(&bus->payload[domain].code[program_ordinal], code,
                              memory_order_relaxed);
        atomic_store_explicit(&bus->payload[domain].length[program_ordinal], code_length,
                              memory_order_release);
    }

    return jx_idle_bitmap_commit_claimed(bitmap, epoch, program_ordinal,
                                         code_length == JX_IDLE_CODEBUS_CODE_NONE ? 0u : 1u);
}

int jx_idle_codebus_complete(const jx_idle_codebus *bus,
                             jx_idle_domain_id domain) {
    return jx_idle_bitmap_complete(domain_bitmap_const(bus, domain));
}

static int collect_one(uint32_t program_ordinal,
                       uint32_t one_ordinal,
                       uint64_t epoch,
                       void *opaque) {
    collect_context *ctx = (collect_context *)opaque;
    uint8_t length = atomic_load_explicit(
        &ctx->bus->payload[ctx->domain].length[program_ordinal], memory_order_acquire);
    uint16_t code = atomic_load_explicit(
        &ctx->bus->payload[ctx->domain].code[program_ordinal], memory_order_acquire);
    if (length != JX_IDLE_CODEBUS_CODE_1BYTE && length != JX_IDLE_CODEBUS_CODE_2BYTE)
        return -6;
    return ctx->collect(ctx->domain, program_ordinal, one_ordinal, epoch,
                        length, code, ctx->context);
}

int jx_idle_codebus_collect(jx_idle_codebus *bus,
                            jx_idle_domain_id domain,
                            jx_idle_codebus_collect_fn collect,
                            void *context) {
    if (!bus || bus->version != JX_IDLE_CODEBUS_VERSION ||
        domain >= JX_IDLE_DOMAIN_COUNT || !collect) return -1;
    collect_context ctx = {bus, domain, collect, context};
    return jx_idle_bitmap_collect(domain_bitmap(bus, domain), collect_one, &ctx);
}
