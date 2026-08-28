#ifndef JX_IDLE_BITMAP_H
#define JX_IDLE_BITMAP_H

#include <stddef.h>
#include <stdint.h>
#include <stdatomic.h>

#define JX_IDLE_BITMAP_VERSION 1u
#define JX_IDLE_BITMAP_MAX_PROGRAMS 256u
#define JX_IDLE_BITMAP_WORD_BITS 64u
#define JX_IDLE_BITMAP_WORDS (JX_IDLE_BITMAP_MAX_PROGRAMS / JX_IDLE_BITMAP_WORD_BITS)

/*
 * Bus #1 response representation.
 *
 * Every registered program owns one stable ordinal bit position. A program
 * always answers an epoch, even when the answer is zero:
 *
 *   ANSWERED bit = program returned for this epoch
 *   DATA bit     = program returned 1 / has prepared data
 *
 * DATA therefore is the ordered 0/1 bitstring carried by bus #1. ANSWERED is
 * control state used only to distinguish a real zero from "not returned yet".
 * The first DATA=1 atomically arms bus #2, but bus #2 cannot collect until the
 * complete expected ANSWERED mask has returned.
 */
typedef struct {
    uint8_t version;
    uint8_t reserved[7];
    _Atomic uint64_t epoch;
    _Atomic uint32_t program_count;
    _Atomic uint32_t collect_pending;
    uint64_t expected[JX_IDLE_BITMAP_WORDS];
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

/* Returns:
 *   2  data=1 and this producer atomically armed bus #2
 *   1  data=1 and bus #2 was already armed
 *   0  data=0; real reply recorded in ANSWERED
 *  <0  invalid/duplicate/wrong epoch
 */
int jx_idle_bitmap_reply(jx_idle_bitmap *bitmap,
                         uint64_t epoch,
                         uint32_t program_ordinal,
                         uint8_t has_data);

int jx_idle_bitmap_complete(const jx_idle_bitmap *bitmap);
int jx_idle_bitmap_collect_pending(const jx_idle_bitmap *bitmap);
uint32_t jx_idle_bitmap_data_count(const jx_idle_bitmap *bitmap);

/* Snapshot the ordered bus #1 reply string. out_words receives DATA; callers
 * that also need completion state can request ANSWERED. Bits above
 * program_count are guaranteed zero. */
int jx_idle_bitmap_snapshot(const jx_idle_bitmap *bitmap,
                            uint64_t data_out[JX_IDLE_BITMAP_WORDS],
                            uint64_t answered_out[JX_IDLE_BITMAP_WORDS],
                            uint64_t *epoch_out,
                            uint32_t *program_count_out);

/* Bus #2 claims the one armed edge only after all 0/1 replies returned, then
 * enumerates DATA set bits in stable program order. one_ordinal is therefore
 * exactly the ordinal of that 1 within the completed bitstring. */
int jx_idle_bitmap_collect(jx_idle_bitmap *bitmap,
                           jx_idle_bitmap_collect_fn collect,
                           void *context);

#endif
