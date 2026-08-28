#ifndef JX11_PATCH_SERVICE_H
#define JX11_PATCH_SERVICE_H
#include <stdint.h>
#include <openssl/evp.h>
#include "jx11-live-patch.h"
#define JX11_PATCH_SOCKET_PATH_MAX 103u
typedef struct {
    int fd;
    char path[JX11_PATCH_SOCKET_PATH_MAX + 1u];
    EVP_PKEY *public_key;
    jx11_live_patch *manager;
} jx11_patch_service;
int jx11_patch_service_open(jx11_patch_service *service, const char *path, const char *public_key_pem, jx11_live_patch *manager);
void jx11_patch_service_close(jx11_patch_service *service);
int jx11_patch_service_fd(const jx11_patch_service *service);
/** Process at most one queued client. Call only at a JX11 quiescent/event-batch boundary. */
int jx11_patch_service_process_one(jx11_patch_service *service);
#endif
