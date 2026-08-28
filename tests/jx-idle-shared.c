#define _POSIX_C_SOURCE 200809L
#include "../host/linux/jx-idle-shared.h"
#include <assert.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <sys/wait.h>
#include <unistd.h>

int main(void) {
    jx_idle_shared host;
    assert(jx_idle_shared_host_open(&host) == 0);
    assert(jx_idle_shared_set_program_count(&host, 1u) == 0);

    int ready[2];
    assert(pipe(ready) == 0);
    pid_t pid = fork();
    assert(pid >= 0);

    if (pid == 0) {
        close(ready[0]);
        jx_idle_shared child;
        if (jx_idle_shared_child_open(&child) != 0) _exit(20);

        uint64_t initial_epoch = 0u;
        if (jx_idle_shared_snapshot(&child, &initial_epoch, NULL, NULL) != 0) _exit(21);
        uint32_t wake_word = jx_idle_shared_wake_word(&child);
        if (write(ready[1], "R", 1u) != 1) _exit(22);
        close(ready[1]);

        uint64_t new_epoch = 0u, pulse_ms = 0u;
        int rc = jx_idle_shared_wait(&child, wake_word, initial_epoch,
                                     &new_epoch, &pulse_ms);
        jx_idle_shared_close(&child, 0);
        if (rc != 1 || new_epoch != 1u || pulse_ms != 500u) _exit(23);
        _exit(0);
    }

    close(ready[1]);
    char marker = 0;
    assert(read(ready[0], &marker, 1u) == 1);
    close(ready[0]);
    assert(marker == 'R');

    assert(jx_idle_shared_broadcast(&host, 1u, 500u) == 0);

    int status = 0;
    assert(waitpid(pid, &status, 0) == pid);
    assert(WIFEXITED(status));
    assert(WEXITSTATUS(status) == 0);

    uint64_t epoch = 0u, pulse_ms = 0u;
    uint32_t count = 0u;
    assert(jx_idle_shared_snapshot(&host, &epoch, &pulse_ms, &count) == 0);
    assert(epoch == 1u);
    assert(pulse_ms == 500u);
    assert(count == 1u);

    jx_idle_shared_close(&host, 1);
    puts("jx-idle-shared: one futex doorbell wakes sleeping child on permission epoch");
    return 0;
}
