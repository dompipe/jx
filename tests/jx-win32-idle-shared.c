#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#include <stdint.h>
#include <stdio.h>
#include "../host/common/jx-idle-bus.h"
#include "../host/windows/jx-idle-shared.h"

typedef struct {
    jx_win32_idle_shared child;
    uint32_t observed;
    uint64_t last_epoch;
    uint64_t epoch;
    uint64_t ms;
    int result;
} wait_ctx;

static int fail_stage(int code, const char *stage) {
    fprintf(stderr, "FAIL Win32 idle bus stage=%s code=%d winerr=%lu\n",
            stage, code, (unsigned long)GetLastError());
    return code;
}

static DWORD WINAPI wait_thread(LPVOID p) {
    wait_ctx *ctx = (wait_ctx *)p;
    ctx->result = jx_win32_idle_shared_wait(&ctx->child, ctx->observed,
                                            ctx->last_epoch,
                                            &ctx->epoch, &ctx->ms);
    return 0u;
}

static int wait_one_epoch(wait_ctx *ctx, jx_win32_idle_shared *host,
                          uint64_t epoch, uint64_t ms, int base_code) {
    HANDLE thread;
    ctx->observed = jx_win32_idle_shared_wake_word(&ctx->child);
    ctx->last_epoch = epoch - 1u;
    ctx->epoch = 0u;
    ctx->ms = 0u;
    ctx->result = 0;
    thread = CreateThread(NULL, 0u, wait_thread, ctx, 0u, NULL);
    if (!thread) return fail_stage(base_code, "thread");
    Sleep(20u);
    if (jx_win32_idle_shared_broadcast(host, epoch, ms) != 0) {
        CloseHandle(thread);
        return fail_stage(base_code + 1, "broadcast");
    }
    if (WaitForSingleObject(thread, 2000u) != WAIT_OBJECT_0) {
        CloseHandle(thread);
        return fail_stage(base_code + 2, "wait-thread");
    }
    CloseHandle(thread);
    if (ctx->result != 1 || ctx->epoch != epoch || ctx->ms != ms)
        return fail_stage(base_code + 3, "wake-result");
    return 0;
}

int main(void) {
    jx_win32_idle_shared host;
    wait_ctx ctx;
    uint8_t tick[JX_IDLE_CALL_BYTES];
    uint64_t epoch = 0u, ms = 0u;
    uint32_t count = 0u;
    int rc;

    if (jx_idle_bus_encode(tick) != 0) return fail_stage(1, "encode");
    if (tick[0] != 0x7fu || tick[1] != 0x00u || tick[2] != 0x01u) return fail_stage(2, "abi");
    if (jx_win32_idle_shared_host_open(&host) != 0) return fail_stage(3, "host-open");
    if (jx_win32_idle_shared_set_program_count(&host, 7u) != 0) return fail_stage(4, "program-count");

    ZeroMemory(&ctx, sizeof ctx);
    if (jx_win32_idle_shared_child_open(&ctx.child) != 0) return fail_stage(5, "child-open");

    rc = wait_one_epoch(&ctx, &host, 1u, 250u, 10);
    if (rc != 0) return rc;
    rc = wait_one_epoch(&ctx, &host, 2u, 500u, 20);
    if (rc != 0) return rc;

    if (jx_win32_idle_shared_snapshot(&ctx.child, &epoch, &ms, &count) != 0)
        return fail_stage(30, "snapshot");
    if (epoch != 2u || ms != 500u || count != 7u)
        return fail_stage(31, "snapshot-values");

    jx_win32_idle_shared_close(&ctx.child, 0);
    jx_win32_idle_shared_close(&host, 1);
    puts("PASS Win32 named-event doorbell preserves JX 7F0001 ABI across consecutive epochs");
    return 0;
}
