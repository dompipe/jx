#ifndef JX_HOST_TRACE_H
#define JX_HOST_TRACE_H

#include <stddef.h>
#include <stdint.h>

#define JX_HOST_TRACE_VERSION 1u
#define JX_HOST_TRACE_MAX_RECORDS 1024u

typedef enum {
    JX_HOST_LINUX_X11 = 1,
    JX_HOST_WINDOWS_WIN32 = 2,
    JX_HOST_EMULATED = 3
} jx_host_kind;

typedef enum {
    JX_TRACE_PROGRAM_START = 1,
    JX_TRACE_PROGRAM_STOP = 2,
    JX_TRACE_INPUT = 3,
    JX_TRACE_CHANNEL_IN = 4,
    JX_TRACE_CHANNEL_OUT = 5,
    JX_TRACE_BAG_REVISION = 6,
    JX_TRACE_RENDER_INVALIDATE = 7,
    JX_TRACE_POWER_STATE = 8,
    JX_TRACE_GENERATION = 9
} jx_host_trace_kind;

typedef struct {
    uint8_t version;
    uint8_t host_kind;
    uint16_t kind;
    uint64_t sequence;
    uint64_t generation;
    uint64_t subject;
    uint64_t value;
} jx_host_trace_record;

typedef struct {
    jx_host_trace_record records[JX_HOST_TRACE_MAX_RECORDS];
    size_t count;
    uint8_t host_kind;
} jx_host_trace;

typedef struct {
    size_t matched;
    size_t mismatch_index;
    int equal;
} jx_host_trace_compare_result;

void jx_host_trace_init(jx_host_trace *trace, uint8_t host_kind);
int jx_host_trace_emit(jx_host_trace *trace,
                       uint16_t kind,
                       uint64_t generation,
                       uint64_t subject,
                       uint64_t value);
int jx_host_trace_compare(const jx_host_trace *reference,
                          const jx_host_trace *candidate,
                          jx_host_trace_compare_result *result);

#endif
