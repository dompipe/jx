#define _POSIX_C_SOURCE 200809L
#include "jx-idle-shared.h"
#include <errno.h>
#include <fcntl.h>
#include <linux/futex.h>
#include <stdatomic.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/mman.h>
#include <sys/stat.h>
#include <sys/syscall.h>
#include <unistd.h>

static int futex_wake_all(uint32_t *word) {
    return (int)syscall(SYS_futex, word, FUTEX_WAKE, 0x7fffffff, NULL, NULL, 0);
}

static int map_page(jx_idle_shared *shared, int fd) {
    void *mapping = mmap(NULL, sizeof(jx_idle_shared_page), PROT_READ | PROT_WRITE,
                         MAP_SHARED, fd, 0);
    if (mapping == MAP_FAILED) return -1;
    shared->fd = fd;
    shared->page = (jx_idle_shared_page *)mapping;
    return 0;
}

int jx_idle_shared_host_open(jx_idle_shared *shared) {
    if (!shared) return -1;
    memset(shared, 0, sizeof *shared);
    shared->fd = -1;
    snprintf(shared->name, sizeof shared->name, "/jx-idle-%ld", (long)getpid());
    int fd = shm_open(shared->name, O_CREAT | O_EXCL | O_RDWR, 0600);
    if (fd < 0) return -2;
    if (ftruncate(fd, (off_t)sizeof(jx_idle_shared_page)) != 0) {
        close(fd); shm_unlink(shared->name); return -3;
    }
    if (map_page(shared, fd) != 0) {
        close(fd); shm_unlink(shared->name); return -4;
    }
    memset(shared->page, 0, sizeof *shared->page);
    shared->page->magic = JX_IDLE_SHARED_MAGIC;
    shared->page->version = JX_IDLE_SHARED_VERSION;
    if (setenv(JX_IDLE_SHARED_ENV, shared->name, 1) != 0) {
        jx_idle_shared_close(shared, 1); return -5;
    }
    return 0;
}

int jx_idle_shared_child_open(jx_idle_shared *shared) {
    if (!shared) return -1;
    memset(shared, 0, sizeof *shared);
    shared->fd = -1;
    const char *name = getenv(JX_IDLE_SHARED_ENV);
    if (!name || !*name || strlen(name) > JX_IDLE_SHARED_NAME_MAX) return -2;
    memcpy(shared->name, name, strlen(name) + 1u);
    int fd = shm_open(name, O_RDWR, 0);
    if (fd < 0) return -3;
    if (map_page(shared, fd) != 0) { close(fd); return -4; }
    if (shared->page->magic != JX_IDLE_SHARED_MAGIC ||
        shared->page->version != JX_IDLE_SHARED_VERSION) {
        jx_idle_shared_close(shared, 0);
        return -5;
    }
    return 0;
}

void jx_idle_shared_close(jx_idle_shared *shared, int owner) {
    if (!shared) return;
    if (shared->page) munmap(shared->page, sizeof(jx_idle_shared_page));
    if (shared->fd >= 0) close(shared->fd);
    if (owner && shared->name[0]) shm_unlink(shared->name);
    memset(shared, 0, sizeof *shared);
    shared->fd = -1;
}

int jx_idle_shared_set_program_count(jx_idle_shared *shared, uint32_t count) {
    if (!shared || !shared->page) return -1;
    atomic_store_explicit((_Atomic uint32_t *)&shared->page->program_count,
                          count, memory_order_release);
    return 0;
}

int jx_idle_shared_broadcast(jx_idle_shared *shared,
                             uint64_t epoch,
                             uint64_t monotonic_ms) {
    if (!shared || !shared->page) return -1;
    atomic_store_explicit((_Atomic uint64_t *)&shared->page->monotonic_ms,
                          monotonic_ms, memory_order_relaxed);
    atomic_store_explicit((_Atomic uint64_t *)&shared->page->epoch,
                          epoch, memory_order_release);
    atomic_fetch_add_explicit((_Atomic uint32_t *)&shared->page->futex_word,
                              1u, memory_order_release);
    (void)futex_wake_all(&shared->page->futex_word);
    return 0;
}

int jx_idle_shared_snapshot(const jx_idle_shared *shared,
                            uint64_t *epoch,
                            uint64_t *monotonic_ms,
                            uint32_t *program_count) {
    if (!shared || !shared->page) return -1;
    if (epoch) *epoch = atomic_load_explicit((_Atomic uint64_t *)&shared->page->epoch,
                                              memory_order_acquire);
    if (monotonic_ms) *monotonic_ms = atomic_load_explicit((_Atomic uint64_t *)&shared->page->monotonic_ms,
                                                            memory_order_relaxed);
    if (program_count) *program_count = atomic_load_explicit((_Atomic uint32_t *)&shared->page->program_count,
                                                              memory_order_acquire);
    return 0;
}
