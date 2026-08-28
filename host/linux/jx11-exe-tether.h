#ifndef JX11_EXE_TETHER_H
#define JX11_EXE_TETHER_H

#include <stddef.h>
#include <stdint.h>

#define JX11_EXE_TETHER_DIGEST_BYTES 32u

typedef enum {
    JX11_EXE_TETHER_OK = 0,
    JX11_EXE_TETHER_ERR_ARGUMENT = -1,
    JX11_EXE_TETHER_ERR_DIGEST = -2,
    JX11_EXE_TETHER_ERR_PATH = -3,
    JX11_EXE_TETHER_ERR_IO = -4,
    JX11_EXE_TETHER_ERR_RENAME = -5,
    JX11_EXE_TETHER_ERR_SYNC = -6,
    JX11_EXE_TETHER_ERR_FORMAT = -7
} jx11_exe_tether_result;

typedef struct {
    const char *install_path;
    const uint8_t *replacement;
    size_t replacement_length;
    const uint8_t *expected_sha256;
} jx11_exe_tether_install;

int jx11_exe_tether_persist(const jx11_exe_tether_install *install);

#endif
