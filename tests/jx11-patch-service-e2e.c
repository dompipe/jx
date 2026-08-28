#define _POSIX_C_SOURCE 200809L
#include "../host/linux/jx11-patch-service.h"
#include "../host/common/jx-live-patch-wire.h"
#include "../host/common/jx-patch-ipc.h"
#include <errno.h>
#include <fcntl.h>
#include <openssl/evp.h>
#include <openssl/pem.h>
#include <poll.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/un.h>
#include <sys/wait.h>
#include <time.h>
#include <unistd.h>

static uint32_t seen_background = 0u;
static int logs = 0;
static void host_log(int level, const char *message) { if (level == 1 && message && *message) ++logs; }
static void host_background(uint32_t rgb) { seen_background = rgb; }
static void host_invalidate(void) { }
static uint64_t host_generation(void) { return 2u; }
static const jx11_patch_host_v1 host = {
    JX11_PATCH_MODULE_ABI_VERSION, sizeof(jx11_patch_host_v1),
    host_log, host_background, host_invalidate, host_generation
};

static uint8_t *read_file(const char *path, size_t *length) {
    FILE *fp = fopen(path, "rb");
    if (!fp) return NULL;
    if (fseek(fp,0,SEEK_END)!=0) { fclose(fp); return NULL; }
    long n=ftell(fp);
    if (n<=0 || fseek(fp,0,SEEK_SET)!=0) { fclose(fp); return NULL; }
    uint8_t *bytes=malloc((size_t)n);
    if (!bytes) { fclose(fp); return NULL; }
    if (fread(bytes,1u,(size_t)n,fp)!=(size_t)n) { free(bytes); fclose(fp); return NULL; }
    fclose(fp); *length=(size_t)n; return bytes;
}

static int sha256(const uint8_t *bytes, size_t length, uint8_t out[JX_PATCH_DIGEST_BYTES]) {
    unsigned int n=0u;
    return EVP_Digest(bytes,length,out,&n,EVP_sha256(),NULL)==1 && n==JX_PATCH_DIGEST_BYTES ? 0 : -1;
}

static EVP_PKEY *make_key(void) {
    EVP_PKEY_CTX *ctx=EVP_PKEY_CTX_new_id(EVP_PKEY_ED25519,NULL);
    if (!ctx) return NULL;
    EVP_PKEY *key=NULL;
    int ok=EVP_PKEY_keygen_init(ctx)==1 && EVP_PKEY_keygen(ctx,&key)==1;
    EVP_PKEY_CTX_free(ctx);
    return ok ? key : NULL;
}

static int write_public_key(EVP_PKEY *key, char path[64]) {
    strcpy(path,"/tmp/jx11-pub-XXXXXX");
    int fd=mkstemp(path);
    if (fd<0) return -1;
    FILE *fp=fdopen(fd,"w");
    if (!fp) { close(fd); unlink(path); return -1; }
    int ok=PEM_write_PUBKEY(fp,key)==1;
    fclose(fp);
    return ok ? 0 : -1;
}

static int sign_patch(EVP_PKEY *key, const jx_patch_manifest *manifest,
                      const uint8_t *patch, size_t patch_length,
                      uint8_t **signature, size_t *signature_length) {
    uint8_t wire[JX_PATCH_MANIFEST_WIRE_BYTES];
    if (jx_patch_manifest_write(wire,manifest)!=0) return -1;
    size_t total=sizeof wire+patch_length;
    uint8_t *message=malloc(total);
    if (!message) return -1;
    memcpy(message,wire,sizeof wire); memcpy(message+sizeof wire,patch,patch_length);
    EVP_MD_CTX *ctx=EVP_MD_CTX_new();
    if (!ctx) { free(message); return -1; }
    size_t need=0u;
    int ok=EVP_DigestSignInit(ctx,NULL,NULL,NULL,key)==1 &&
           EVP_DigestSign(ctx,NULL,&need,message,total)==1;
    uint8_t *sig=ok ? malloc(need) : NULL;
    if (!sig) ok=0;
    if (ok) ok=EVP_DigestSign(ctx,sig,&need,message,total)==1;
    EVP_MD_CTX_free(ctx); free(message);
    if (!ok) { free(sig); return -1; }
    *signature=sig; *signature_length=need; return 0;
}

static int write_all(int fd, const uint8_t *bytes, size_t length) {
    size_t at=0u;
    while (at<length) {
        ssize_t n=send(fd,bytes+at,length-at,MSG_NOSIGNAL);
        if (n<0) { if (errno==EINTR) continue; return -1; }
        if (n==0) return -1;
        at+=(size_t)n;
    }
    return 0;
}

