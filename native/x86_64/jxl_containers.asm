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
;   +16 head/count-origin pointer (qword*)
;   +24 tail/count pointer (qword*)
;   +32 capacity (elements/slots)
;   +40 mask (power-of-two ring/hash)
;   +48 generation pointer (qword*)
;   +56 flags pointer (qword*)
;   +64 aux pointer
;   +72 aux2 pointer
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
    jae .record_fail
    mov rax, [rdi + B_BASE]
    mov rax, [rax + rsi*8]
    clc
    ret
jx_record_put_u64:
    cmp rsi, [rdi + B_CAP]
    jae .record_fail
    mov rax, [rdi + B_BASE]
    mov [rax + rsi*8], rdx
    mov rax, rdx
    clc
    ret
.record_fail:
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
    jz .vec_fail
    mov rcx, [r8]
    cmp rcx, [rdi + B_CAP]
    jae .vec_fail
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
    jz .vec_fail
    mov rcx, [r8]
    test rcx, rcx
    jz .vec_fail
    dec rcx
    mov [r8], rcx
    mov rax, [rdi + B_BASE]
    mov rax, [rax + rcx*8]
    clc
    ret

jx_vector_get_u64:
    mov r8, [rdi + B_TAIL]
    test r8, r8
    jz .vec_fail
    cmp rsi, [r8]
    jae .vec_fail
    mov rax, [rdi + B_BASE]
    mov rax, [rax + rsi*8]
    clc
    ret

jx_vector_put_u64:
    mov r8, [rdi + B_TAIL]
    test r8, r8
    jz .vec_fail
    cmp rsi, [r8]
    jae .vec_fail
    mov rax, [rdi + B_BASE]
    mov [rax + rsi*8], rdx
    mov rax, rdx
    clc
    ret

; RSI=index, RDX=value. One backwards tail shift, then one store.
jx_vector_emplace_u64:
    mov r8, [rdi + B_TAIL]
    test r8, r8
    jz .vec_fail
    mov rcx, [r8]                 ; count
    cmp rsi, rcx
    ja .vec_fail
    cmp rcx, [rdi + B_CAP]
    jae .vec_fail
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
    jz .vec_fail
    mov rcx, [r8]
    test rcx, rcx
    jz .vec_fail
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
    jz .vec_fail
    mov rax, [r8]
    add rax, rsi
    jc .vec_fail
    cmp rax, [rdi + B_CAP]
    ja .vec_fail
    clc
    ret
.vec_fail:
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
    jz .ring_fail
    test r9, r9
    jz .ring_fail
    mov rcx, [r9]
    mov rax, rcx
    sub rax, [r8]
    cmp rax, [rdi + B_CAP]
    jae .ring_fail
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
    jz .ring_fail
    test r9, r9
    jz .ring_fail
    mov rcx, [r8]
    cmp rcx, [r9]
    je .ring_fail
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
    jz .ring_fail
    test r9, r9
    jz .ring_fail
    mov rcx, [r8]
    cmp rcx, [r9]
    je .ring_fail
    and rcx, [rdi + B_MASK]
    mov rax, [rdi + B_BASE]
    mov rax, [rax + rcx*8]
    clc
    ret

jx_deque_push_front_u64:
    mov r8, [rdi + B_HEAD]
    mov r9, [rdi + B_TAIL]
    test r8, r8
    jz .ring_fail
    test r9, r9
    jz .ring_fail
    mov rax, [r9]
    sub rax, [r8]
    cmp rax, [rdi + B_CAP]
    jae .ring_fail
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
    jz .ring_fail
    test r9, r9
    jz .ring_fail
    mov rcx, [r9]
    cmp rcx, [r8]
    je .ring_fail
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
    jz .ring_fail
    test r9, r9
    jz .ring_fail
    mov rcx, [r9]
    cmp rcx, [r8]
    je .ring_fail
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
    jz .ring_fail
    test r9, r9
    jz .ring_fail
    mov rax, [r9]
    sub rax, [r8]
    add rax, rsi
    jc .ring_fail
    cmp rax, [rdi + B_CAP]
    ja .ring_fail
    clc
    ret
.ring_fail:
    mov rax, -1
    stc
    ret

; ---------------------------------------------------------------------------
; Map/set: open-addressed u64 slots, 24 bytes each:
;   +0 state: 0 empty, 1 occupied, 2 tombstone
;   +8 key
;  +16 value
; Capacity is power-of-two; mask = capacity - 1.
; ---------------------------------------------------------------------------
global jx_map_probe_u64
global jx_map_emplace_u64
global jx_map_get_u64
global jx_map_put_u64
global jx_map_has_u64
global jx_map_remove_u64
global jx_set_add_u64
global jx_set_has_u64
global jx_set_remove_u64
global jx_hash_reserve_u64

