#define _POSIX_C_SOURCE 200809L
#include "jx-maintainer-service.h"
#include "../common/jx-maintainer-ipc.h"
#include "../common/jx-remote-bag-wire.h"
#include <errno.h>
#include <fcntl.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/stat.h>
#include <sys/time.h>
#include <sys/un.h>
#include <unistd.h>

static int read_exact(int fd, uint8_t *out, size_t length) {
    size_t at = 0u;
    while (at < length) {
        ssize_t n = recv(fd, out + at, length - at, 0);
        if (n < 0) {
            if (errno == EINTR) continue;
            return -1;
        }
        if (n == 0) return -1;
        at += (size_t)n;
    }
    return 0;
}

static int write_text(int fd, const char *text) {
    size_t at = 0u, length = strlen(text);
    while (at < length) {
        ssize_t n = send(fd, text + at, length - at, MSG_NOSIGNAL);
        if (n < 0) {
            if (errno == EINTR) continue;
            return -1;
        }
        if (n == 0) return -1;
        at += (size_t)n;
    }
    return 0;
}

int jx_linux_maintainer_service_open(jx_linux_maintainer_service *service,
                                     const char *path,
                                     jx_maintainer_service *core) {
    if (!service || !path || !*path || !core) return -1;
    size_t n = strlen(path);
    if (n > JX_MAINTAINER_SOCKET_PATH_MAX) return -1;
    memset(service, 0, sizeof *service);
    service->fd = -1;

    int fd = socket(AF_UNIX, SOCK_STREAM, 0);
    if (fd < 0) return -2;
    int flags = fcntl(fd, F_GETFL, 0);
    if (flags < 0 || fcntl(fd, F_SETFL, flags | O_NONBLOCK) < 0) {
        close(fd);
        return -2;
    }

    struct sockaddr_un addr;
    memset(&addr, 0, sizeof addr);
    addr.sun_family = AF_UNIX;
    memcpy(addr.sun_path, path, n + 1u);
    unlink(path);
    if (bind(fd, (const struct sockaddr *)&addr, sizeof addr) != 0 || listen(fd, 8) != 0) {
        close(fd);
        unlink(path);
        return -3;
    }
    if (chmod(path, 0600) != 0) {
        close(fd);
        unlink(path);
        return -3;
    }

    service->fd = fd;
    service->core = core;
    memcpy(service->path, path, n + 1u);
    return 0;
}

void jx_linux_maintainer_service_close(jx_linux_maintainer_service *service) {
    if (!service) return;
    if (service->fd >= 0) close(service->fd);
    if (service->path[0]) unlink(service->path);
    memset(service, 0, sizeof *service);
    service->fd = -1;
}

int jx_linux_maintainer_service_fd(const jx_linux_maintainer_service *service) {
    return service ? service->fd : -1;
}

int jx_linux_maintainer_service_process_one(jx_linux_maintainer_service *service,
                                            uint64_t now) {
    if (!service || service->fd < 0 || !service->core) return -1;
    int client = accept(service->fd, NULL, NULL);
    if (client < 0) return (errno == EAGAIN || errno == EWOULDBLOCK) ? 0 : -1;

    struct timeval tv = {2, 0};
    (void)setsockopt(client, SOL_SOCKET, SO_RCVTIMEO, &tv, sizeof tv);

    uint8_t raw[JX_MAINTAINER_IPC_HEADER_BYTES];
    if (read_exact(client, raw, sizeof raw) != 0) {
        write_text(client, "ERR ipc-header\n");
        close(client);
        return -2;
    }
    jx_maintainer_ipc_header header;
    if (jx_maintainer_ipc_read(raw, sizeof raw, &header) != 0 ||
        header.operation != JX_MAINTAINER_IPC_OP_BAG ||
        header.request_length != JX_REMOTE_BAG_REQUEST_WIRE_BYTES ||
        header.json_length == 0u || header.json_length > JX_BAG_PATCH_JSON_MAX) {
        write_text(client, "ERR lengths\n");
        close(client);
        return -3;
    }

    uint8_t request_wire[JX_REMOTE_BAG_REQUEST_WIRE_BYTES];
    uint8_t *json = (uint8_t *)malloc(header.json_length);
    if (!json) {
        write_text(client, "ERR memory\n");
        close(client);
        return -4;
    }
    if (read_exact(client, request_wire, sizeof request_wire) != 0 ||
        read_exact(client, json, header.json_length) != 0) {
        free(json);
        write_text(client, "ERR truncated\n");
        close(client);
        return -5;
    }

    jx_remote_bag_request request;
    if (jx_remote_bag_request_read(request_wire, sizeof request_wire, &request) != 0 ||
        request.qualifier.json_length != header.json_length) {
        free(json);
        write_text(client, "ERR request\n");
        close(client);
        return -6;
    }

    int rc = jx_maintainer_service_apply(service->core, &request, now, json, header.json_length);
    free(json);
    if (rc != JX_MAINTAINER_SERVICE_OK) {
        char response[64];
        snprintf(response, sizeof response, "ERR apply=%d\n", rc);
        write_text(client, response);
        close(client);
        return -7;
    }

    write_text(client, "OK bag\n");
    close(client);
    return 1;
}
