#ifndef JX_IDLE_COLLECT_H
#define JX_IDLE_COLLECT_H

#include <stddef.h>
#include <stdint.h>
#include <stdatomic.h>

#define JX_IDLE_COLLECT_VERSION 1u
#define JX_IDLE_NOTE_CAPACITY 512u
#define JX_IDLE_COLLECT_CALL_BYTES 3u

/* Second fixed system envelope:
 *   0x7f 0x00 0x02 = SYSTEM | IDLE-BUS | COLLECT
 * It is armed by the first producer that atomically publishes has_data=1.
 */
#define JX_IDLE_CALL_COLLECT 0x02u

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
    _Atomic uint32_t head;
    _Atomic uint32_t tail;
    jx_idle_note notes[JX_IDLE_NOTE_CAPACITY];
} jx_idle_note_deque;

typedef int (*jx_idle_collect_fn)(const jx_idle_note *note, void *context);

void jx_idle_note_deque_init(jx_idle_note_deque *deque);

/* Publish one program's wake result. Returns:
 *   2  note=1 and this producer armed the collect bus (0 -> 1)
 *   1  note=1 and collect bus was already armed
 *   0  note=0 published; no collect arm
 *  <0  error/full
 */
int jx_idle_note_publish(jx_idle_note_deque *deque,
                         uint32_t program_id,
                         uint64_t epoch,
                         uint8_t has_data);

int jx_idle_collect_is_pending(const jx_idle_note_deque *deque);
int jx_idle_collect_encode(uint8_t out[JX_IDLE_COLLECT_CALL_BYTES]);
int jx_idle_collect_is_call(const uint8_t *code, size_t length);

/* Second bus sweep. Notes with has_data=0 are discarded cheaply. Notes with
 * has_data=1 are handed to collect(). All notes consumed by the sweep leave
 * the deque. If a producer races in after the sweep, collect_pending remains
 * armed for the next sweep rather than losing the edge.
 */
int jx_idle_collect_run(jx_idle_note_deque *deque,
                        jx_idle_collect_fn collect,
                        void *context);

#endif
