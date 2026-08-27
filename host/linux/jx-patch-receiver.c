#define _POSIX_C_SOURCE 200809L
#include "../common/jx-patch-ssh.h"
#include "../common/jx-patch-ipc.h"
#include <errno.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/un.h>
#include <unistd.h>

#define RESPONSE_MAX 4096u

static int read_exact(FILE *in, uint8_t *out, size_t length) {
    size_t at = 0u;
    while (at < length) {
        size_t n = fread(out + at, 1u, length - at, in);
        if (n == 0u) return ferror(in) ? -1 : -2;
        at += n;
    }
    return 0;
}

static int write_all(int fd, const uint8_t *data, size_t length) {
    size_t at = 0u;
    while (at < length) {
        ssize_t n = send(fd, data + at, length - at, MSG_NOSIGNAL);
        if (n < 0) { if (errno == EINTR) continue; return -1; }
        if (n == 0) return -1;
        at += (size_t)n;
    }
    return 0;
}

static uint32_t parse_ops(const char *text) {
    if (!text || !*text) return 0u;
    uint32_t ops = 0u;
    char buf[128];
    size_t n = strlen(text);
    if (n >= sizeof buf) return 0u;
    memcpy(buf, text, n + 1u);
    for (char *tok = strtok(buf, ","); tok; tok = strtok(NULL, ",")) {
        if (!strcmp(tok, "status")) ops |= JX_PATCH_SSH_OP_STATUS;
        else if (!strcmp(tok, "push")) ops |= JX_PATCH_SSH_OP_PUSH;
        else if (!strcmp(tok, "rollback")) ops |= JX_PATCH_SSH_OP_ROLLBACK;
        else return 0u;
    }
    return ops;
}

static uint8_t ipc_operation(jx_patch_ssh_command_kind kind) {
    if (kind == JX_PATCH_SSH_COMMAND_STATUS) return JX_PATCH_IPC_OP_STATUS;
    if (kind == JX_PATCH_SSH_COMMAND_PUSH) return JX_PATCH_IPC_OP_PUSH;
    if (kind == JX_PATCH_SSH_COMMAND_ROLLBACK) return JX_PATCH_IPC_OP_ROLLBACK;
    return 0u;
}

static int connect_local(const char *path) {
    if (!path || !*path || strlen(path) > JX_PATCH_IPC_SOCKET_PATH_MAX) return -1;
    int fd = socket(AF_UNIX, SOCK_STREAM, 0);
    if (fd < 0) return -1;
    struct sockaddr_un addr;
    memset(&addr, 0, sizeof addr);
    addr.sun_family = AF_UNIX;
    memcpy(addr.sun_path, path, strlen(path) + 1u);
    if (connect(fd, (const struct sockaddr *)&addr, sizeof addr) != 0) { close(fd); return -1; }
    return fd;
}

int main(void) {
    const char *socket_path = getenv("JX_PATCH_SOCKET");
    uint32_t allowed = parse_ops(getenv("JX_PATCH_OPS"));
    if (!socket_path || allowed == 0u) { fputs("ERR receiver-config\n", stderr); return 64; }

    char line[JX_PATCH_SSH_LINE_MAX + 2u];
    if (!fgets(line, sizeof line, stdin)) { fputs("ERR request\n", stderr); return 65; }
    size_t line_len = strlen(line);
    if (line_len == 0u || (line_len == JX_PATCH_SSH_LINE_MAX + 1u && line[line_len - 1u] != '\n')) {
        fputs("ERR request-length\n", stderr); return 65;
    }

    jx_patch_ssh_command command;
    int parsed = jx_patch_ssh_parse_line(line, line_len, &command);
    if (parsed != JX_PATCH_SSH_OK) { fprintf(stderr, "ERR request %d\n", parsed); return 65; }
    if (!jx_patch_ssh_authorized(allowed, command.kind)) { fputs("ERR unauthorized\n", stderr); return 77; }

    uint8_t *manifest = NULL, *signature = NULL, *patch = NULL;
    if (command.kind == JX_PATCH_SSH_COMMAND_PUSH) {
        manifest = malloc(command.manifest_length);
        signature = malloc(command.signature_length);
        patch = malloc(command.patch_length);
        if (!manifest || !signature || !patch) { free(manifest); free(signature); free(patch); fputs("ERR memory\n", stderr); return 70; }
        if (read_exact(stdin, manifest, command.manifest_length) != 0 ||
            read_exact(stdin, signature, command.signature_length) != 0 ||
            read_exact(stdin, patch, command.patch_length) != 0) {
            free(manifest); free(signature); free(patch); fputs("ERR truncated\n", stderr); return 65;
        }
    }

    int fd = connect_local(socket_path);
    if (fd < 0) { free(manifest); free(signature); free(patch); fputs("ERR jx11-unavailable\n", stderr); return 69; }

    jx_patch_ipc_header header;
    memset(&header, 0, sizeof header);
    header.magic = JX_PATCH_IPC_MAGIC;
    header.version = JX_PATCH_IPC_VERSION;
    header.operation = ipc_operation(command.kind);
    header.manifest_length = command.manifest_length;
    header.signature_length = command.signature_length;
    header.patch_length = command.patch_length;
    header.request_id = ((uint64_t)(uint32_t)getpid() << 32) ^ (uint64_t)command.patch_length;

    uint8_t raw[JX_PATCH_IPC_HEADER_BYTES];
    jx_patch_ipc_header_write(raw, &header);
    int failed = write_all(fd, raw, sizeof raw) != 0;
    if (!failed && manifest) failed = write_all(fd, manifest, command.manifest_length) != 0;
    if (!failed && signature) failed = write_all(fd, signature, command.signature_length) != 0;
    if (!failed && patch) failed = write_all(fd, patch, command.patch_length) != 0;
    free(manifest); free(signature); free(patch);
    if (failed) { close(fd); fputs("ERR handoff\n", stderr); return 74; }

    shutdown(fd, SHUT_WR);
    char response[RESPONSE_MAX + 1u];
    ssize_t n = recv(fd, response, RESPONSE_MAX, 0);
    close(fd);
    if (n <= 0) { fputs("ERR no-response\n", stderr); return 74; }
    response[n] = '\0';
    fwrite(response, 1u, (size_t)n, stdout);
    if (response[n - 1] != '\n') fputc('\n', stdout);
    return strncmp(response, "OK", 2u) == 0 ? 0 : 1;
}
