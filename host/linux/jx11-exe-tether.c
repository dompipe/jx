#define _POSIX_C_SOURCE 200809L
#include "jx11-exe-tether.h"

#include <errno.h>
#include <fcntl.h>
#include <limits.h>
#include <openssl/evp.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>
#include <sys/types.h>
#include <unistd.h>

#ifndef PATH_MAX
#define PATH_MAX 4096
#endif

static int sha256_bytes(const uint8_t *bytes, size_t length,
                        uint8_t out[JX11_EXE_TETHER_DIGEST_BYTES]) {
    unsigned int n = 0u;
    if (!bytes || !out) return -1;
    if (EVP_Digest(bytes, length, out, &n, EVP_sha256(), NULL) != 1) return -1;
    return n == JX11_EXE_TETHER_DIGEST_BYTES ? 0 : -1;
}

static int digest_equal(const uint8_t *a, const uint8_t *b) {
    uint8_t diff = 0u;
    for (size_t i = 0; i < JX11_EXE_TETHER_DIGEST_BYTES; ++i) diff |= (uint8_t)(a[i] ^ b[i]);
    return diff == 0u;
}

static int is_elf(const uint8_t *bytes, size_t length) {
    return length >= 16u && bytes[0] == 0x7fu && bytes[1] == 'E' && bytes[2] == 'L' && bytes[3] == 'F';
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

static int split_dir(const char *path, char dir[PATH_MAX]) {
    const char *slash = strrchr(path, '/');
    if (!slash) { strcpy(dir, "."); return 0; }
    size_t n = (size_t)(slash - path);
    if (n == 0u) n = 1u;
    if (n >= PATH_MAX) return -1;
    memcpy(dir, path, n);
    dir[n] = '\0';
    return 0;
}

int jx11_exe_tether_persist(const jx11_exe_tether_install *install) {
    if (!install || !install->install_path || !*install->install_path ||
        !install->replacement || install->replacement_length == 0u ||
        !install->expected_sha256) return JX11_EXE_TETHER_ERR_ARGUMENT;
    if (!is_elf(install->replacement, install->replacement_length)) return JX11_EXE_TETHER_ERR_FORMAT;

    uint8_t actual[JX11_EXE_TETHER_DIGEST_BYTES];
    if (sha256_bytes(install->replacement, install->replacement_length, actual) != 0 ||
        !digest_equal(actual, install->expected_sha256)) return JX11_EXE_TETHER_ERR_DIGEST;

    char temp_path[PATH_MAX];
    char previous_path[PATH_MAX];
    char dir_path[PATH_MAX];
    if (snprintf(temp_path, sizeof temp_path, "%s.next-XXXXXX", install->install_path) >= (int)sizeof temp_path ||
        snprintf(previous_path, sizeof previous_path, "%s.previous", install->install_path) >= (int)sizeof previous_path ||
        split_dir(install->install_path, dir_path) != 0) return JX11_EXE_TETHER_ERR_PATH;

    struct stat st;
    mode_t mode = 0755;
    int had_current = stat(install->install_path, &st) == 0;
    if (had_current) mode = st.st_mode & 07777;

    int fd = mkstemp(temp_path);
    if (fd < 0) return JX11_EXE_TETHER_ERR_IO;
    if (fchmod(fd, mode) != 0 || write_all(fd, install->replacement, install->replacement_length) != 0 || fsync(fd) != 0) {
        close(fd); unlink(temp_path); return JX11_EXE_TETHER_ERR_IO;
    }
    if (close(fd) != 0) { unlink(temp_path); return JX11_EXE_TETHER_ERR_IO; }

    if (had_current) {
        (void)unlink(previous_path);
        if (rename(install->install_path, previous_path) != 0) {
            unlink(temp_path); return JX11_EXE_TETHER_ERR_RENAME;
        }
    }

    if (rename(temp_path, install->install_path) != 0) {
        if (had_current) (void)rename(previous_path, install->install_path);
        unlink(temp_path); return JX11_EXE_TETHER_ERR_RENAME;
    }

    int dirfd = open(dir_path, O_RDONLY | O_DIRECTORY);
    if (dirfd < 0) return JX11_EXE_TETHER_ERR_SYNC;
    int sync_rc = fsync(dirfd);
    close(dirfd);
    return sync_rc == 0 ? JX11_EXE_TETHER_OK : JX11_EXE_TETHER_ERR_SYNC;
}
