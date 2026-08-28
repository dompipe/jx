#ifndef JX_IDLE_SHARED_H
#define JX_IDLE_SHARED_H

#include <stdint.h>

#define JX_IDLE_SHARED_VERSION 1u
#define JX_IDLE_SHARED_ENV "JX_IDLE_SHM"
#define JX_IDLE_SHARED_NAME_MAX 95u
#define JX_IDLE_SHARED_MAGIC 0x4a584944u /* JXID */

typedef struct {
    uint32_t magic;
    uint16_t version;
    uint16_t reserved;
    uint32_t futex_word;
    uint32_t program_count;
    uint64_t epoch;
    uint64_t monotonic_ms;
} jx_idle_shared_page;

typedef struct {
    int fd;
    char name[JX_IDLE_SHARED_NAME_MAX + 1u];
    jx_idle_shared_page *page;
} jx_idle_shared;

int jx_idle_shared_host_open(jx_idle_shared *shared);
int jx_idle_shared_child_open(jx_idle_shared *shared);
void jx_idle_shared_close(jx_idle_shared *shared, int owner);
int jx_idle_shared_set_program_count(jx_idle_shared *shared, uint32_t count);
int jx_idle_shared_broadcast(jx_idle_shared *shared,
                             uint64_t epoch,
                             uint64_t monotonic_ms);
int jx_idle_shared_snapshot(const jx_idle_shared *shared,
                            uint64_t *epoch,
                            uint64_t *monotonic_ms,
                            uint32_t *program_count);

#endif
