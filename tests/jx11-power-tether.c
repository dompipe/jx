#define _POSIX_C_SOURCE 200809L
#include "../host/linux/jx11-power-tether.h"

#include <assert.h>
#include <fcntl.h>
#include <openssl/evp.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>
#include <unistd.h>

static int starts = 0;
static int stops = 0;
static int probes = 0;
static int start_ok(void *state, void *context) { (void)state; (void)context; ++starts; return 0; }
static void stop_ok(void *state, void *context) { (void)state; (void)context; ++stops; }
static int call_ok(void *state, void *context, uint32_t event, void *payload) {
    (void)state; (void)context; (void)payload; return (int)event;
}
static int probe_ok(void *state, void *context) { (void)state; (void)context; ++probes; return 0; }

static void fill(uint8_t *p, uint8_t v) { memset(p, v, JX_HOT_SWAP_DIGEST_BYTES); }

static void sha256(const uint8_t *bytes, size_t length, uint8_t out[32]) {
    unsigned int n = 0u;
    assert(EVP_Digest(bytes, length, out, &n, EVP_sha256(), NULL) == 1);
    assert(n == 32u);
}

static void write_file(const char *path, const uint8_t *bytes, size_t length) {
    int fd = open(path, O_CREAT | O_TRUNC | O_WRONLY, 0755);
    assert(fd >= 0);
    assert(write(fd, bytes, length) == (ssize_t)length);
    assert(close(fd) == 0);
}

static size_t read_file(const char *path, uint8_t *out, size_t cap) {
    int fd = open(path, O_RDONLY);
    assert(fd >= 0);
    ssize_t n = read(fd, out, cap);
    assert(n >= 0);
    close(fd);
    return (size_t)n;
}

int main(void) {
    const uint8_t old_elf[] = {0x7f,'E','L','F',2,1,1,0,0,0,0,0,0,0,0,0,'O','L','D'};
    const uint8_t new_elf[] = {0x7f,'E','L','F',2,1,1,0,0,0,0,0,0,0,0,0,'N','E','W','2'};
    char dir[] = "/tmp/jx11-tether-XXXXXX";
    assert(mkdtemp(dir) != NULL);
    char path[256], previous[272];
    snprintf(path, sizeof path, "%s/jx11", dir);
    snprintf(previous, sizeof previous, "%s.previous", path);
    write_file(path, old_elf, sizeof old_elf);

    jx_hot_swap_program oldp = {0}, newp = {0};
    oldp.version = newp.version = JX_HOT_SWAP_VERSION;
    oldp.generation = 1u; newp.generation = 2u;
    fill(oldp.data_source_digest, 0x11u); fill(newp.data_source_digest, 0x11u);
    fill(oldp.state_abi_digest, 0x22u); fill(newp.state_abi_digest, 0x22u);
    oldp.start = start_ok; oldp.stop = stop_ok; oldp.call = call_ok;
    newp.start = start_ok; newp.stop = stop_ok; newp.call = call_ok; newp.power_probe = probe_ok;

    jx_hot_swap_gate gate;
    jx_hot_swap_init(&gate, &oldp, NULL);
    assert(jx_hot_swap_prepare(&gate, &newp) == JX_HOT_SWAP_OK);
    assert(starts == 0); /* candidate is dormant until the power button */

    const uint32_t old_ep = 0x80000001u, new_ep = 0x80000002u;
    jx_channel_bus bus;
    jx_channel_bus_init(&bus, old_ep);
    assert(jx_channel_bus_add_endpoint(&bus, old_ep, NULL, NULL) == 0);
    assert(jx_channel_bus_add_endpoint(&bus, new_ep, NULL, NULL) == 0);

    uint8_t digest[32]; sha256(new_elf, sizeof new_elf, digest);
    jx11_exe_tether_install install = {path, new_elf, sizeof new_elf, digest};
    jx11_power_tether_status status;
    assert(jx11_power_tether_cutover(&gate, &bus, old_ep, new_ep, &install, &status) == JX11_POWER_TETHER_OK);
    assert(status.live_takeover && status.disk_persisted);
    assert(gate.active.generation == 2u && jx_hot_swap_takeover_proven(&gate));
    assert(bus.active_program_endpoint == new_ep);
    assert(starts == 1 && probes == 1 && stops == 1);

    uint8_t buf[64];
    size_t n = read_file(path, buf, sizeof buf);
    assert(n == sizeof new_elf && memcmp(buf, new_elf, n) == 0);
    n = read_file(previous, buf, sizeof buf);
    assert(n == sizeof old_elf && memcmp(buf, old_elf, n) == 0);

    unlink(path); unlink(previous); rmdir(dir);
    puts("jx11-power-tether: takeover proven -> installed exe atomically replaced; previous kept");
    return 0;
}
