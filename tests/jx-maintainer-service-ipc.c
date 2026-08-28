#define _POSIX_C_SOURCE 200809L
#include "../host/linux/jx-maintainer-service.h"
#include "../host/common/jx-live-bag.h"
#include "../host/common/jx-maintainer-ipc.h"
#include "../host/common/jx-remote-bag-wire.h"
#include <assert.h>
#include <errno.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/un.h>
#include <sys/wait.h>
#include <time.h>
#include <unistd.h>

typedef struct {
    jx_live_bag_registry registry;
    uint8_t digest[32];
} test_context;

typedef struct {
    uint8_t *json;
    size_t length;
} test_candidate;

static void fill(uint8_t *p, uint8_t value) {
    memset(p, value, 32u);
}

static int resolve_cb(const char *name, jx_bag_patch_current *out, void *context) {
    test_context *ctx = (test_context *)context;
    return jx_live_bag_current(&ctx->registry, name, out);
}

static int build_cb(const jx_bag_patch_current *current,
                    const jx_bag_patch_qualifier *target,
                    const uint8_t *json,
                    size_t length,
                    void **out,
                    uint8_t digest[32],
                    void *context) {
    (void)current;
    (void)target;
    test_context *ctx = (test_context *)context;
    test_candidate *candidate = (test_candidate *)calloc(1u, sizeof *candidate);
    if (!candidate) return -1;
    candidate->json = (uint8_t *)malloc(length);
    if (!candidate->json) {
        free(candidate);
        return -1;
    }
    memcpy(candidate->json, json, length);
    candidate->length = length;
    memcpy(digest, ctx->digest, 32u);
    *out = candidate;
    return 0;
}

static int commit_cb(void *candidate,
                     const jx_bag_patch_qualifier *target,
                     void *context) {
    test_candidate *item = (test_candidate *)candidate;
    test_context *ctx = (test_context *)context;
    return jx_live_bag_replace(&ctx->registry, target, item->json, item->length);
}

static void discard_cb(void *candidate, void *context) {
    (void)context;
    test_candidate *item = (test_candidate *)candidate;
    if (!item) return;
    free(item->json);
    free(item);
}

static int write_all(int fd, const uint8_t *bytes, size_t length) {
    size_t at = 0u;
    while (at < length) {
        ssize_t n = write(fd, bytes + at, length - at);
        if (n < 0) {
            if (errno == EINTR) continue;
            return -1;
        }
        if (n == 0) return -1;
        at += (size_t)n;
    }
    return 0;
}

static void tiny_sleep(void) {
    const struct timespec ts = {0, 1000000L};
    nanosleep(&ts, NULL);
}

