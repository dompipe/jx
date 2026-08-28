#ifndef JX_IDLE_CODEBUS_H
#define JX_IDLE_CODEBUS_H

#include "jx-idle-domains.h"
#include <stdint.h>
#include <stdatomic.h>

#define JX_IDLE_CODEBUS_VERSION 2u
#define JX_IDLE_CODEBUS_CODE_NONE 0u
#define JX_IDLE_CODEBUS_CODE_1BYTE 1u
#define JX_IDLE_CODEBUS_CODE_2BYTE 2u

typedef struct {
    _Atomic uint16_t code[JX_IDLE_BITMAP_MAX_PROGRAMS];
    _Atomic uint8_t length[JX_IDLE_BITMAP_MAX_PROGRAMS];
} jx_idle_code_domain;

typedef struct {
    uint8_t version;
    uint8_t reserved[7];
    jx_idle_domains replies;
    jx_idle_code_domain payload[JX_IDLE_DOMAIN_COUNT];
} jx_idle_codebus;

typedef int (*jx_idle_codebus_collect_fn)(jx_idle_domain_id domain,
                                          uint32_t program_ordinal,
                                          uint32_t one_ordinal,
                                          uint64_t epoch,
                                          uint8_t code_length,
                                          uint16_t code,
                                          void *context);

void jx_idle_codebus_init(jx_idle_codebus *bus);
int jx_idle_codebus_begin(jx_idle_codebus *bus,
                          uint64_t epoch,
                          uint32_t core_count,
                          uint32_t window_count);

/* Arm SECURITY only when an inspection batch exists. Existing callers can
 * keep using jx_idle_codebus_begin(); SECURITY begins with an empty barrier. */
int jx_idle_codebus_begin_security(jx_idle_codebus *bus,
                                   uint64_t epoch,
                                   uint32_t security_count);

/* Every program must reply. code_length==0 is the canonical zero reply.
 * A data reply carries exactly one one- or two-byte prepared code. */
int jx_idle_codebus_reply(jx_idle_codebus *bus,
                          jx_idle_domain_id domain,
                          uint64_t epoch,
                          uint32_t program_ordinal,
                          uint8_t code_length,
                          uint16_t code);

int jx_idle_codebus_complete(const jx_idle_codebus *bus,
                             jx_idle_domain_id domain);
int jx_idle_codebus_collect(jx_idle_codebus *bus,
                            jx_idle_domain_id domain,
                            jx_idle_codebus_collect_fn collect,
                            void *context);

#endif
