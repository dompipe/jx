#ifndef JX_IDLE_COLLECT_H
#define JX_IDLE_COLLECT_H

#include <stddef.h>
#include <stdint.h>
#include <stdatomic.h>

#define JX_IDLE_COLLECT_VERSION 6u
#define JX_IDLE_NOTE_CAPACITY 512u
#define JX_IDLE_COLLECT_CALL_BYTES 3u
#define JX_IDLE_ORDINAL_NONE UINT32_MAX

#define JX_IDLE_CALL_COLLECT 0x02u
#define JX_IDLE_NOTE_MODE_ACK  0u
#define JX_IDLE_NOTE_MODE_DATA 1u

typedef struct {
    uint32_t program_id;
    uint32_t ack_ordinal;
    uint32_t one_ordinal;
    uint32_t reserved32;
    uint64_t epoch;
    uint8_t has_data;
    uint8_t reserved[7];
} jx_idle_note;

typedef struct {
    uint8_t version;
    _Atomic uint32_t lock;
    _Atomic uint32_t collect_pending;
    _Atomic uint32_t mode;
    _Atomic uint32_t ack_head;
    _Atomic uint32_t ack_tail;
    _Atomic uint32_t data_head;
    _Atomic uint32_t data_tail;
    _Atomic uint32_t expected_answers;
    _Atomic uint32_t answered;
    _Atomic uint32_t data_answers;
    _Atomic uint32_t finalized;
    _Atomic uint64_t epoch;
    jx_idle_note ack_notes[JX_IDLE_NOTE_CAPACITY];
    jx_idle_note data_notes[JX_IDLE_NOTE_CAPACITY];
} jx_idle_note_deque;

typedef int (*jx_idle_collect_fn)(const jx_idle_note *note, void *context);

void jx_idle_note_deque_init(jx_idle_note_deque *deque);
int jx_idle_note_begin_epoch(jx_idle_note_deque *deque, uint64_t epoch, uint32_t expected_answers);
int jx_idle_note_publish(jx_idle_note_deque *deque, uint32_t program_id, uint64_t epoch, uint8_t has_data);
int jx_idle_note_finalize_epoch(jx_idle_note_deque *deque);

int jx_idle_collect_is_pending(const jx_idle_note_deque *deque);
int jx_idle_epoch_is_complete(const jx_idle_note_deque *deque);
uint32_t jx_idle_epoch_answered(const jx_idle_note_deque *deque);
uint32_t jx_idle_epoch_data_answers(const jx_idle_note_deque *deque);
uint32_t jx_idle_epoch_ack_count(const jx_idle_note_deque *deque);
uint32_t jx_idle_epoch_data_count(const jx_idle_note_deque *deque);
int jx_idle_epoch_ack_at(const jx_idle_note_deque *deque, uint32_t ordinal, jx_idle_note *out);
int jx_idle_collect_encode(uint8_t out[JX_IDLE_COLLECT_CALL_BYTES]);
int jx_idle_collect_is_call(const uint8_t *code, size_t length);
int jx_idle_collect_run(jx_idle_note_deque *deque, jx_idle_collect_fn collect, void *context);

#endif
