#ifndef JX_IDLE_COLLECT_H
#define JX_IDLE_COLLECT_H

#include <stddef.h>
#include <stdint.h>
#include <stdatomic.h>

#define JX_IDLE_COLLECT_VERSION 4u
#define JX_IDLE_NOTE_CAPACITY 512u
#define JX_IDLE_COLLECT_CALL_BYTES 3u

/* Second fixed system envelope:
 *   0x7f 0x00 0x02 = SYSTEM | IDLE-BUS | COLLECT
 *
 * Every program writes one ACK note (0 or 1) for each wake epoch. The first
 * 1 atomically flips collection mode ACK -> DATA and arms bus #2. Every 1 is
 * also written to the DATA deque. Bus #2 waits for all ACKs, then drains the
 * entire DATA deque as one batch.
 */
#define JX_IDLE_CALL_COLLECT 0x02u
#define JX_IDLE_NOTE_MODE_ACK  0u
#define JX_IDLE_NOTE_MODE_DATA 1u

typedef struct {
    uint32_t program_id;
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
    _Atomic uint64_t epoch;
    jx_idle_note ack_notes[JX_IDLE_NOTE_CAPACITY];
    jx_idle_note data_notes[JX_IDLE_NOTE_CAPACITY];
} jx_idle_note_deque;

typedef int (*jx_idle_collect_fn)(const jx_idle_note *note, void *context);

void jx_idle_note_deque_init(jx_idle_note_deque *deque);
int jx_idle_note_begin_epoch(jx_idle_note_deque *deque,
                             uint64_t epoch,
                             uint32_t expected_answers);

/* Every program publishes exactly once per epoch, including has_data=0.
 * All replies are written to ACK deque. Only has_data=1 replies are copied
 * into DATA deque. First 1 flips mode and arms collection exactly once.
 */
int jx_idle_note_publish(jx_idle_note_deque *deque,
                         uint32_t program_id,
                         uint64_t epoch,
                         uint8_t has_data);

int jx_idle_collect_is_pending(const jx_idle_note_deque *deque);
int jx_idle_epoch_is_complete(const jx_idle_note_deque *deque);
uint32_t jx_idle_epoch_answered(const jx_idle_note_deque *deque);
uint32_t jx_idle_epoch_data_answers(const jx_idle_note_deque *deque);
uint32_t jx_idle_epoch_ack_count(const jx_idle_note_deque *deque);
uint32_t jx_idle_epoch_data_count(const jx_idle_note_deque *deque);
int jx_idle_collect_encode(uint8_t out[JX_IDLE_COLLECT_CALL_BYTES]);
int jx_idle_collect_is_call(const uint8_t *code, size_t length);

/* Returns 0 while replies are outstanding. Once every ACK has arrived, all
 * DATA notes (all 1s from the epoch) are drained together in one batch. */
int jx_idle_collect_run(jx_idle_note_deque *deque,
                        jx_idle_collect_fn collect,
                        void *context);

#endif
