#ifndef JX_IDLE_COLLECT_H
#define JX_IDLE_COLLECT_H

#include <stddef.h>
#include <stdint.h>
#include <stdatomic.h>

#define JX_IDLE_COLLECT_VERSION 3u
#define JX_IDLE_NOTE_CAPACITY 512u
#define JX_IDLE_COLLECT_CALL_BYTES 3u

/* Second fixed system envelope:
 *   0x7f 0x00 0x02 = SYSTEM | IDLE-BUS | COLLECT
 * The first has_data=1 atomically flips the epoch from ACK to DATA mode and
 * arms this bus. The bus waits for every program reply, then drains all data
 * notes from that epoch together in one batch.
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
    _Atomic uint32_t head;
    _Atomic uint32_t tail;
    _Atomic uint32_t expected_answers;
    _Atomic uint32_t answered;
    _Atomic uint32_t data_answers;
    _Atomic uint64_t epoch;
    jx_idle_note notes[JX_IDLE_NOTE_CAPACITY];
} jx_idle_note_deque;

typedef int (*jx_idle_collect_fn)(const jx_idle_note *note, void *context);

void jx_idle_note_deque_init(jx_idle_note_deque *deque);
int jx_idle_note_begin_epoch(jx_idle_note_deque *deque,
                             uint64_t epoch,
                             uint32_t expected_answers);

/* Every program publishes exactly once per epoch, including has_data=0.
 * Zero replies advance epoch completion but are not stored in the DATA list.
 * The first 1 atomically changes mode ACK -> DATA and arms the second bus.
 */
int jx_idle_note_publish(jx_idle_note_deque *deque,
                         uint32_t program_id,
                         uint64_t epoch,
                         uint8_t has_data);

int jx_idle_collect_is_pending(const jx_idle_note_deque *deque);
int jx_idle_epoch_is_complete(const jx_idle_note_deque *deque);
uint32_t jx_idle_epoch_answered(const jx_idle_note_deque *deque);
uint32_t jx_idle_epoch_data_answers(const jx_idle_note_deque *deque);
int jx_idle_collect_encode(uint8_t out[JX_IDLE_COLLECT_CALL_BYTES]);
int jx_idle_collect_is_call(const uint8_t *code, size_t length);

/* Returns 0 while replies are still outstanding, even if bus #2 has already
 * been armed. Once the epoch is complete, every stored 1-note is drained in
 * one batch. */
int jx_idle_collect_run(jx_idle_note_deque *deque,
                        jx_idle_collect_fn collect,
                        void *context);

#endif
