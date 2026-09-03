; JX JXL native containers - x86-64 System V ABI
;
; Containers execute here as pure assembly. PHP/JX names, aliases, discipline
; checks and method lookup have already disappeared before admission.
;
; Call convention for an admitted native binding:
;   RDI = runtime PreparedContainerBinding*
;   RSI = arg0 (u64 value/index/key/request)
;   RDX = arg1 (u64 value/destination helper input)
;   RAX = result
;   CF  = 0 success, 1 slow-path/failure (bounds/full/empty/not-found)
;
; Runtime binding layout (pointers are installed at admission, never serialized):
;   +00 native function pointer
;   +08 base pointer
;   +16 head/count-origin/cursor pointer (qword*)
;   +24 tail/count pointer (qword*)
;   +32 capacity (elements/slots)
;   +40 mask (power-of-two ring mask; unused by ordered Map/Set)
;   +48 generation pointer (qword*)
;   +56 flags pointer (qword*)
;   +64 aux pointer (Map values[]; discipline-specific otherwise)
;   +72 aux2 pointer
;
; Map is always an ordered 2D array: keys[] + values[]. Set is its ordered
; 1D unique-key form. There is no hash table, probing, tombstone, collision,
; or load-factor law in canonical JXL Map/Set execution.
;
; All v1 hot payloads are fixed u64. Wider records are lowered to prepared copy
; helpers before entering this ABI.

bits 64
default rel

%define B_FN       0
%define B_BASE     8
%define B_HEAD    16
%define B_TAIL    24
%define B_CAP     32
%define B_MASK    40
%define B_GEN     48
%define B_FLAGS   56
%define B_AUX     64
%define B_AUX2    72

%define BAG_DIRTY 1

section .text

; ---------------------------------------------------------------------------
; Binding dispatcher. The decoder has already selected the binding record.
; There is no discipline or operation lookup here: jump straight to fn.
; ---------------------------------------------------------------------------
global jx_jxl_container_dispatch
jx_jxl_container_dispatch:
    mov rax, [rdi + B_FN]
    test rax, rax
    jz .bad
    jmp rax
.bad:
    mov rax, -1
    stc
    ret

; ---------------------------------------------------------------------------
; Record: fixed dense u64 slots. capacity == field count.
; ---------------------------------------------------------------------------
global jx_record_get_u64
global jx_record_put_u64
jx_record_get_u64:
    cmp rsi, [rdi + B_CAP]
    jae jx_record_fail
    mov rax, [rdi + B_BASE]
    mov rax, [rax + rsi*8]
    clc
    ret

jx_record_put_u64:
    cmp rsi, [rdi + B_CAP]
    jae jx_record_fail
    mov rax, [rdi + B_BASE]
    mov [rax + rsi*8], rdx
    mov rax, rdx
    clc
    ret

jx_record_fail:
    mov rax, -1
    stc
    ret

; ---------------------------------------------------------------------------
; Vector/stack: B_TAIL points to count. B_HEAD is unused.
; ---------------------------------------------------------------------------
global jx_vector_push_u64
global jx_vector_pop_u64
global jx_vector_get_u64
global jx_vector_put_u64
global jx_vector_emplace_u64
global jx_vector_peek_u64
global jx_vector_reserve_u64
global jx_stack_push_u64
global jx_stack_pop_u64
global jx_stack_peek_u64

jx_stack_push_u64:
jx_vector_push_u64:
    mov r8, [rdi + B_TAIL]
    test r8, r8
    jz jx_vec_fail
    mov rcx, [r8]
    cmp rcx, [rdi + B_CAP]
    jae jx_vec_fail
    mov rax, [rdi + B_BASE]
    mov [rax + rcx*8], rsi
    inc rcx
    mov [r8], rcx
    mov rax, rsi
    clc
    ret

jx_stack_pop_u64:
jx_vector_pop_u64:
    mov r8, [rdi + B_TAIL]
    test r8, r8
    jz jx_vec_fail
    mov rcx, [r8]
    test rcx, rcx
    jz jx_vec_fail
    dec rcx
    mov [r8], rcx
    mov rax, [rdi + B_BASE]
    mov rax, [rax + rcx*8]
    clc
    ret

