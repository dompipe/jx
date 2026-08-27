#include <stdio.h>
#include <string.h>
#include "../host/common/jx-patch-ssh.h"

static int expect_fail(const char *line) {
    jx_patch_ssh_command command;
    return jx_patch_ssh_parse_line(line, strlen(line), &command) != JX_PATCH_SSH_OK;
}

int main(void) {
    jx_patch_ssh_command command;

    const char *status = "JX-PATCH/1 STATUS\n";
    if (jx_patch_ssh_parse_line(status, strlen(status), &command) != JX_PATCH_SSH_OK ||
        command.kind != JX_PATCH_SSH_COMMAND_STATUS) return 2;
    if (!jx_patch_ssh_authorized(JX_PATCH_SSH_OP_STATUS, command.kind)) return 3;
    if (jx_patch_ssh_authorized(JX_PATCH_SSH_OP_PUSH, command.kind)) return 4;

    const char *rollback = "JX-PATCH/1 ROLLBACK\n";
    if (jx_patch_ssh_parse_line(rollback, strlen(rollback), &command) != JX_PATCH_SSH_OK ||
        command.kind != JX_PATCH_SSH_COMMAND_ROLLBACK) return 5;

    const char *push = "JX-PATCH/1 PUSH 128 64 4096\n";
    if (jx_patch_ssh_parse_line(push, strlen(push), &command) != JX_PATCH_SSH_OK ||
        command.kind != JX_PATCH_SSH_COMMAND_PUSH || command.manifest_length != 128u ||
        command.signature_length != 64u || command.patch_length != 4096u) return 6;
    if (!jx_patch_ssh_authorized(JX_PATCH_SSH_OP_PUSH, command.kind)) return 7;

    if (!expect_fail("JX-PATCH/1 EXEC id\n")) return 8;
    if (!expect_fail("JX-PATCH/1 STATUS extra\n")) return 9;
    if (!expect_fail("JX-PATCH/1 PUSH 128 64 0\n")) return 10;
    if (!expect_fail("JX-PATCH/1 PUSH 128 64 67108865\n")) return 11;
    if (!expect_fail("JX-PATCH/1 PUSH 5000 64 128\n")) return 12;
    if (!expect_fail("JX-PATCH/1 PUSH 128 9000 128\n")) return 13;
    if (!expect_fail("JX-PATCH/1 STATUS;id\n")) return 14;
    if (!expect_fail("JX-PATCH/1 STATUS | sh\n")) return 15;
    if (!expect_fail("JX-PATCH/1 STATUS && whoami\n")) return 16;
    if (!expect_fail("JX-PATCH/1 PUSH 1 1 1 extra\n")) return 17;
    if (!expect_fail("JX-PATCH/2 STATUS\n")) return 18;

    puts("jx-patch-ssh: ok");
    return 0;
}