; RDI=binding, RSI=key
; return RAX=slot ptr, EDX=0 insertion slot / 1 found / 2 full
jx_map_probe_u64:
    mov r8, [rdi + B_BASE]
    test r8, r8
    jz .probe_full
    mov rcx, [rdi + B_CAP]
    test rcx, rcx
    jz .probe_full
    mov rax, rsi
    mov r10, 11400714819323198485
    imul rax, r10
    and rax, [rdi + B_MASK]
    xor r11, r11                    ; first tombstone pointer
.probe_loop:
    mov r9, rax
    imul r9, r9, 24
    add r9, r8
    mov r10, [r9]
    test r10, r10
    jz .probe_empty
    cmp r10, 2
    je .probe_tomb
    cmp qword [r9 + 8], rsi
    je .probe_found
.probe_next:
    inc rax
    and rax, [rdi + B_MASK]
    dec rcx
    jnz .probe_loop
    test r11, r11
    jnz .probe_tomb_return
.probe_full:
    xor rax, rax
    mov edx, 2
    ret
.probe_tomb:
    test r11, r11
    cmovz r11, r9
    jmp .probe_next
.probe_empty:
    test r11, r11
    cmovnz r9, r11
    mov rax, r9
    xor edx, edx
    ret
.probe_tomb_return:
    mov rax, r11
    xor edx, edx
    ret
.probe_found:
    mov rax, r9
    mov edx, 1
    ret

; RSI=key, RDX=value; existing value is returned unchanged.
jx_map_emplace_u64:
    push rdx
    call jx_map_probe_u64
    pop rcx
    cmp edx, 2
    je .hash_fail
    cmp edx, 1
    je .map_existing
    mov [rax + 8], rsi
    mov [rax + 16], rcx
    mov qword [rax], 1
    mov rax, rcx
    clc
    ret
.map_existing:
    mov rax, [rax + 16]
    clc
    ret

jx_map_put_u64:
    push rdx
    call jx_map_probe_u64
    pop rcx
    cmp edx, 2
    je .hash_fail
    cmp edx, 1
    je .map_replace
    mov [rax + 8], rsi
    mov qword [rax], 1
.map_replace:
    mov [rax + 16], rcx
    mov rax, rcx
    clc
    ret

jx_map_get_u64:
    call jx_map_probe_u64
    cmp edx, 1
    jne .hash_fail
    mov rax, [rax + 16]
    clc
    ret

jx_map_has_u64:
    call jx_map_probe_u64
    xor eax, eax
    cmp edx, 1
    sete al
    clc
    ret

jx_map_remove_u64:
    call jx_map_probe_u64
    cmp edx, 1
    jne .hash_not_found
    mov qword [rax], 2
    mov rax, 1
    clc
    ret
.hash_not_found:
    xor eax, eax
    clc
    ret

jx_set_add_u64:
    mov rdx, 1
    jmp jx_map_emplace_u64
jx_set_has_u64:
    jmp jx_map_has_u64
jx_set_remove_u64:
    jmp jx_map_remove_u64

; Hot capacity guard. B_AUX may point to an admitted count qword. When absent,
; reserve cannot be proven and the prelinked slow path is requested.
jx_hash_reserve_u64:
    mov r8, [rdi + B_AUX]
    test r8, r8
    jz .hash_fail
    mov rax, [r8]
    add rax, rsi
    jc .hash_fail
    cmp rax, [rdi + B_CAP]
    ja .hash_fail
    clc
    ret
.hash_fail:
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
    jz .bag_fail
    or qword [r8], BAG_DIRTY
    xor eax, eax
    clc
    ret

; If dirty, advance the generation exactly once and clear DIRTY.
jx_bag_sync:
    mov r8, [rdi + B_FLAGS]
    mov r9, [rdi + B_GEN]
    test r8, r8
    jz .bag_fail
    test r9, r9
    jz .bag_fail
    test qword [r8], BAG_DIRTY
    jz .bag_clean
    inc qword [r9]
    and qword [r8], ~BAG_DIRTY
.bag_clean:
    mov rax, [r9]
    clc
    ret
.bag_fail:
    mov rax, -1
    stc
    ret

section .note.GNU-stack noalloc noexec nowrite progbits