static int child_request(const char *socket_path, const jx_patch_ipc_header *header,
                         const uint8_t *manifest, const uint8_t *signature, const uint8_t *patch) {
    int fd=socket(AF_UNIX,SOCK_STREAM,0); if (fd<0) return 20;
    struct sockaddr_un addr; memset(&addr,0,sizeof addr); addr.sun_family=AF_UNIX;
    strncpy(addr.sun_path,socket_path,sizeof addr.sun_path-1u);
    if (connect(fd,(struct sockaddr *)&addr,sizeof addr)!=0) { close(fd); return 21; }
    uint8_t raw[JX_PATCH_IPC_HEADER_BYTES]; jx_patch_ipc_header_write(raw,header);
    if (write_all(fd,raw,sizeof raw)!=0) { close(fd); return 22; }
    if (header->manifest_length && write_all(fd,manifest,header->manifest_length)!=0) { close(fd); return 23; }
    if (header->signature_length && write_all(fd,signature,header->signature_length)!=0) { close(fd); return 24; }
    if (header->patch_length && write_all(fd,patch,header->patch_length)!=0) { close(fd); return 25; }
    shutdown(fd,SHUT_WR);
    char response[256]; ssize_t n=recv(fd,response,sizeof response-1u,0); close(fd);
    if (n<=0) return 26; response[n]='\0';
    return strncmp(response,"OK",2u)==0 ? 0 : 27;
}

static int run_request(jx11_patch_service *service, const char *socket_path,
                       const jx_patch_ipc_header *header,
                       const uint8_t *manifest, const uint8_t *signature, const uint8_t *patch) {
    pid_t pid=fork();
    if (pid<0) return -1;
    if (pid==0) _exit(child_request(socket_path,header,manifest,signature,patch));
    struct pollfd p={jx11_patch_service_fd(service),POLLIN,0};
    if (poll(&p,1u,3000)<=0) { kill(pid,SIGKILL); waitpid(pid,NULL,0); return -2; }
    int service_rc=jx11_patch_service_process_one(service);
    int status=0; waitpid(pid,&status,0);
    return service_rc>0 && WIFEXITED(status) && WEXITSTATUS(status)==0 ? 0 : -3;
}

int main(int argc, char **argv) {
    if (argc!=2) return 2;
    size_t patch_length=0u; uint8_t *patch=read_file(argv[1],&patch_length); if (!patch) return 3;
    EVP_PKEY *key=make_key(); if (!key) { free(patch); return 4; }
    char pub_path[64]; if (write_public_key(key,pub_path)!=0) { EVP_PKEY_free(key); free(patch); return 5; }
    char socket_path[96]; snprintf(socket_path,sizeof socket_path,"/tmp/jx11-patch-e2e-%ld.sock",(long)getpid());
    uint8_t base[JX_PATCH_DIGEST_BYTES]; memset(base,0x11,sizeof base);
    jx11_live_patch manager; jx11_live_patch_init(&manager,1u,base,JX_PATCH_CAP_ALL);
    jx11_patch_service service;
    if (jx11_patch_service_open(&service,socket_path,pub_path,&manager)!=0) { unlink(pub_path); EVP_PKEY_free(key); free(patch); return 6; }
    jx11_patch_service_set_host(&service,&host);

    jx_patch_manifest manifest; memset(&manifest,0,sizeof manifest);
    manifest.version=JX_LIVE_PATCH_VERSION; manifest.protocol=JX_PATCH_PROTOCOL_SSH;
    manifest.generation=2u; manifest.base_generation=1u; manifest.issued_at=(uint64_t)time(NULL);
    manifest.expires_at=manifest.issued_at+60u; manifest.nonce=1u;
    manifest.capability_mask=JX_PATCH_CAP_NATIVE_CODE; manifest.patch_length=(uint32_t)patch_length;
    memcpy(manifest.base_digest,base,sizeof base); if (sha256(patch,patch_length,manifest.target_digest)!=0) return 7;
    uint8_t *signature=NULL; size_t signature_length=0u;
    if (sign_patch(key,&manifest,patch,patch_length,&signature,&signature_length)!=0) return 8;
    uint8_t manifest_wire[JX_PATCH_MANIFEST_WIRE_BYTES]; if (jx_patch_manifest_write(manifest_wire,&manifest)!=0) return 9;
    jx_patch_ipc_header push; memset(&push,0,sizeof push); push.magic=JX_PATCH_IPC_MAGIC; push.version=JX_PATCH_IPC_VERSION;
    push.operation=JX_PATCH_IPC_OP_PUSH; push.manifest_length=sizeof manifest_wire; push.signature_length=(uint32_t)signature_length;
    push.patch_length=(uint32_t)patch_length; push.request_id=1u;
    if (run_request(&service,socket_path,&push,manifest_wire,signature,patch)!=0) return 10;
    if (manager.active.generation!=2u || !manager.active.native_module || strcmp(manager.active.native_module->name,"sample-live-module")!=0) return 11;
    if (seen_background!=0x123456u || logs<1) return 12;

    jx_patch_ipc_header rollback; memset(&rollback,0,sizeof rollback); rollback.magic=JX_PATCH_IPC_MAGIC; rollback.version=JX_PATCH_IPC_VERSION;
    rollback.operation=JX_PATCH_IPC_OP_ROLLBACK; rollback.request_id=2u;
    if (run_request(&service,socket_path,&rollback,NULL,NULL,NULL)!=0) return 13;
    if (manager.active.generation!=1u || manager.active.native_module!=NULL) return 14;

    free(signature); free(patch); jx11_patch_service_close(&service); unlink(pub_path); EVP_PKEY_free(key);
    puts("jx11-patch-service-e2e: signed PUSH activated executable module; ROLLBACK restored core");
    return 0;
}
