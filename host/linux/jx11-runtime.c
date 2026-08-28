#define _POSIX_C_SOURCE 200809L
#include <xcb/xcb.h>
#include <errno.h>
#include <fcntl.h>
#include <poll.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <openssl/evp.h>
#include "jx11-live-patch.h"
#include "jx11-patch-service.h"

static jx11_live_patch patch_manager;
static jx11_patch_service patch_service;
static int patch_service_enabled = 0;

static xcb_generic_event_t *jx11_runtime_wait_for_event(xcb_connection_t *connection);

/* Compile the existing JX11 core into this translation unit, but route its
 * blocking wait through the dual-FD runtime below. This leaves all X event
 * semantics in one canonical core while making the produced jx11 binary
 * patch-aware. */
#define xcb_wait_for_event jx11_runtime_wait_for_event
#define main jx11_core_main
#include "jx11.c"
#undef main
#undef xcb_wait_for_event

static int hash_running_image(uint8_t out[JX_PATCH_DIGEST_BYTES]) {
    FILE *fp = fopen("/proc/self/exe", "rb");
    if (!fp) return -1;
    EVP_MD_CTX *ctx = EVP_MD_CTX_new();
    if (!ctx) { fclose(fp); return -1; }
    int ok = EVP_DigestInit_ex(ctx, EVP_sha256(), NULL) == 1;
    uint8_t buffer[32768];
    while (ok) {
        size_t n = fread(buffer, 1u, sizeof buffer, fp);
        if (n > 0u && EVP_DigestUpdate(ctx, buffer, n) != 1) ok = 0;
        if (n < sizeof buffer) {
            if (ferror(fp)) ok = 0;
            break;
        }
    }
    unsigned int digest_length = 0u;
    if (ok && (EVP_DigestFinal_ex(ctx, out, &digest_length) != 1 ||
               digest_length != JX_PATCH_DIGEST_BYTES)) ok = 0;
    EVP_MD_CTX_free(ctx);
    fclose(fp);
    return ok ? 0 : -1;
}

static xcb_generic_event_t *jx11_runtime_wait_for_event(xcb_connection_t *connection) {
    if (!patch_service_enabled) {
        /* We cannot call xcb_wait_for_event here because the core's macro has
         * been removed. Poll the X fd directly and consume through XCB. */
        for (;;) {
            xcb_generic_event_t *event = xcb_poll_for_event(connection);
            if (event) return event;
            if (xcb_connection_has_error(connection)) return NULL;
            struct pollfd p = { xcb_get_file_descriptor(connection), POLLIN, 0 };
            int rc = poll(&p, 1u, -1);
            if (rc < 0 && errno == EINTR) continue;
            if (rc < 0) return NULL;
        }
    }

    for (;;) {
        xcb_generic_event_t *event = xcb_poll_for_event(connection);
        if (event) return event;
        if (xcb_connection_has_error(connection)) return NULL;

        struct pollfd fds[2];
        fds[0].fd = xcb_get_file_descriptor(connection);
        fds[0].events = POLLIN;
        fds[0].revents = 0;
        fds[1].fd = jx11_patch_service_fd(&patch_service);
        fds[1].events = POLLIN;
        fds[1].revents = 0;

        int rc = poll(fds, 2u, -1);
        if (rc < 0 && errno == EINTR) continue;
        if (rc < 0) return NULL;

        /* Patch work is handled only here: the core reaches this function
         * after finishing its previous X event batch. Therefore PUSH and
         * ROLLBACK activation happen at a quiescent boundary. Limit each wake
         * to one transaction so X cannot be starved by patch traffic. */
        if ((fds[1].revents & POLLIN) != 0) {
            int prc = jx11_patch_service_process_one(&patch_service);
            if (prc < 0) fprintf(stderr, "jx11: patch service transaction failed (%d)\n", prc);
        }
        if ((fds[0].revents & (POLLIN | POLLERR | POLLHUP)) != 0) {
            event = xcb_poll_for_event(connection);
            if (event) return event;
            if (xcb_connection_has_error(connection)) return NULL;
        }
    }
}

static void print_runtime_help(void) {
    puts("jx11 [--nested] [--desktop FILE] [--launch PROGRAM] [--patch-socket PATH --patch-pubkey PEM]");
    puts("  --patch-socket PATH   enable local JXP1 live-patch service");
    puts("  --patch-pubkey PEM    Ed25519 public key used to authorize signed patches");
}

int main(int argc, char **argv) {
    const char *patch_socket = NULL;
    const char *patch_pubkey = NULL;
    char **core_argv = calloc((size_t)argc + 1u, sizeof *core_argv);
    if (!core_argv) return 70;
    int core_argc = 0;
    core_argv[core_argc++] = argv[0];

    for (int i = 1; i < argc; ++i) {
        if (!strcmp(argv[i], "--patch-socket")) {
            if (++i >= argc) { fputs("jx11: --patch-socket requires a path\n", stderr); free(core_argv); return 64; }
            patch_socket = argv[i];
        } else if (!strcmp(argv[i], "--patch-pubkey")) {
            if (++i >= argc) { fputs("jx11: --patch-pubkey requires a PEM file\n", stderr); free(core_argv); return 64; }
            patch_pubkey = argv[i];
        } else if (!strcmp(argv[i], "--help")) {
            print_runtime_help();
            free(core_argv);
            return 0;
        } else {
            core_argv[core_argc++] = argv[i];
        }
    }

    if ((patch_socket == NULL) != (patch_pubkey == NULL)) {
        fputs("jx11: --patch-socket and --patch-pubkey must be supplied together\n", stderr);
        free(core_argv);
        return 64;
    }

    if (patch_socket) {
        uint8_t base_digest[JX_PATCH_DIGEST_BYTES];
        if (hash_running_image(base_digest) != 0) {
            fputs("jx11: cannot hash running image for live-patch base identity\n", stderr);
            free(core_argv);
            return 78;
        }
        jx11_live_patch_init(&patch_manager, 1u, base_digest, JX_PATCH_CAP_ALL);
        int rc = jx11_patch_service_open(&patch_service, patch_socket, patch_pubkey, &patch_manager);
        if (rc != 0) {
            fprintf(stderr, "jx11: cannot open patch service (%d)\n", rc);
            free(core_argv);
            return 78;
        }
        patch_service_enabled = 1;
        fprintf(stderr, "jx11: signed live patch service active socket=%s generation=1\n", patch_socket);
    }

    int rc = jx11_core_main(core_argc, core_argv);
    if (patch_service_enabled) {
        jx11_patch_service_close(&patch_service);
        patch_service_enabled = 0;
    }
    free(core_argv);
    return rc;
}