jx_vector_get_u64:
    mov r8, [rdi + B_TAIL]
    test r8, r8
    jz jx_vec_fail
    cmp rsi, [r8]
    jae jx_vec_fail
    mov rax, [rdi + B_BASE]
    mov rax, [rax + rsi*8]
    clc
    ret

jx_vector_put_u64:
    mov r8, [rdi + B_TAIL]
    test r8, r8
    jz jx_vec_fail
    cmp rsi, [r8]
    jae jx_vec_fail
    mov rax, [rdi + B_BASE]
    mov [rax + rsi*8], rdx
    mov rax, rdx
    clc
    ret

; RSI=index, RDX=value. One backwards tail shift, then one store.
jx_vector_emplace_u64:
    mov r8, [rdi + B_TAIL]
    test r8, r8
    jz jx_vec_fail
    mov rcx, [r8]
    cmp rsi, rcx
    ja jx_vec_fail
    cmp rcx, [rdi + B_CAP]
    jae jx_vec_fail
    mov r9, [rdi + B_BASE]
.shift:
    cmp rcx, rsi
    jbe .insert
    mov r10, [r9 + rcx*8 - 8]
    mov [r9 + rcx*8], r10
    dec rcx
    jmp .shift
.insert:
    mov [r9 + rsi*8], rdx
    inc qword [r8]
    mov rax, rdx
    clc
    ret

jx_stack_peek_u64:
jx_vector_peek_u64:
    mov r8, [rdi + B_TAIL]
    test r8, r8
    jz jx_vec_fail
    mov rcx, [r8]
    test rcx, rcx
    jz jx_vec_fail
    dec rcx
    mov rax, [rdi + B_BASE]
    mov rax, [rax + rcx*8]
    clc
    ret

; RSI=requested additional elements. This is the hot guard only; allocation is
; an already-prelinked cold service taken by the caller on CF=1.
jx_vector_reserve_u64:
    mov r8, [rdi + B_TAIL]
    test r8, r8
    jz jx_vec_fail
    mov rax, [r8]
    add rax, rsi
    jc jx_vec_fail
    cmp rax, [rdi + B_CAP]
    ja jx_vec_fail
    clc
    ret

jx_vec_fail:
    mov rax, -1
    stc
    ret

; ---------------------------------------------------------------------------
; Queue/deque power-of-two ring. Head/tail are monotonic indexes.
; ---------------------------------------------------------------------------
global jx_queue_push_u64
global jx_queue_pop_u64
global jx_queue_peek_u64
global jx_deque_push_front_u64
global jx_deque_push_back_u64
global jx_deque_pop_front_u64
global jx_deque_pop_back_u64
global jx_deque_peek_front_u64
global jx_deque_peek_back_u64
global jx_ring_reserve_u64

jx_deque_push_back_u64:
jx_queue_push_u64:
    mov r8, [rdi + B_HEAD]
    mov r9, [rdi + B_TAIL]
    test r8, r8
    jz jx_ring_fail
    test r9, r9
    jz jx_ring_fail
    mov rcx, [r9]
    mov rax, rcx
    sub rax, [r8]
    cmp rax, [rdi + B_CAP]
    jae jx_ring_fail
    and rcx, [rdi + B_MASK]
    mov rax, [rdi + B_BASE]
    mov [rax + rcx*8], rsi
    inc qword [r9]
    mov rax, rsi
    clc
    ret

jx_deque_pop_front_u64:
jx_queue_pop_u64:
    mov r8, [rdi + B_HEAD]
    mov r9, [rdi + B_TAIL]
    test r8, r8
    jz jx_ring_fail
    test r9, r9
    jz jx_ring_fail
    mov rcx, [r8]
    cmp rcx, [r9]
    je jx_ring_fail
    mov rdx, rcx
    and rdx, [rdi + B_MASK]
    mov rax, [rdi + B_BASE]
    mov rax, [rax + rdx*8]
    inc rcx
    mov [r8], rcx
    clc
    ret

jx_deque_peek_front_u64:
jx_queue_peek_u64:
    mov r8, [rdi + B_HEAD]
    mov r9, [rdi + B_TAIL]
    test r8, r8
    jz jx_ring_fail
    test r9, r9
    jz jx_ring_fail
    mov rcx, [r8]
    cmp rcx, [r9]
    je jx_ring_fail
    and rcx, [rdi + B_MASK]
    mov rax, [rdi + B_BASE]
    mov rax, [rax + rcx*8]
    clc
    ret

