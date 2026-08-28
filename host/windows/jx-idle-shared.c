#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#include <stdio.h>
#include <string.h>
#include "jx-idle-shared.h"

static void jx_win32_idle_shared_zero(jx_win32_idle_shared *shared) {
    if (!shared) return;
    memset(shared, 0, sizeof *shared);
}

static int jx_win32_idle_shared_map(jx_win32_idle_shared *shared, HANDLE mapping) {
    void *view;
    if (!shared || !mapping) return -1;
    view = MapViewOfFile(mapping, FILE_MAP_ALL_ACCESS, 0u, 0u,
                         sizeof(jx_win32_idle_shared_page));
    if (!view) return -1;
    shared->mapping = mapping;
    shared->page = (jx_win32_idle_shared_page *)view;
    return 0;
}

int jx_win32_idle_shared_host_open(jx_win32_idle_shared *shared) {
    HANDLE mapping;
    DWORD pid;
    ULONGLONG tick;
    if (!shared) return -1;
    jx_win32_idle_shared_zero(shared);
    pid = GetCurrentProcessId();
    tick = GetTickCount64();
    _snwprintf_s(shared->name, JX_WIN32_IDLE_SHARED_NAME_MAX + 1u, _TRUNCATE,
                 L"Local\\JXIdle-%lu-%llu",
                 (unsigned long)pid, (unsigned long long)tick);
    mapping = CreateFileMappingW(INVALID_HANDLE_VALUE, NULL, PAGE_READWRITE,
                                 0u, (DWORD)sizeof(jx_win32_idle_shared_page),
                                 shared->name);
    if (!mapping) return -1;
    if (jx_win32_idle_shared_map(shared, mapping) != 0) {
        CloseHandle(mapping);
        jx_win32_idle_shared_zero(shared);
        return -1;
    }
    memset(shared->page, 0, sizeof *shared->page);
    shared->page->magic = JX_WIN32_IDLE_SHARED_MAGIC;
    shared->page->version = JX_WIN32_IDLE_SHARED_VERSION;
    MemoryBarrier();
    if (!SetEnvironmentVariableW(JX_WIN32_IDLE_SHARED_ENV, shared->name)) {
        jx_win32_idle_shared_close(shared, 1);
        return -1;
    }
    return 0;
}

int jx_win32_idle_shared_child_open(jx_win32_idle_shared *shared) {
    HANDLE mapping;
    DWORD n;
    if (!shared) return -1;
    jx_win32_idle_shared_zero(shared);
    n = GetEnvironmentVariableW(JX_WIN32_IDLE_SHARED_ENV, shared->name,
                                JX_WIN32_IDLE_SHARED_NAME_MAX + 1u);
    if (n == 0u || n > JX_WIN32_IDLE_SHARED_NAME_MAX) return -1;
    mapping = OpenFileMappingW(FILE_MAP_ALL_ACCESS, FALSE, shared->name);
    if (!mapping) return -1;
    if (jx_win32_idle_shared_map(shared, mapping) != 0) {
        CloseHandle(mapping);
        jx_win32_idle_shared_zero(shared);
        return -1;
    }
    MemoryBarrier();
    if (shared->page->magic != JX_WIN32_IDLE_SHARED_MAGIC ||
        shared->page->version != JX_WIN32_IDLE_SHARED_VERSION) {
        jx_win32_idle_shared_close(shared, 0);
        return -1;
    }
    return 0;
}

void jx_win32_idle_shared_close(jx_win32_idle_shared *shared, int owner) {
    if (!shared) return;
    if (shared->page) UnmapViewOfFile(shared->page);
    if (shared->mapping) CloseHandle(shared->mapping);
    if (owner) SetEnvironmentVariableW(JX_WIN32_IDLE_SHARED_ENV, NULL);
    jx_win32_idle_shared_zero(shared);
}

int jx_win32_idle_shared_set_program_count(jx_win32_idle_shared *shared, uint32_t count) {
    if (!shared || !shared->page || count > 0x7fffffffu) return -1;
    InterlockedExchange(&shared->page->program_count, (LONG)count);
    return 0;
}

int jx_win32_idle_shared_broadcast(jx_win32_idle_shared *shared,
                                   uint64_t epoch,
                                   uint64_t monotonic_ms) {
    if (!shared || !shared->page || epoch > 0x7fffffffffffffffULL ||
        monotonic_ms > 0x7fffffffffffffffULL) return -1;
    InterlockedExchange64(&shared->page->monotonic_ms, (LONG64)monotonic_ms);
    InterlockedExchange64(&shared->page->epoch, (LONG64)epoch);
    InterlockedIncrement(&shared->page->wake_word);
    WakeByAddressAll((PVOID)&shared->page->wake_word);
    return 0;
}

int jx_win32_idle_shared_snapshot(const jx_win32_idle_shared *shared,
                                  uint64_t *epoch,
                                  uint64_t *monotonic_ms,
                                  uint32_t *program_count) {
    LONG64 e;
    LONG64 ms;
    LONG count;
    if (!shared || !shared->page) return -1;
    e = InterlockedCompareExchange64((volatile LONG64 *)&shared->page->epoch, 0, 0);
    ms = InterlockedCompareExchange64((volatile LONG64 *)&shared->page->monotonic_ms, 0, 0);
    count = InterlockedCompareExchange((volatile LONG *)&shared->page->program_count, 0, 0);
    if (epoch) *epoch = (uint64_t)e;
    if (monotonic_ms) *monotonic_ms = (uint64_t)ms;
    if (program_count) *program_count = (uint32_t)count;
    return 0;
}

uint32_t jx_win32_idle_shared_wake_word(const jx_win32_idle_shared *shared) {
    if (!shared || !shared->page) return 0u;
    return (uint32_t)InterlockedCompareExchange((volatile LONG *)&shared->page->wake_word, 0, 0);
}

int jx_win32_idle_shared_wait(jx_win32_idle_shared *shared,
                              uint32_t observed_wake_word,
                              uint64_t last_seen_epoch,
                              uint64_t *new_epoch,
                              uint64_t *monotonic_ms) {
    LONG observed;
    uint64_t epoch = 0u;
    uint64_t ms = 0u;
    if (!shared || !shared->page) return -1;
    if (jx_win32_idle_shared_snapshot(shared, &epoch, &ms, NULL) != 0) return -1;
    if (epoch <= last_seen_epoch) {
        observed = (LONG)observed_wake_word;
        if (!WaitOnAddress((volatile VOID *)&shared->page->wake_word,
                           &observed, sizeof observed, INFINITE)) return -1;
        if (jx_win32_idle_shared_snapshot(shared, &epoch, &ms, NULL) != 0) return -1;
    }
    if (epoch <= last_seen_epoch) return 0;
    if (new_epoch) *new_epoch = epoch;
    if (monotonic_ms) *monotonic_ms = ms;
    return 1;
}
