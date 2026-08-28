#define _POSIX_C_SOURCE 200809L
#include "jx11-patch-service.h"
#include "jx11-patch-module.h"
#include "../common/jx-patch-ipc.h"
#include "../common/jx-live-patch-wire.h"
#include <errno.h>
#include <fcntl.h>
#include <openssl/pem.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/stat.h>
#include <sys/time.h>
#include <sys/un.h>
#include <time.h>
#include <unistd.h>

static int read_exact(int fd, uint8_t *out, size_t length) {
    size_t at = 0u;
    while (at < length) {
        ssize_t n = recv(fd, out + at, length - at, 0);
        if (n < 0) { if (errno == EINTR) continue; return -1; }
        if (n == 0) return -1;
        at += (size_t)n;
    }
    return 0;
}

static int write_text(int fd, const char *text) {
    size_t length = strlen(text), at = 0u;
    while (at < length) {
        ssize_t n = send(fd, text + at, length - at, MSG_NOSIGNAL);
        if (n < 0) { if (errno == EINTR) continue; return -1; }
        if (n == 0) return -1;
        at += (size_t)n;
    }
    return 0;
}

typedef struct { EVP_PKEY *key; } verify_context;

static int verify_ed25519(const jx_patch_manifest *manifest,
                          const uint8_t *signature, size_t signature_length,
                          const uint8_t *patch, size_t patch_length, void *context) {
    verify_context *ctx = (verify_context *)context;
    if (!ctx || !ctx->key || !manifest || !signature || !patch) return 0;
    uint8_t wire[JX_PATCH_MANIFEST_WIRE_BYTES];
    if (jx_patch_manifest_write(wire, manifest) != 0) return 0;
    size_t total = sizeof wire + patch_length;
    uint8_t *message = malloc(total);
    if (!message) return 0;
    memcpy(message, wire, sizeof wire);
    memcpy(message + sizeof wire, patch, patch_length);
    EVP_MD_CTX *md = EVP_MD_CTX_new();
    if (!md) { free(message); return 0; }
    int ok = EVP_DigestVerifyInit(md, NULL, NULL, NULL, ctx->key) == 1 &&
             EVP_DigestVerify(md, signature, signature_length, message, total) == 1;
    EVP_MD_CTX_free(md);
    free(message);
    return ok;
}

static int sha256_bytes(const uint8_t *bytes, size_t length, uint8_t out[JX_PATCH_DIGEST_BYTES]) {
    unsigned int out_length = 0u;
    return bytes && out && EVP_Digest(bytes, length, out, &out_length, EVP_sha256(), NULL) == 1 &&
           out_length == JX_PATCH_DIGEST_BYTES ? 0 : -1;
}

static EVP_PKEY *load_public_key(const char *path) {
    FILE *fp = fopen(path, "r");
    if (!fp) return NULL;
    EVP_PKEY *key = PEM_read_PUBKEY(fp, NULL, NULL, NULL);
    fclose(fp);
    if (!key) return NULL;
    if (EVP_PKEY_base_id(key) != EVP_PKEY_ED25519) { EVP_PKEY_free(key); return NULL; }
    return key;
}

static void module_activate(jx11_patch_service *service, const jx11_generation *generation) {
    if (service && service->host && generation && generation->native_module && generation->native_module->activate)
        generation->native_module->activate(service->host);
}

static void module_deactivate(jx11_patch_service *service, const jx11_generation *generation) {
    if (service && service->host && generation && generation->native_module && generation->native_module->deactivate)
        generation->native_module->deactivate(service->host);
}

int jx11_patch_service_open(jx11_patch_service *service, const char *path,
                            const char *public_key_pem, jx11_live_patch *manager) {
    if (!service || !path || !*path || !public_key_pem || !*public_key_pem || !manager) return -1;
    size_t n = strlen(path);
    if (n > JX11_PATCH_SOCKET_PATH_MAX) return -1;
    memset(service, 0, sizeof *service);
    service->fd = -1;
    service->public_key = load_public_key(public_key_pem);
    if (!service->public_key) return -2;
    int fd = socket(AF_UNIX, SOCK_STREAM, 0);
    if (fd < 0) { EVP_PKEY_free(service->public_key); service->public_key=NULL; return -3; }
    int flags = fcntl(fd, F_GETFL, 0);
    if (flags < 0 || fcntl(fd, F_SETFL, flags | O_NONBLOCK) < 0) { close(fd); EVP_PKEY_free(service->public_key); service->public_key=NULL; return -3; }
    struct sockaddr_un addr; memset(&addr,0,sizeof addr); addr.sun_family=AF_UNIX; memcpy(addr.sun_path,path,n+1u);
    unlink(path);
    if (bind(fd,(const struct sockaddr *)&addr,sizeof addr)!=0 || listen(fd,8)!=0) { close(fd); unlink(path); EVP_PKEY_free(service->public_key); service->public_key=NULL; return -4; }
    if (chmod(path,0600) != 0) { close(fd); unlink(path); EVP_PKEY_free(service->public_key); service->public_key=NULL; return -4; }
    service->fd=fd; service->manager=manager; memcpy(service->path,path,n+1u);
    return 0;
}

void jx11_patch_service_set_host(jx11_patch_service *service, const jx11_patch_host_v1 *host) {
    if (service) service->host = host;
}

void jx11_patch_service_close(jx11_patch_service *service) {
    if (!service) return;
    if (service->manager) {
        module_deactivate(service, &service->manager->active);
        jx11_live_patch_dispose(service->manager);
    }
    if (service->fd >= 0) close(service->fd);
    if (service->path[0]) unlink(service->path);
    if (service->public_key) EVP_PKEY_free(service->public_key);
    memset(service,0,sizeof *service); service->fd=-1;
}