jx_deque_push_front_u64:
    mov r8, [rdi + B_HEAD]
    mov r9, [rdi + B_TAIL]
    test r8, r8
    jz jx_ring_fail
    test r9, r9
    jz jx_ring_fail
    mov rax, [r9]
    sub rax, [r8]
    cmp rax, [rdi + B_CAP]
    jae jx_ring_fail
    dec qword [r8]
    mov rcx, [r8]
    and rcx, [rdi + B_MASK]
    mov rax, [rdi + B_BASE]
    mov [rax + rcx*8], rsi
    mov rax, rsi
    clc
    ret

jx_deque_pop_back_u64:
    mov r8, [rdi + B_HEAD]
    mov r9, [rdi + B_TAIL]
    test r8, r8
    jz jx_ring_fail
    test r9, r9
    jz jx_ring_fail
    mov rcx, [r9]
    cmp rcx, [r8]
    je jx_ring_fail
    dec rcx
    mov [r9], rcx
    and rcx, [rdi + B_MASK]
    mov rax, [rdi + B_BASE]
    mov rax, [rax + rcx*8]
    clc
    ret

jx_deque_peek_back_u64:
    mov r8, [rdi + B_HEAD]
    mov r9, [rdi + B_TAIL]
    test r8, r8
    jz jx_ring_fail
    test r9, r9
    jz jx_ring_fail
    mov rcx, [r9]
    cmp rcx, [r8]
    je jx_ring_fail
    dec rcx
    and rcx, [rdi + B_MASK]
    mov rax, [rdi + B_BASE]
    mov rax, [rax + rcx*8]
    clc
    ret

jx_ring_reserve_u64:
    mov r8, [rdi + B_HEAD]
    mov r9, [rdi + B_TAIL]
    test r8, r8
    jz jx_ring_fail
    test r9, r9
    jz jx_ring_fail
    mov rax, [r9]
    sub rax, [r8]
    add rax, rsi
    jc jx_ring_fail
    cmp rax, [rdi + B_CAP]
    ja jx_ring_fail
    clc
    ret

jx_ring_fail:
    mov rax, -1
    stc
    ret

; ---------------------------------------------------------------------------
; Map/Set ordered arrays.
;
; Map is a 2D array represented as two synchronized dense dimensions:
;   B_BASE -> keys[]
;   B_AUX  -> values[]
;   B_TAIL -> count
;   B_HEAD -> locality cursor index (optional)
;
; Set is the same law without values[]:
;   B_BASE -> unique sorted keys[]
;   B_TAIL -> count
;   B_HEAD -> locality cursor index (optional)
;
; FIND is lower_bound with a one-step marquee fast path from the previous
; cursor. PUT overwrites values[index] when the key exists. Missing keys are
; inserted into the sorted arrays. There are no hash slots or tombstones.
; ---------------------------------------------------------------------------
global jx_sorted_find_u64
global jx_map_emplace_u64
global jx_map_get_u64
global jx_map_put_u64
global jx_map_has_u64
global jx_map_remove_u64
global jx_set_add_u64
global jx_set_has_u64
global jx_set_remove_u64
global jx_sorted_reserve_u64
global jx_hash_reserve_u64

; RDI=binding, RSI=key
; return RAX=lower_bound index, EDX=1 found / 0 absent.
; B_HEAD cursor is optional. Exact cursor and cursor+1 checks make ordered/local
; access effectively a marquee walk while random access falls back to binary.
jx_sorted_find_u64:
    mov r8, [rdi + B_BASE]
    mov r9, [rdi + B_TAIL]
    test r8, r8
    jz jx_sorted_fail
    test r9, r9
    jz jx_sorted_fail
    mov rcx, [r9]
    test rcx, rcx
    jz .empty

    mov r10, [rdi + B_HEAD]
    test r10, r10
    jz .binary
    mov rax, [r10]
    cmp rax, rcx
    jae .binary
    mov r11, [r8 + rax*8]
    cmp rsi, r11
    je .found_cursor
    jb .binary

    lea rdx, [rax + 1]
    cmp rdx, rcx
    jae .absent_end
    mov r11, [r8 + rdx*8]
    cmp rsi, r11
    je .found_next
    jb .absent_next

