#ifndef JX_WIN32_IDLE_SHARED_H
#define JX_WIN32_IDLE_SHARED_H

#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#include <stdint.h>

#define JX_WIN32_IDLE_SHARED_VERSION 1u
#define JX_WIN32_IDLE_SHARED_MAGIC 0x4a584944u
#define JX_WIN32_IDLE_SHARED_ENV L"JX_IDLE_SHM"
#define JX_WIN32_IDLE_SHARED_NAME_MAX 96u

typedef struct {
    uint32_t magic;
    uint16_t version;
    uint16_t reserved;
    volatile LONG wake_word;
    volatile LONG program_count;
    volatile LONG64 epoch;
    volatile LONG64 monotonic_ms;
} jx_win32_idle_shared_page;

typedef struct {
    HANDLE mapping;
    jx_win32_idle_shared_page *page;
    wchar_t name[JX_WIN32_IDLE_SHARED_NAME_MAX + 1u];
} jx_win32_idle_shared;

int jx_win32_idle_shared_host_open(jx_win32_idle_shared *shared);
int jx_win32_idle_shared_child_open(jx_win32_idle_shared *shared);
void jx_win32_idle_shared_close(jx_win32_idle_shared *shared, int owner);
int jx_win32_idle_shared_set_program_count(jx_win32_idle_shared *shared, uint32_t count);
int jx_win32_idle_shared_broadcast(jx_win32_idle_shared *shared,
                                   uint64_t epoch,
                                   uint64_t monotonic_ms);
int jx_win32_idle_shared_snapshot(const jx_win32_idle_shared *shared,
                                  uint64_t *epoch,
                                  uint64_t *monotonic_ms,
                                  uint32_t *program_count);
uint32_t jx_win32_idle_shared_wake_word(const jx_win32_idle_shared *shared);
int jx_win32_idle_shared_wait(jx_win32_idle_shared *shared,
                              uint32_t observed_wake_word,
                              uint64_t last_seen_epoch,
                              uint64_t *new_epoch,
                              uint64_t *monotonic_ms);

#endif
