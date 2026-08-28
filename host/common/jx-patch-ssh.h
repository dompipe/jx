#ifndef JX_PATCH_SSH_H
#define JX_PATCH_SSH_H

#include <stddef.h>
#include <stdint.h>
#include "jx-live-patch.h"

#define JX_PATCH_SSH_VERSION 1u
#define JX_PATCH_SSH_LINE_MAX 160u
#define JX_PATCH_SSH_SIGNATURE_MAX JX_PATCH_SIGNATURE_MAX
#define JX_PATCH_SSH_MANIFEST_MAX 4096u

#define JX_PATCH_SSH_OP_STATUS   (1u << 0)
#define JX_PATCH_SSH_OP_PUSH     (1u << 1)
#define JX_PATCH_SSH_OP_ROLLBACK (1u << 2)
#define JX_PATCH_SSH_OP_ALL      (JX_PATCH_SSH_OP_STATUS | JX_PATCH_SSH_OP_PUSH | JX_PATCH_SSH_OP_ROLLBACK)

typedef enum {
    JX_PATCH_SSH_COMMAND_INVALID = 0,
    JX_PATCH_SSH_COMMAND_STATUS = 1,
    JX_PATCH_SSH_COMMAND_PUSH = 2,
    JX_PATCH_SSH_COMMAND_ROLLBACK = 3
} jx_patch_ssh_command_kind;

typedef enum {
    JX_PATCH_SSH_OK = 0,
    JX_PATCH_SSH_ERR_ARGUMENT = -100,
    JX_PATCH_SSH_ERR_VERSION = -101,
    JX_PATCH_SSH_ERR_COMMAND = -102,
    JX_PATCH_SSH_ERR_FORMAT = -103,
    JX_PATCH_SSH_ERR_LENGTH = -104,
    JX_PATCH_SSH_ERR_AUTH = -105
} jx_patch_ssh_result;

typedef struct {
    jx_patch_ssh_command_kind kind;
    uint32_t manifest_length;
    uint32_t signature_length;
    uint32_t patch_length;
} jx_patch_ssh_command;

/**
 * Parse exactly one ASCII command line. Supported forms:
 *   JX-PATCH/1 STATUS
 *   JX-PATCH/1 ROLLBACK
 *   JX-PATCH/1 PUSH <manifest-bytes> <signature-bytes> <patch-bytes>
 *
 * No quoting, shell expansion, extra arguments, or command separators exist in
 * this protocol. The PUSH body follows the line as raw bounded bytes.
 */
int jx_patch_ssh_parse_line(const char *line, size_t length, jx_patch_ssh_command *out);

uint32_t jx_patch_ssh_required_operation(jx_patch_ssh_command_kind kind);
int jx_patch_ssh_authorized(uint32_t allowed_operations, jx_patch_ssh_command_kind kind);

#endif
