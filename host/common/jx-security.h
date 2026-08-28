#ifndef JX_SECURITY_H
#define JX_SECURITY_H

#include <stddef.h>
#include <stdint.h>

#define JX_SECURITY_VERSION 1u
#define JX_SECURITY_PATTERN_MAX 64u
#define JX_SECURITY_NAME_MAX 63u
#define JX_SECURITY_OFFSET_ANY UINT64_MAX
#define JX_SECURITY_RESULT_SLOT_DIRECT_MAX 63u
#define JX_SECURITY_RESULT_SLOT_EXTENDED_MAX 16383u

typedef enum {
    JX_SECURITY_CLEAN = 0,
    JX_SECURITY_SUSPICIOUS = 1,
    JX_SECURITY_MALWARE = 2,
    JX_SECURITY_ERROR = 3
} jx_security_verdict;

typedef enum {
    JX_SECURITY_SIG_BYTES = 1,
    JX_SECURITY_SIG_BYTES_MASKED = 2
} jx_security_signature_type;

typedef struct {
    uint32_t id;
    uint8_t type;
    uint8_t verdict;
    uint8_t length;
    uint8_t reserved;
    uint64_t offset; /* JX_SECURITY_OFFSET_ANY scans the whole object. */
    uint8_t bytes[JX_SECURITY_PATTERN_MAX];
    uint8_t mask[JX_SECURITY_PATTERN_MAX]; /* 0xff = compare; 0x00 = wildcard. */
    char name[JX_SECURITY_NAME_MAX + 1u];
} jx_security_signature;

typedef struct {
    uint8_t version;
    uint8_t verdict;
    uint16_t reserved16;
    uint32_t signature_id;
    uint64_t match_offset;
    uint64_t bytes_scanned;
    char signature_name[JX_SECURITY_NAME_MAX + 1u];
} jx_security_result;

/* Scan a caller-owned buffer in place. The scanner never takes ownership and
 * never copies the payload. First highest-severity match wins; ties keep the
 * earliest signature in canonical signature order. */
int jx_security_scan_buffer(const uint8_t *data,
                            size_t length,
                            const jx_security_signature *signatures,
                            size_t signature_count,
                            jx_security_result *result);

/* SECURITY bus result code. Direct slots use one byte: vvssssss.
 * Extended slots use two bytes: vvssssssssssssss. Verdict occupies the top
 * two bits and the remaining bits are the prepared result-slot reference. */
int jx_security_code_encode(jx_security_verdict verdict,
                            uint16_t result_slot,
                            uint8_t *code_length,
                            uint16_t *code);
int jx_security_code_decode(uint8_t code_length,
                            uint16_t code,
                            jx_security_verdict *verdict,
                            uint16_t *result_slot);

#endif
