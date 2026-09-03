#ifndef JX_JXL_CONTAINER_RUNTIME_H
#define JX_JXL_CONTAINER_RUNTIME_H

#include <stddef.h>
#include <stdint.h>

#ifdef __cplusplus
extern "C" {
#endif

/*
 * Native admission ABI for JXL Bag containers.
 *
 * This struct is runtime state, not canonical JX state and not serialized into
 * a .64B Book. Admission builds it from the Book's JXCBIND1 records plus the
 * host/kernel's durable Bag-handle resolver.
 *
 * The field order is part of the x86-64 assembly ABI. Keep synchronized with:
 *   native/x86_64/jxl_containers.asm
 *   native/x86_64/jxl_container_executor.asm
 *   native/x86_64/jxl_container_stream.asm
 */
typedef struct JxJxlContainerBinding {
    void    *native_fn;       /* +00 already-resolved assembly routine */
    uint64_t *base;           /* +08 element/hash-slot storage */
    uint64_t *head;           /* +16 ring head or discipline-specific origin */
    uint64_t *tail;           /* +24 ring tail / vector count */
    uint64_t capacity;        /* +32 element or slot capacity */
    uint64_t mask;            /* +40 power-of-two ring/hash mask */
    uint64_t *generation;     /* +48 durable Bag generation */
    uint64_t *flags;          /* +56 runtime Bag flags */
    void    *aux;             /* +64 admitted discipline helper state */
    void    *aux2;            /* +72 admitted discipline helper state */
} JxJxlContainerBinding;

_Static_assert(sizeof(JxJxlContainerBinding) == 80, "JXL container binding ABI must stay 80 bytes");
_Static_assert(offsetof(JxJxlContainerBinding, native_fn) == 0, "native_fn ABI offset");
_Static_assert(offsetof(JxJxlContainerBinding, base) == 8, "base ABI offset");
_Static_assert(offsetof(JxJxlContainerBinding, head) == 16, "head ABI offset");
_Static_assert(offsetof(JxJxlContainerBinding, tail) == 24, "tail ABI offset");
_Static_assert(offsetof(JxJxlContainerBinding, capacity) == 32, "capacity ABI offset");
_Static_assert(offsetof(JxJxlContainerBinding, mask) == 40, "mask ABI offset");
_Static_assert(offsetof(JxJxlContainerBinding, generation) == 48, "generation ABI offset");
_Static_assert(offsetof(JxJxlContainerBinding, flags) == 56, "flags ABI offset");
_Static_assert(offsetof(JxJxlContainerBinding, aux) == 64, "aux ABI offset");
_Static_assert(offsetof(JxJxlContainerBinding, aux2) == 72, "aux2 ABI offset");

enum {
    JX_JXL_CONTAINER_INSTRUCTION_BYTES = 6,
    JX_JXL_CONTAINER_UNUSED_SELECTOR = 0x7f,
    JX_BAG_DIRTY = 1u,
};

/* Execute exactly one fixed-width JXL container instruction.
 *
 * On success RAX/return value is next pc. The assembly ABI also exposes CF for
 * failure/slow-path; normal C callers should prefer the stream API below when
 * executing a prepared container region.
 */
const uint8_t *jx_jxl_container_execute(
    const uint8_t *pc,
    JxJxlContainerBinding *bindings,
    uint64_t window8[8],
    uint64_t binding_count
);

/* Execute [begin,end) as one resident prepared-container region.
 *
 * begin/end      exact six-byte-aligned JXL stream bounds
 * bindings       admitted operation-specific runtime binding table
 * window8        current eight-qword JXL register window
 * binding_count  admitted binding count
 *
 * Returns 0 on exact completion, -1 for malformed/truncated JXL or an invalid
 * prepared reference, and -2 when an admitted native operation requests its
 * slow/failure path. The loop itself is pure x86-64 assembly and keeps stream
 * state resident across all operations.
 */
int jx_jxl_container_execute_stream(
    const uint8_t *begin,
    const uint8_t *end,
    JxJxlContainerBinding *bindings,
    uint64_t window8[8],
    uint64_t binding_count
);

/* Numeric admission table. Slot zero is invalid; IDs 1..count are callable
 * assembly targets. Serialized JXCBIND1 records carry the numeric native ID,
 * so admission never needs native symbol-name lookup.
 */
extern void *jx_jxl_container_native_table[];
extern const uint64_t jx_jxl_container_native_count;

/* Direct native routines are public so admission can bind them without a
 * higher-level container runtime. Their machine ABI is RDI=binding, RSI=arg0,
 * RDX=arg1, RAX=result, CF=status.
 */
void jx_vector_push_u64(void);
void jx_vector_pop_u64(void);
void jx_vector_get_u64(void);
void jx_vector_put_u64(void);
void jx_vector_emplace_u64(void);
void jx_vector_peek_u64(void);
void jx_vector_reserve_u64(void);
void jx_record_get_u64(void);
void jx_record_put_u64(void);
void jx_queue_push_u64(void);
void jx_queue_pop_u64(void);
void jx_queue_peek_u64(void);
void jx_deque_push_front_u64(void);
void jx_deque_push_back_u64(void);
void jx_deque_pop_front_u64(void);
void jx_deque_pop_back_u64(void);
void jx_deque_peek_front_u64(void);
void jx_deque_peek_back_u64(void);
void jx_ring_reserve_u64(void);
void jx_map_emplace_u64(void);
void jx_map_get_u64(void);
void jx_map_put_u64(void);
void jx_map_has_u64(void);
void jx_map_remove_u64(void);
void jx_set_add_u64(void);
void jx_set_has_u64(void);
void jx_set_remove_u64(void);
void jx_hash_reserve_u64(void);
void jx_bag_dirty(void);
void jx_bag_sync(void);

#ifdef __cplusplus
}
#endif

#endif