.binary:
    xor eax, eax                    ; low = 0
    mov rdx, rcx                    ; high = count
.bin_loop:
    cmp rax, rdx
    jae .bin_done
    mov r11, rax
    add r11, rdx
    shr r11, 1                      ; mid
    cmp qword [r8 + r11*8], rsi
    jb .bin_right
    mov rdx, r11
    jmp .bin_loop
.bin_right:
    lea rax, [r11 + 1]
    jmp .bin_loop
.bin_done:
    mov r10, [rdi + B_HEAD]
    test r10, r10
    jz .check_found
    mov [r10], rax
.check_found:
    cmp rax, rcx
    jae .absent
    cmp qword [r8 + rax*8], rsi
    jne .absent
    mov edx, 1
    clc
    ret

.found_cursor:
    mov edx, 1
    clc
    ret
.found_next:
    mov rax, rdx
    mov [r10], rax
    mov edx, 1
    clc
    ret
.absent_next:
    mov rax, rdx
    mov [r10], rax
    xor edx, edx
    clc
    ret
.absent_end:
    mov rax, rcx
    mov [r10], rax
    xor edx, edx
    clc
    ret
.empty:
    xor eax, eax
    mov r10, [rdi + B_HEAD]
    test r10, r10
    jz .empty_done
    mov qword [r10], 0
.empty_done:
    xor edx, edx
    clc
    ret
.absent:
    xor edx, edx
    clc
    ret

; Shift keys right one slot from count-1 down to insertion index RAX.
; RDI=binding, RAX=index. Clobbers RCX,R8,R9,R11.
jx_sorted_shift_keys_right:
    mov r8, [rdi + B_BASE]
    mov r9, [rdi + B_TAIL]
    mov rcx, [r9]
.shift_keys_right:
    cmp rcx, rax
    jbe .keys_right_done
    mov r11, [r8 + rcx*8 - 8]
    mov [r8 + rcx*8], r11
    dec rcx
    jmp .shift_keys_right
.keys_right_done:
    ret

; Shift Map values right in lockstep. RAX=insertion index.
jx_sorted_shift_values_right:
    mov r8, [rdi + B_AUX]
    mov r9, [rdi + B_TAIL]
    mov rcx, [r9]
.shift_values_right:
    cmp rcx, rax
    jbe .values_right_done
    mov r11, [r8 + rcx*8 - 8]
    mov [r8 + rcx*8], r11
    dec rcx
    jmp .shift_values_right
.values_right_done:
    ret

; Shift keys left after removal. RAX=removed index.
jx_sorted_shift_keys_left:
    mov r8, [rdi + B_BASE]
    mov r9, [rdi + B_TAIL]
    mov rcx, [r9]
    lea r11, [rax + 1]
.shift_keys_left:
    cmp r11, rcx
    jae .keys_left_done
    mov rdx, [r8 + r11*8]
    mov [r8 + r11*8 - 8], rdx
    inc r11
    jmp .shift_keys_left
.keys_left_done:
    ret

; Shift Map values left after removal. RAX=removed index.
jx_sorted_shift_values_left:
    mov r8, [rdi + B_AUX]
    mov r9, [rdi + B_TAIL]
    mov rcx, [r9]
    lea r11, [rax + 1]
.shift_values_left:
    cmp r11, rcx
    jae .values_left_done
    mov rdx, [r8 + r11*8]
    mov [r8 + r11*8 - 8], rdx
    inc r11
    jmp .shift_values_left
.values_left_done:
    ret

