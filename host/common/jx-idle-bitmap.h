#ifndef JX_IDLE_BITMAP_H
#define JX_IDLE_BITMAP_H

#include <stddef.h>
#include <stdint.h>
#include <stdatomic.h>

#define JX_IDLE_BITMAP_VERSION 3u
#define JX_IDLE_BITMAP_MAX_PROGRAMS 256u
#define JX_IDLE_BITMAP_WORD_BITS 64u
#define JX_IDLE_BITMAP_WORDS (JX_IDLE_BITMAP_MAX_PROGRAMS / JX_IDLE_BITMAP_WORD_BITS)

/*
 * Bus #1 response representation.
 *
 * Every registered program owns one stable ordinal bit position. A program
 * always answers an epoch, even when the answer is zero:
 *
 *   CLAIMED bit  = this program owns its one reply slot for the epoch
 *   ANSWERED bit = the complete 0/1 reply has been published
 *   DATA bit     = the published reply was 1 / prepared data exists
 *
 * A producer that has a separate payload may reserve its reply with
 * jx_idle_bitmap_claim(), publish that payload, then finish with
 * jx_idle_bitmap_commit_claimed(). This preserves the publication order:
 *
 *   CLAIMED -> payload -> DATA -> ANSWERED
 *
 * A plain 0/1 caller may continue to use jx_idle_bitmap_reply(), which is the
 * claim+commit convenience operation.
 *
 * ANSWERED is the completion fence: when the expected ANSWERED bitmap is full,
 * every DATA bit and every payload published before commit is already visible.
 */
typedef struct {
    uint8_t version;
    uint8_t reserved[7];
    _Atomic uint64_t epoch;
    _Atomic uint32_t program_count;
    _Atomic uint32_t collect_pending;
    uint64_t expected[JX_IDLE_BITMAP_WORDS];
    _Atomic uint64_t claimed[JX_IDLE_BITMAP_WORDS];
    _Atomic uint64_t answered[JX_IDLE_BITMAP_WORDS];
    _Atomic uint64_t data[JX_IDLE_BITMAP_WORDS];
} jx_idle_bitmap;

typedef int (*jx_idle_bitmap_collect_fn)(uint32_t program_ordinal,
                                         uint32_t one_ordinal,
                                         uint64_t epoch,
                                         void *context);

void jx_idle_bitmap_init(jx_idle_bitmap *bitmap);
int jx_idle_bitmap_begin(jx_idle_bitmap *bitmap,
                         uint64_t epoch,
                         uint32_t program_count);

/* Reserve exactly one reply position for this program/epoch. */
int jx_idle_bitmap_claim(jx_idle_bitmap *bitmap,
                         uint64_t epoch,
                         uint32_t program_ordinal);

/* Commit an already-claimed reply after any associated payload is published.
 * Returns 2 when this 1 is the unique bus-#2 armer, 1 for a later 1, and 0 for
 * a committed zero reply. */
int jx_idle_bitmap_commit_claimed(jx_idle_bitmap *bitmap,
                                  uint64_t epoch,
                                  uint32_t program_ordinal,
                                  uint8_t has_data);

/* Convenience claim+commit operation.
 * Returns:
 *   2  data=1 and this producer atomically armed bus #2
 *   1  data=1 and bus #2 was already armed
 *   0  data=0; real reply committed in ANSWERED
 *  <0  invalid/duplicate/wrong epoch
 */
int jx_idle_bitmap_reply(jx_idle_bitmap *bitmap,
                         uint64_t epoch,
                         uint32_t program_ordinal,
                         uint8_t has_data);

int jx_idle_bitmap_complete(const jx_idle_bitmap *bitmap);
int jx_idle_bitmap_collect_pending(const jx_idle_bitmap *bitmap);
uint32_t jx_idle_bitmap_data_count(const jx_idle_bitmap *bitmap);

int jx_idle_bitmap_snapshot(const jx_idle_bitmap *bitmap,
                            uint64_t data_out[JX_IDLE_BITMAP_WORDS],
                            uint64_t answered_out[JX_IDLE_BITMAP_WORDS],
                            uint64_t *epoch_out,
                            uint32_t *program_count_out);

int jx_idle_bitmap_collect(jx_idle_bitmap *bitmap,
                           jx_idle_bitmap_collect_fn collect,
                           void *context);

#endif