int jx11_patch_service_fd(const jx11_patch_service *service) { return service ? service->fd : -1; }

int jx11_patch_service_process_one(jx11_patch_service *service) {
    if (!service || service->fd < 0 || !service->manager) return -1;
    int client = accept(service->fd,NULL,NULL);
    if (client < 0) return (errno==EAGAIN || errno==EWOULDBLOCK) ? 0 : -1;
    struct timeval tv; tv.tv_sec=2; tv.tv_usec=0; setsockopt(client,SOL_SOCKET,SO_RCVTIMEO,&tv,sizeof tv);
    uint8_t raw[JX_PATCH_IPC_HEADER_BYTES];
    if (read_exact(client,raw,sizeof raw)!=0) { write_text(client,"ERR ipc-header\n"); close(client); return -2; }
    jx_patch_ipc_header h;
    if (jx_patch_ipc_header_read(raw,sizeof raw,&h)!=0) { write_text(client,"ERR ipc-header\n"); close(client); return -2; }
    if (h.operation == JX_PATCH_IPC_OP_STATUS) {
        char response[256];
        const char *name = service->manager->active.native_module ? service->manager->active.native_module->name : "core";
        snprintf(response,sizeof response,"OK generation=%llu pending=%u rollback=%u module=%s\n",
                 (unsigned long long)service->manager->active.generation,
                 (unsigned)service->manager->pending_ready,(unsigned)service->manager->previous_valid,name);
        write_text(client,response); close(client); return 1;
    }
    if (h.operation == JX_PATCH_IPC_OP_ROLLBACK) {
        jx11_generation old_active = service->manager->active;
        int r=jx11_live_patch_rollback(service->manager);
        if (r==JX_PATCH_OK) {
            module_deactivate(service, &old_active);
            module_activate(service, &service->manager->active);
        }
        write_text(client,r==JX_PATCH_OK?"OK rollback\n":"ERR rollback\n"); close(client); return r==JX_PATCH_OK?1:-3;
    }
    if (h.operation != JX_PATCH_IPC_OP_PUSH ||
        h.manifest_length != JX_PATCH_MANIFEST_WIRE_BYTES ||
        h.signature_length == 0u || h.signature_length > JX_PATCH_SIGNATURE_MAX ||
        h.patch_length == 0u || h.patch_length > JX_PATCH_MAX_BYTES) {
        write_text(client,"ERR lengths\n"); close(client); return -4;
    }
    uint8_t manifest_raw[JX_PATCH_MANIFEST_WIRE_BYTES];
    uint8_t *signature=malloc(h.signature_length), *patch=malloc(h.patch_length);
    if (!signature || !patch) { free(signature); free(patch); write_text(client,"ERR memory\n"); close(client); return -5; }
    if (read_exact(client,manifest_raw,sizeof manifest_raw)!=0 || read_exact(client,signature,h.signature_length)!=0 || read_exact(client,patch,h.patch_length)!=0) {
        free(signature); free(patch); write_text(client,"ERR truncated\n"); close(client); return -6;
    }
    jx_patch_manifest manifest;
    if (jx_patch_manifest_read(manifest_raw,sizeof manifest_raw,&manifest)!=0) { free(signature); free(patch); write_text(client,"ERR manifest\n"); close(client); return -7; }
    if ((manifest.capability_mask & JX_PATCH_CAP_NATIVE_CODE) == 0u) {
        free(signature); free(patch); write_text(client,"ERR native-capability\n"); close(client); return -7;
    }
    uint8_t actual_digest[JX_PATCH_DIGEST_BYTES];
    if (sha256_bytes(patch,h.patch_length,actual_digest)!=0 || !jx_patch_digest_equal(actual_digest,manifest.target_digest)) {
        free(signature); free(patch); write_text(client,"ERR target-digest\n"); close(client); return -7;
    }
    verify_context vctx={service->public_key};
    int valid=jx_patch_validate(&service->manager->security,&manifest,signature,h.signature_length,patch,h.patch_length,(uint64_t)time(NULL),verify_ed25519,&vctx);
    free(signature);
    if (valid!=JX_PATCH_OK) { free(patch); char response[64]; snprintf(response,sizeof response,"ERR validate=%d\n",valid); write_text(client,response); close(client); return -8; }

    jx11_loaded_patch_module loaded;
    int lrc = jx11_patch_module_load(patch, h.patch_length, &loaded);
    free(patch);
    if (lrc != 0) { char response[64]; snprintf(response,sizeof response,"ERR module=%d\n",lrc); write_text(client,response); close(client); return -9; }

    jx11_generation staged; memset(&staged,0,sizeof staged);
    staged.generation=manifest.generation;
    memcpy(staged.digest,manifest.target_digest,JX_PATCH_DIGEST_BYTES);
    staged.native_handle=loaded.handle;
    staged.native_module=loaded.module;
    int r=jx11_live_patch_stage(service->manager,&manifest,&staged);
    if (r!=JX_PATCH_OK) { jx11_patch_module_unload(&loaded); write_text(client,"ERR stage\n"); close(client); return -9; }

    jx11_generation old_active = service->manager->active;
    r=jx11_live_patch_commit_pending(service->manager,&manifest);
    if (r!=JX_PATCH_OK) {
        jx11_live_patch_discard_pending(service->manager);
        write_text(client,"ERR commit\n"); close(client); return -9;
    }
    module_deactivate(service, &old_active);
    module_activate(service, &service->manager->active);

    char response[192];
    snprintf(response,sizeof response,"OK generation=%llu module=%s\n",
             (unsigned long long)service->manager->active.generation,
             service->manager->active.native_module->name);
    write_text(client,response); close(client); return 1;
}