; RSI=key, RDX=value. Existing key returns its current value unchanged.
jx_map_emplace_u64:
    mov r8, [rdi + B_BASE]
    mov r9, [rdi + B_TAIL]
    mov r10, [rdi + B_AUX]
    test r8, r8
    jz jx_sorted_fail
    test r9, r9
    jz jx_sorted_fail
    test r10, r10
    jz jx_sorted_fail
    mov rcx, [r9]

    ; Fast append/last-key path for monotonic or repeated inserts.
    test rcx, rcx
    jz .emplace_append
    mov rax, rcx
    dec rax
    cmp rsi, [r8 + rax*8]
    je .emplace_existing_last
    ja .emplace_append

    push rdx
    call jx_sorted_find_u64
    pop r10
    cmp edx, 1
    je .emplace_existing_index
    cmp qword [r9], [rdi + B_CAP]
    jae jx_sorted_fail
    push rax
    call jx_sorted_shift_keys_right
    pop rax
    push rax
    call jx_sorted_shift_values_right
    pop rax
    mov r8, [rdi + B_BASE]
    mov r11, [rdi + B_AUX]
    mov [r8 + rax*8], rsi
    mov [r11 + rax*8], r10
    inc qword [r9]
    mov r11, [rdi + B_HEAD]
    test r11, r11
    jz .emplace_insert_done
    mov [r11], rax
.emplace_insert_done:
    mov rax, r10
    clc
    ret
.emplace_existing_index:
    mov r11, [rdi + B_AUX]
    mov rax, [r11 + rax*8]
    clc
    ret
.emplace_existing_last:
    mov rax, [r10 + rax*8]
    clc
    ret
.emplace_append:
    cmp rcx, [rdi + B_CAP]
    jae jx_sorted_fail
    mov [r8 + rcx*8], rsi
    mov [r10 + rcx*8], rdx
    inc qword [r9]
    mov r11, [rdi + B_HEAD]
    test r11, r11
    jz .emplace_append_done
    mov [r11], rcx
.emplace_append_done:
    mov rax, rdx
    clc
    ret

; RSI=key, RDX=value. Existing key overwrites value memory; absent key inserts a
; new synchronized key/value position into the 2D array.
jx_map_put_u64:
    mov r8, [rdi + B_BASE]
    mov r9, [rdi + B_TAIL]
    mov r10, [rdi + B_AUX]
    test r8, r8
    jz jx_sorted_fail
    test r9, r9
    jz jx_sorted_fail
    test r10, r10
    jz jx_sorted_fail
    mov rcx, [r9]
    test rcx, rcx
    jz .put_append
    mov rax, rcx
    dec rax
    cmp rsi, [r8 + rax*8]
    je .put_replace_last
    ja .put_append

    push rdx
    call jx_sorted_find_u64
    pop r10
    cmp edx, 1
    je .put_replace_index
    cmp qword [r9], [rdi + B_CAP]
    jae jx_sorted_fail
    push rax
    call jx_sorted_shift_keys_right
    pop rax
    push rax
    call jx_sorted_shift_values_right
    pop rax
    mov r8, [rdi + B_BASE]
    mov r11, [rdi + B_AUX]
    mov [r8 + rax*8], rsi
    mov [r11 + rax*8], r10
    inc qword [r9]
    mov r11, [rdi + B_HEAD]
    test r11, r11
    jz .put_insert_done
    mov [r11], rax
.put_insert_done:
    mov rax, r10
    clc
    ret
.put_replace_index:
    mov r11, [rdi + B_AUX]
    mov [r11 + rax*8], r10
    mov rax, r10
    clc
    ret
.put_replace_last:
    mov [r10 + rax*8], rdx
    mov rax, rdx
    clc
    ret
.put_append:
    cmp rcx, [rdi + B_CAP]
    jae jx_sorted_fail
    mov [r8 + rcx*8], rsi
    mov [r10 + rcx*8], rdx
    inc qword [r9]
    mov r11, [rdi + B_HEAD]
    test r11, r11
    jz .put_append_done
    mov [r11], rcx
.put_append_done:
    mov rax, rdx
    clc
    ret

jx_map_get_u64:
    call jx_sorted_find_u64
    cmp edx, 1
    jne jx_sorted_fail
    mov r8, [rdi + B_AUX]
    test r8, r8
    jz jx_sorted_fail
    mov rax, [r8 + rax*8]
    clc
    ret

jx_map_has_u64:
    call jx_sorted_find_u64
    mov eax, edx
    clc
    ret

