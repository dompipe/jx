#include "jx-host-trace.h"
#include <string.h>

void jx_host_trace_init(jx_host_trace *trace, uint8_t host_kind) {
    if (!trace) return;
    memset(trace, 0, sizeof *trace);
    trace->host_kind = host_kind;
}

int jx_host_trace_emit(jx_host_trace *trace,
                       uint16_t kind,
                       uint64_t generation,
                       uint64_t subject,
                       uint64_t value) {
    if (!trace || trace->count >= JX_HOST_TRACE_MAX_RECORDS) return -1;
    jx_host_trace_record *r = &trace->records[trace->count];
    memset(r, 0, sizeof *r);
    r->version = JX_HOST_TRACE_VERSION;
    r->host_kind = trace->host_kind;
    r->kind = kind;
    r->sequence = (uint64_t)trace->count;
    r->generation = generation;
    r->subject = subject;
    r->value = value;
    trace->count++;
    return 0;
}

static int semantic_equal(const jx_host_trace_record *a,
                          const jx_host_trace_record *b) {
    if (!a || !b) return 0;
    return a->version == b->version &&
           a->kind == b->kind &&
           a->sequence == b->sequence &&
           a->generation == b->generation &&
           a->subject == b->subject &&
           a->value == b->value;
}

int jx_host_trace_compare(const jx_host_trace *reference,
                          const jx_host_trace *candidate,
                          jx_host_trace_compare_result *result) {
    if (!reference || !candidate || !result) return -1;
    memset(result, 0, sizeof *result);
    size_t n = reference->count < candidate->count ? reference->count : candidate->count;
    for (size_t i = 0; i < n; ++i) {
        if (!semantic_equal(&reference->records[i], &candidate->records[i])) {
            result->matched = i;
            result->mismatch_index = i;
            result->equal = 0;
            return 0;
        }
    }
    result->matched = n;
    if (reference->count != candidate->count) {
        result->mismatch_index = n;
        result->equal = 0;
        return 0;
    }
    result->mismatch_index = n;
    result->equal = 1;
    return 0;
}
