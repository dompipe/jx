#ifndef JX_SECURITY_IMPORT_H
#define JX_SECURITY_IMPORT_H

#include "jx-security.h"
#include <stddef.h>
#include <stdint.h>

typedef struct {
    uint32_t lines_seen;
    uint32_t imported;
    uint32_t ignored;
    uint32_t errors;
} jx_security_import_report;

/* Import whole-file hash signatures used by ClamAV HDB/HSB-style databases
 * and phpMussel Hash signature files. Both use HASH:FILESIZE:NAME records.
 * phpMussel's leading signature-file header is accepted and skipped.
 *
 * Returns the number of imported signatures, or a negative value for invalid
 * API arguments/capacity exhaustion. Malformed individual lines are not
 * activated and are counted in report->errors. */
int jx_security_import_hash_text(const uint8_t *text,
                                 size_t text_length,
                                 jx_security_signature *out,
                                 size_t capacity,
                                 uint32_t first_id,
                                 jx_security_import_report *report);

#endif