jx_map_remove_u64:
    call jx_sorted_find_u64
    cmp edx, 1
    jne .map_not_found
    push rax
    call jx_sorted_shift_keys_left
    pop rax
    push rax
    call jx_sorted_shift_values_left
    pop rax
    mov r8, [rdi + B_TAIL]
    dec qword [r8]
    mov r9, [rdi + B_HEAD]
    test r9, r9
    jz .map_removed
    mov rcx, [r8]
    cmp rax, rcx
    cmova rax, rcx
    mov [r9], rax
.map_removed:
    mov rax, 1
    clc
    ret
.map_not_found:
    xor eax, eax
    clc
    ret

; Set ADD is unique ordered insertion. Existing key is dropped immediately.
jx_set_add_u64:
    mov r8, [rdi + B_BASE]
    mov r9, [rdi + B_TAIL]
    test r8, r8
    jz jx_sorted_fail
    test r9, r9
    jz jx_sorted_fail
    mov rcx, [r9]
    test rcx, rcx
    jz .set_append
    mov rax, rcx
    dec rax
    cmp rsi, [r8 + rax*8]
    je .set_present
    ja .set_append

    call jx_sorted_find_u64
    cmp edx, 1
    je .set_present
    cmp qword [r9], [rdi + B_CAP]
    jae jx_sorted_fail
    push rax
    call jx_sorted_shift_keys_right
    pop rax
    mov r8, [rdi + B_BASE]
    mov [r8 + rax*8], rsi
    inc qword [r9]
    mov r10, [rdi + B_HEAD]
    test r10, r10
    jz .set_inserted
    mov [r10], rax
.set_inserted:
    mov rax, 1
    clc
    ret
.set_present:
    mov rax, 1
    clc
    ret
.set_append:
    cmp rcx, [rdi + B_CAP]
    jae jx_sorted_fail
    mov [r8 + rcx*8], rsi
    inc qword [r9]
    mov r10, [rdi + B_HEAD]
    test r10, r10
    jz .set_append_done
    mov [r10], rcx
.set_append_done:
    mov rax, 1
    clc
    ret

jx_set_has_u64:
    call jx_sorted_find_u64
    mov eax, edx
    clc
    ret

jx_set_remove_u64:
    call jx_sorted_find_u64
    cmp edx, 1
    jne .set_not_found
    push rax
    call jx_sorted_shift_keys_left
    pop rax
    mov r8, [rdi + B_TAIL]
    dec qword [r8]
    mov r9, [rdi + B_HEAD]
    test r9, r9
    jz .set_removed
    mov rcx, [r8]
    cmp rax, rcx
    cmova rax, rcx
    mov [r9], rax
.set_removed:
    mov rax, 1
    clc
    ret
.set_not_found:
    xor eax, eax
    clc
    ret

; RSI=requested additional array positions. Allocation/growth remains a cold
; prelinked service. The legacy jx_hash_reserve_u64 symbol is retained only as
; an ABI alias; its behavior is ordered-array capacity checking.
jx_hash_reserve_u64:
jx_sorted_reserve_u64:
    mov r8, [rdi + B_TAIL]
    test r8, r8
    jz jx_sorted_fail
    mov rax, [r8]
    add rax, rsi
    jc jx_sorted_fail
    cmp rax, [rdi + B_CAP]
    ja jx_sorted_fail
    clc
    ret

jx_sorted_fail:
    mov rax, -1
    stc
    ret

; ---------------------------------------------------------------------------
; Bag canonical-boundary flags/generation.
; ---------------------------------------------------------------------------
global jx_bag_dirty
global jx_bag_sync
jx_bag_dirty:
    mov r8, [rdi + B_FLAGS]
    test r8, r8
    jz jx_bag_fail
    or qword [r8], BAG_DIRTY
    xor eax, eax
    clc
    ret

; If dirty, advance the generation exactly once and clear DIRTY.
jx_bag_sync:
    mov r8, [rdi + B_FLAGS]
    mov r9, [rdi + B_GEN]
    test r8, r8
    jz jx_bag_fail
    test r9, r9
    jz jx_bag_fail
    test qword [r8], BAG_DIRTY
    jz .clean
    inc qword [r9]
    and qword [r8], ~BAG_DIRTY
.clean:
    mov rax, [r9]
    clc
    ret

jx_bag_fail:
    mov rax, -1
    stc
    ret

section .note.GNU-stack noalloc noexec nowrite progbits
