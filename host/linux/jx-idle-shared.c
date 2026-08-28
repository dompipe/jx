#define _GNU_SOURCE
#define _POSIX_C_SOURCE 200809L
#include "jx-idle-shared.h"
#include <errno.h>
#include <fcntl.h>
#include <linux/futex.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/mman.h>
#include <sys/stat.h>
#include <sys/syscall.h>
#include <unistd.h>

static int futex_wake_all(_Atomic uint32_t *word) {
    return (int)syscall(SYS_futex, (uint32_t *)word, FUTEX_WAKE, 0x7fffffff, NULL, NULL, 0);
}

static int futex_wait_word(_Atomic uint32_t *word, uint32_t expected) {
    int rc = (int)syscall(SYS_futex, (uint32_t *)word, FUTEX_WAIT, expected, NULL, NULL, 0);
    if (rc == 0) return 0;
    if (errno == EAGAIN || errno == EINTR) return 0;
    return -1;
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
    atomic_store_explicit(&shared->page->program_count, count, memory_order_release);
    return 0;
}

int jx_idle_shared_broadcast(jx_idle_shared *shared,
                             uint64_t epoch,
                             uint64_t monotonic_ms) {
    if (!shared || !shared->page) return -1;
    atomic_store_explicit(&shared->page->monotonic_ms, monotonic_ms, memory_order_relaxed);
    atomic_store_explicit(&shared->page->epoch, epoch, memory_order_release);
    atomic_fetch_add_explicit(&shared->page->futex_word, 1u, memory_order_release);
    (void)futex_wake_all(&shared->page->futex_word);
    return 0;
}

int jx_idle_shared_snapshot(const jx_idle_shared *shared,
                            uint64_t *epoch,
                            uint64_t *monotonic_ms,
                            uint32_t *program_count) {
    if (!shared || !shared->page) return -1;
    if (epoch) *epoch = atomic_load_explicit(&shared->page->epoch, memory_order_acquire);
    if (monotonic_ms) *monotonic_ms = atomic_load_explicit(&shared->page->monotonic_ms,
                                                            memory_order_relaxed);
    if (program_count) *program_count = atomic_load_explicit(&shared->page->program_count,
                                                              memory_order_acquire);
    return 0;
}

uint32_t jx_idle_shared_wake_word(const jx_idle_shared *shared) {
    if (!shared || !shared->page) return 0u;
    return atomic_load_explicit(&shared->page->futex_word, memory_order_acquire);
}

int jx_idle_shared_wait(jx_idle_shared *shared,
                        uint32_t observed_wake_word,
                        uint64_t last_seen_epoch,
                        uint64_t *new_epoch,
                        uint64_t *monotonic_ms) {
    if (!shared || !shared->page) return -1;

    uint64_t epoch = atomic_load_explicit(&shared->page->epoch, memory_order_acquire);
    if (epoch == last_seen_epoch) {
        uint32_t current = atomic_load_explicit(&shared->page->futex_word, memory_order_acquire);
        if (current == observed_wake_word && futex_wait_word(&shared->page->futex_word, observed_wake_word) != 0)
            return -2;
        epoch = atomic_load_explicit(&shared->page->epoch, memory_order_acquire);
    }

    if (epoch == last_seen_epoch) return 0;
    if (new_epoch) *new_epoch = epoch;
    if (monotonic_ms) *monotonic_ms = atomic_load_explicit(&shared->page->monotonic_ms,
                                                            memory_order_relaxed);
    return 1;
}