int main(void) {
    char path[108];
    snprintf(path, sizeof path, "/tmp/jx-maint-%ld.sock", (long)getpid());

    test_context ctx;
    memset(&ctx, 0, sizeof ctx);
    jx_live_bag_registry_init(&ctx.registry);
    fill(ctx.digest, 0x44u);

    uint8_t schema[32], content[32], trust[32];
    fill(schema, 0x11u);
    fill(content, 0x22u);
    fill(trust, 0x55u);
    const uint8_t old_json[] = "{\"gap\":4}";
    const uint8_t new_json[] = "{\"gap\":8}";
    assert(jx_live_bag_add(&ctx.registry, "Controls", JX_BAG_DISCIPLINE_RECORD, 7u,
                           schema, content, old_json, sizeof old_json - 1u) == 0);

    jx_remote_bag_source source;
    memset(&source, 0, sizeof source);
    source.version = JX_REMOTE_BAG_VERSION;
    source.transport = JX_REMOTE_BAG_TRANSPORT_SSH;
    source.capability_mask = JX_REMOTE_BAG_CAP_WRITE;
    source.enabled = 1u;
    source.maintainer = 1u;
    strcpy(source.source_id, "corp");
    strcpy(source.installation_id, "prod");
    strcpy(source.bag_name, "Controls");
    memcpy(source.maintainer_trust_digest, trust, 32u);

    jx_maintainer_service core;
    memset(&core, 0, sizeof core);
    core.version = JX_MAINTAINER_SERVICE_VERSION;
    core.plane.version = JX_REMOTE_BAG_VERSION;
    core.plane.provisioned = 1u;
    strcpy(core.plane.installation_id, "prod");
    memcpy(core.plane.maintainer_trust_digest, trust, 32u);
    core.sources = &source;
    core.source_count = 1u;
    core.resolve = resolve_cb;
    core.build = build_cb;
    core.commit = commit_cb;
    core.discard = discard_cb;
    core.context = &ctx;

    jx_linux_maintainer_service service;
    assert(jx_linux_maintainer_service_open(&service, path, &core) == 0);

    jx_remote_bag_request request;
    memset(&request, 0, sizeof request);
    request.version = JX_REMOTE_BAG_VERSION;
    request.transport = JX_REMOTE_BAG_TRANSPORT_SSH;
    request.requested_capabilities = JX_REMOTE_BAG_CAP_WRITE;
    strcpy(request.source_id, "corp");
    strcpy(request.installation_id, "prod");
    request.sequence = 1u;
    request.issued_at = 10u;
    request.expires_at = 20u;
    request.qualifier.version = JX_BAG_PATCH_VERSION;
    request.qualifier.discipline = JX_BAG_DISCIPLINE_RECORD;
    request.qualifier.expected_revision = 7u;
    request.qualifier.target_revision = 8u;
    strcpy(request.qualifier.bag_name, "Controls");
    memcpy(request.qualifier.current_schema_digest, schema, 32u);
    memcpy(request.qualifier.current_content_digest, content, 32u);
    memcpy(request.qualifier.target_schema_digest, schema, 32u);
    memcpy(request.qualifier.target_json_digest, ctx.digest, 32u);
    request.qualifier.json_length = (uint32_t)(sizeof new_json - 1u);

    uint8_t request_wire[JX_REMOTE_BAG_REQUEST_WIRE_BYTES];
    assert(jx_remote_bag_request_write(request_wire, &request) == 0);

    jx_maintainer_ipc_header ipc = {
        JX_MAINTAINER_IPC_VERSION,
        JX_MAINTAINER_IPC_OP_BAG,
        0u,
        JX_REMOTE_BAG_REQUEST_WIRE_BYTES,
        (uint32_t)(sizeof new_json - 1u)
    };
    uint8_t header[JX_MAINTAINER_IPC_HEADER_BYTES];
    assert(jx_maintainer_ipc_write(header, &ipc) == 0);

    pid_t child = fork();
    assert(child >= 0);
    if (child == 0) {
        int fd = socket(AF_UNIX, SOCK_STREAM, 0);
        if (fd < 0) _exit(2);
        struct sockaddr_un addr;
        memset(&addr, 0, sizeof addr);
        addr.sun_family = AF_UNIX;
        strcpy(addr.sun_path, path);
        if (connect(fd, (struct sockaddr *)&addr, sizeof addr) != 0) _exit(3);
        if (write_all(fd, header, sizeof header) != 0 ||
            write_all(fd, request_wire, sizeof request_wire) != 0 ||
            write_all(fd, new_json, sizeof new_json - 1u) != 0) _exit(4);
        shutdown(fd, SHUT_WR);
        char response[32] = {0};
        ssize_t n = read(fd, response, sizeof response - 1u);
        close(fd);
        _exit(n > 0 && strncmp(response, "OK bag", 6u) == 0 ? 0 : 5);
    }

    int processed = 0;
    for (int i = 0; i < 100 && !processed; ++i) {
        int rc = jx_linux_maintainer_service_process_one(&service, 15u);
        if (rc == 1) processed = 1;
        else if (rc < 0) assert(0);
        else tiny_sleep();
    }
    assert(processed);

    int status = 0;
    waitpid(child, &status, 0);
    assert(WIFEXITED(status) && WEXITSTATUS(status) == 0);
    jx_live_bag *bag = jx_live_bag_find(&ctx.registry, "Controls");
    assert(bag && bag->revision == 8u &&
           bag->json_length == sizeof new_json - 1u &&
           memcmp(bag->json, new_json, sizeof new_json - 1u) == 0);

    jx_linux_maintainer_service_close(&service);
    jx_live_bag_registry_dispose(&ctx.registry);
    puts("jx-maintainer-service-ipc: JXM1 -> private service -> live Bag ok");
    return 0;
}
