; Numeric JXL container native target table.
;
; IDs are serialized by jx-jxl-containers.php and resolved at admission by
; indexing this table. No native symbol string lookup is required.
;
; Map IDs 18..22 route to the canonical keyed-vector backend. The older split
; keys[] + values[] jx_map_* symbols remain linked only for later A/B benchmarks.

bits 64
default rel

extern jx_vector_push_u64
extern jx_vector_pop_u64
extern jx_vector_get_u64
extern jx_vector_put_u64
extern jx_vector_emplace_u64
extern jx_vector_peek_u64
extern jx_record_get_u64
extern jx_record_put_u64
extern jx_queue_push_u64
extern jx_queue_pop_u64
extern jx_queue_peek_u64
extern jx_deque_push_front_u64
extern jx_deque_push_back_u64
extern jx_deque_pop_front_u64
extern jx_deque_pop_back_u64
extern jx_deque_peek_front_u64
extern jx_deque_peek_back_u64
extern jx_map_vector_emplace_u64
extern jx_map_vector_get_u64
extern jx_map_vector_put_u64
extern jx_map_vector_has_u64
extern jx_map_vector_remove_u64
extern jx_set_add_u64
extern jx_set_has_u64
extern jx_set_remove_u64
extern jx_vector_reserve_u64
extern jx_ring_reserve_u64
extern jx_sorted_reserve_u64
extern jx_bag_dirty
extern jx_bag_sync

section .rodata align=8

global jx_jxl_container_native_table
global jx_jxl_container_native_count

jx_jxl_container_native_table:
    dq 0                              ; 0 invalid
    dq jx_vector_push_u64             ; 1
    dq jx_vector_pop_u64              ; 2
    dq jx_vector_get_u64              ; 3
    dq jx_vector_put_u64              ; 4
    dq jx_vector_emplace_u64          ; 5
    dq jx_vector_peek_u64             ; 6
    dq jx_record_get_u64              ; 7
    dq jx_record_put_u64              ; 8
    dq jx_queue_push_u64              ; 9
    dq jx_queue_pop_u64               ; 10
    dq jx_queue_peek_u64              ; 11
    dq jx_deque_push_front_u64        ; 12
    dq jx_deque_push_back_u64         ; 13
    dq jx_deque_pop_front_u64         ; 14
    dq jx_deque_pop_back_u64          ; 15
    dq jx_deque_peek_front_u64        ; 16
    dq jx_deque_peek_back_u64         ; 17
    dq jx_map_vector_emplace_u64      ; 18
    dq jx_map_vector_get_u64          ; 19
    dq jx_map_vector_put_u64          ; 20
    dq jx_map_vector_has_u64          ; 21
    dq jx_map_vector_remove_u64       ; 22
    dq jx_set_add_u64                 ; 23
    dq jx_set_has_u64                 ; 24
    dq jx_set_remove_u64              ; 25
    dq jx_vector_reserve_u64          ; 26
    dq jx_ring_reserve_u64            ; 27
    dq jx_sorted_reserve_u64          ; 28
    dq jx_bag_dirty                    ; 29
    dq jx_bag_sync                     ; 30

; Number of valid IDs, not including slot zero.
jx_jxl_container_native_count:
    dq 30

section .note.GNU-stack noalloc noexec nowrite progbits
