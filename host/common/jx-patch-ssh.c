#include "jx-patch-ssh.h"
#include <ctype.h>
#include <stdio.h>
#include <string.h>

static int has_forbidden_byte(const char *line, size_t length) {
    for (size_t i = 0; i < length; ++i) {
        unsigned char c = (unsigned char)line[i];
        if (c == 0u || c == ';' || c == '|' || c == '&' || c == '`' || c == '\\' || c == '"' || c == '\'') return 1;
        if (c < 0x20u && c != '\n' && c != '\r' && c != '\t') return 1;
        if (c > 0x7eu) return 1;
    }
    return 0;
}

static int parse_u32_token(const char *text, uint32_t *out) {
    if (!text || !*text || !out) return 0;
    uint64_t value = 0u;
    for (const unsigned char *p = (const unsigned char *)text; *p; ++p) {
        if (!isdigit(*p)) return 0;
        value = value * 10u + (uint64_t)(*p - (unsigned char)'0');
        if (value > 0xffffffffu) return 0;
    }
    *out = (uint32_t)value;
    return 1;
}

uint32_t jx_patch_ssh_required_operation(jx_patch_ssh_command_kind kind) {
    switch (kind) {
        case JX_PATCH_SSH_COMMAND_STATUS: return JX_PATCH_SSH_OP_STATUS;
        case JX_PATCH_SSH_COMMAND_PUSH: return JX_PATCH_SSH_OP_PUSH;
        case JX_PATCH_SSH_COMMAND_ROLLBACK: return JX_PATCH_SSH_OP_ROLLBACK;
        default: return 0u;
    }
}

int jx_patch_ssh_authorized(uint32_t allowed_operations, jx_patch_ssh_command_kind kind) {
    uint32_t required = jx_patch_ssh_required_operation(kind);
    return required != 0u && (allowed_operations & required) == required;
}

int jx_patch_ssh_parse_line(const char *line, size_t length, jx_patch_ssh_command *out) {
    if (!line || !out) return JX_PATCH_SSH_ERR_ARGUMENT;
    if (length == 0u || length > JX_PATCH_SSH_LINE_MAX) return JX_PATCH_SSH_ERR_LENGTH;
    if (has_forbidden_byte(line, length)) return JX_PATCH_SSH_ERR_FORMAT;

    char buffer[JX_PATCH_SSH_LINE_MAX + 1u];
    memcpy(buffer, line, length);
    buffer[length] = '\0';
    while (length > 0u && (buffer[length - 1u] == '\n' || buffer[length - 1u] == '\r')) buffer[--length] = '\0';
    if (length == 0u) return JX_PATCH_SSH_ERR_FORMAT;

    memset(out, 0, sizeof *out);

    char version[16];
    char command[16];
    char a[16] = {0};
    char b[16] = {0};
    char c[16] = {0};
    char extra[2] = {0};
    int fields = sscanf(buffer, "%15s %15s %15s %15s %15s %1s", version, command, a, b, c, extra);
    if (fields < 2) return JX_PATCH_SSH_ERR_FORMAT;
    if (strcmp(version, "JX-PATCH/1") != 0) return JX_PATCH_SSH_ERR_VERSION;

    if (strcmp(command, "STATUS") == 0) {
        if (fields != 2) return JX_PATCH_SSH_ERR_FORMAT;
        out->kind = JX_PATCH_SSH_COMMAND_STATUS;
        return JX_PATCH_SSH_OK;
    }
    if (strcmp(command, "ROLLBACK") == 0) {
        if (fields != 2) return JX_PATCH_SSH_ERR_FORMAT;
        out->kind = JX_PATCH_SSH_COMMAND_ROLLBACK;
        return JX_PATCH_SSH_OK;
    }
    if (strcmp(command, "PUSH") == 0) {
        if (fields != 5) return JX_PATCH_SSH_ERR_FORMAT;
        if (!parse_u32_token(a, &out->manifest_length) ||
            !parse_u32_token(b, &out->signature_length) ||
            !parse_u32_token(c, &out->patch_length)) return JX_PATCH_SSH_ERR_FORMAT;
        if (out->manifest_length == 0u || out->manifest_length > JX_PATCH_SSH_MANIFEST_MAX ||
            out->signature_length == 0u || out->signature_length > JX_PATCH_SSH_SIGNATURE_MAX ||
            out->patch_length == 0u || out->patch_length > JX_PATCH_MAX_BYTES) return JX_PATCH_SSH_ERR_LENGTH;
        out->kind = JX_PATCH_SSH_COMMAND_PUSH;
        return JX_PATCH_SSH_OK;
    }

    return JX_PATCH_SSH_ERR_COMMAND;
}
