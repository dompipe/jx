#ifndef JX_IDLE_COLLECT_H
#define JX_IDLE_COLLECT_H

#include <stddef.h>
#include <stdint.h>
#include <stdatomic.h>

#define JX_IDLE_COLLECT_VERSION 5u
#define JX_IDLE_NOTE_CAPACITY 512u
#define JX_IDLE_COLLECT_CALL_BYTES 3u
#define JX_IDLE_ORDINAL_NONE UINT32_MAX

/* Second fixed system envelope:
 *   0x7f 0x00 0x02 = SYSTEM | IDLE-BUS | COLLECT
 *
 * Bus #1 is the ordering authority. Every program writes one ACK note (0/1)
 * into the epoch sequence. The first 1 atomically arms bus #2, but collection
 * cannot run until the ACK sequence is complete. At completion, JX derives
 * the DATA deque by scanning ACK order and assigning one_ordinal=0..N-1.
 * Bus #2 therefore cannot reorder data because of producer scheduling.
 */
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
int jx_idle_note_begin_epoch(jx_idle_note_deque *deque,
                             uint64_t epoch,
                             uint32_t expected_answers);

/* Every program publishes exactly once per epoch, including 0. ACK notes are
 * appended in bus-return order. one_ordinal is assigned later, only after the
 * full ACK sequence has returned and can be frozen. */
int jx_idle_note_publish(jx_idle_note_deque *deque,
                         uint32_t program_id,
                         uint64_t epoch,
                         uint8_t has_data);

/* Freeze the completed ACK vector and derive the DATA list in its exact order.
 * Safe to call repeatedly; after the first successful finalization it is a
 * no-op. Returns number of 1 notes, 0 for all-zero/not-ready, or <0 on error. */
int jx_idle_note_finalize_epoch(jx_idle_note_deque *deque);

int jx_idle_collect_is_pending(const jx_idle_note_deque *deque);
int jx_idle_epoch_is_complete(const jx_idle_note_deque *deque);
uint32_t jx_idle_epoch_answered(const jx_idle_note_deque *deque);
uint32_t jx_idle_epoch_data_answers(const jx_idle_note_deque *deque);
uint32_t jx_idle_epoch_ack_count(const jx_idle_note_deque *deque);
uint32_t jx_idle_epoch_data_count(const jx_idle_note_deque *deque);
int jx_idle_collect_encode(uint8_t out[JX_IDLE_COLLECT_CALL_BYTES]);
int jx_idle_collect_is_call(const uint8_t *code, size_t length);

/* Bus #2 only drains a finalized epoch. DATA notes are emitted in increasing
 * one_ordinal, which is the same order as their 1 positions in the ACK bit
 * sequence. */
int jx_idle_collect_run(jx_idle_note_deque *deque,
                        jx_idle_collect_fn collect,
                        void *context);

#endif
