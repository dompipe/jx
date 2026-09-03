; JX JXL keyed-vector Map backend - x86-64 System V ABI
;
; Canonical Map law:
;   Map is one ordered Vector of fixed-width entries.
;   Each v1 entry is exactly [u64 key, u64 value] (16 bytes).
;   B_BASE -> Entry[]
;   B_HEAD -> locality cursor index (optional qword*)
;   B_TAIL -> element count (qword*)
;   B_CAP  -> capacity in entries
;   B_AUX  -> unused by keyed-vector Map
;
; FIND performs the same cursor-marquee then lower_bound strategy as Set.
; PUT overwrites entry.value when the key exists; otherwise it vector-inserts
; one complete 16-byte entry. No hash/bucket/probe/tombstone structure exists.
;
; The older split keys[] + values[] routines remain in jxl_containers.asm under
; jx_map_* symbols only so the two physical layouts can be benchmarked later.
; The native JXL target table selects the routines in this file.

bits 64
default rel

%define B_BASE     8
%define B_HEAD    16
%define B_TAIL    24
%define B_CAP     32

section .text

global jx_map_vector_find_u64
global jx_map_vector_emplace_u64
global jx_map_vector_get_u64
global jx_map_vector_put_u64
global jx_map_vector_has_u64
global jx_map_vector_remove_u64

; RDI=binding, RSI=key
; RAX=lower_bound entry index, EDX=1 found / 0 absent, CF=0.
jx_map_vector_find_u64:
    mov r8, [rdi + B_BASE]
    mov r9, [rdi + B_TAIL]
    test r8, r8
    jz jx_map_vector_fail
    test r9, r9
    jz jx_map_vector_fail
    mov rcx, [r9]
    test rcx, rcx
    jz .empty

    ; Marquee: current cursor, then the immediately following entry.
    mov r10, [rdi + B_HEAD]
    test r10, r10
    jz .binary
    mov rax, [r10]
    cmp rax, rcx
    jae .binary
    lea r11, [rax + rax]
    cmp rsi, [r8 + r11*8]
    je .found_cursor
    jb .binary

    lea rdx, [rax + 1]
    cmp rdx, rcx
    jae .absent_end
    lea r11, [rdx + rdx]
    cmp rsi, [r8 + r11*8]
    je .found_next
    jb .absent_next

.binary:
    xor eax, eax                  ; lo
    mov rdx, rcx                  ; hi
.bin_loop:
    cmp rax, rdx
    jae .bin_done
    mov r11, rax
    add r11, rdx
    shr r11, 1                    ; mid entry index
    lea r10, [r11 + r11]          ; qword offset = mid*2
    cmp qword [r8 + r10*8], rsi
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
    lea r11, [rax + rax]
    cmp qword [r8 + r11*8], rsi
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

; RAX=insertion entry index. Shift complete [key,value] entries one position
; right. This is Vector insertion with a 16-byte element width.
jx_map_vector_shift_right:
    mov r8, [rdi + B_BASE]
    mov r9, [rdi + B_TAIL]
    mov rcx, [r9]
.shift:
    cmp rcx, rax
    jbe .done
    lea r10, [rcx + rcx]          ; destination qword offset
    mov r11, [r8 + r10*8 - 16]   ; source key
    mov rdx, [r8 + r10*8 - 8]    ; source value
    mov [r8 + r10*8], r11
    mov [r8 + r10*8 + 8], rdx
    dec rcx
    jmp .shift
.done:
    ret

; RAX=removed entry index. Shift complete entries left over the removed cell.
jx_map_vector_shift_left:
    mov r8, [rdi + B_BASE]
    mov r9, [rdi + B_TAIL]
    mov rcx, [r9]
    lea r11, [rax + 1]
.shift:
    cmp r11, rcx
    jae .done
    lea r10, [r11 + r11]
    mov rdx, [r8 + r10*8]
    mov [r8 + r10*8 - 16], rdx
    mov rdx, [r8 + r10*8 + 8]
    mov [r8 + r10*8 - 8], rdx
    inc r11
    jmp .shift
.done:
    ret

; RSI=key, RDX=value. Existing key returns the current value unchanged.
jx_map_vector_emplace_u64:
    mov r8, [rdi + B_BASE]
    mov r9, [rdi + B_TAIL]
    test r8, r8
    jz jx_map_vector_fail
    test r9, r9
    jz jx_map_vector_fail
    mov rcx, [r9]
    test rcx, rcx
    jz .append

    mov rax, rcx
    dec rax
    lea r11, [rax + rax]
    cmp rsi, [r8 + r11*8]
    je .existing_last
    ja .append

    push rdx
    call jx_map_vector_find_u64
    pop r10
    jc jx_map_vector_fail
    cmp edx, 1
    je .existing_index
    mov r9, [rdi + B_TAIL]
    mov rcx, [r9]
    cmp rcx, [rdi + B_CAP]
    jae jx_map_vector_fail
    push r10
    call jx_map_vector_shift_right
    pop r10
    mov r8, [rdi + B_BASE]
    lea r11, [rax + rax]
    mov [r8 + r11*8], rsi
    mov [r8 + r11*8 + 8], r10
    inc qword [r9]
    mov r11, [rdi + B_HEAD]
    test r11, r11
    jz .insert_done
    mov [r11], rax
.insert_done:
    mov rax, r10
    clc
    ret
.existing_index:
    mov r8, [rdi + B_BASE]
    lea r11, [rax + rax]
    mov rax, [r8 + r11*8 + 8]
    clc
    ret
.existing_last:
    mov rax, [r8 + r11*8 + 8]
    clc
    ret
.append:
    cmp rcx, [rdi + B_CAP]
    jae jx_map_vector_fail
    lea r11, [rcx + rcx]
    mov [r8 + r11*8], rsi
    mov [r8 + r11*8 + 8], rdx
    inc qword [r9]
    mov r10, [rdi + B_HEAD]
    test r10, r10
    jz .append_done
    mov [r10], rcx
.append_done:
    mov rax, rdx
    clc
    ret

; RSI=key, RDX=value. Found means one overwrite of the value qword. Missing
; means Vector-insert one complete [key,value] entry.
jx_map_vector_put_u64:
    mov r8, [rdi + B_BASE]
    mov r9, [rdi + B_TAIL]
    test r8, r8
    jz jx_map_vector_fail
    test r9, r9
    jz jx_map_vector_fail
    mov rcx, [r9]
    test rcx, rcx
    jz .append

    mov rax, rcx
    dec rax
    lea r11, [rax + rax]
    cmp rsi, [r8 + r11*8]
    je .replace_last
    ja .append

    push rdx
    call jx_map_vector_find_u64
    pop r10
    jc jx_map_vector_fail
    cmp edx, 1
    je .replace_index
    mov r9, [rdi + B_TAIL]
    mov rcx, [r9]
    cmp rcx, [rdi + B_CAP]
    jae jx_map_vector_fail
    push r10
    call jx_map_vector_shift_right
    pop r10
    mov r8, [rdi + B_BASE]
    lea r11, [rax + rax]
    mov [r8 + r11*8], rsi
    mov [r8 + r11*8 + 8], r10
    inc qword [r9]
    mov r11, [rdi + B_HEAD]
    test r11, r11
    jz .insert_done
    mov [r11], rax
.insert_done:
    mov rax, r10
    clc
    ret
.replace_index:
    mov r8, [rdi + B_BASE]
    lea r11, [rax + rax]
    mov [r8 + r11*8 + 8], r10
    mov rax, r10
    clc
    ret
.replace_last:
    mov [r8 + r11*8 + 8], rdx
    mov rax, rdx
    clc
    ret
.append:
    cmp rcx, [rdi + B_CAP]
    jae jx_map_vector_fail
    lea r11, [rcx + rcx]
    mov [r8 + r11*8], rsi
    mov [r8 + r11*8 + 8], rdx
    inc qword [r9]
    mov r10, [rdi + B_HEAD]
    test r10, r10
    jz .append_done
    mov [r10], rcx
.append_done:
    mov rax, rdx
    clc
    ret

jx_map_vector_get_u64:
    call jx_map_vector_find_u64
    jc jx_map_vector_fail
    cmp edx, 1
    jne jx_map_vector_fail
    mov r8, [rdi + B_BASE]
    lea r11, [rax + rax]
    mov rax, [r8 + r11*8 + 8]
    clc
    ret

jx_map_vector_has_u64:
    call jx_map_vector_find_u64
    jc jx_map_vector_fail
    mov eax, edx
    clc
    ret

jx_map_vector_remove_u64:
    call jx_map_vector_find_u64
    jc jx_map_vector_fail
    cmp edx, 1
    jne .not_found
    push rax
    call jx_map_vector_shift_left
    pop rax
    mov r8, [rdi + B_TAIL]
    dec qword [r8]
    mov r9, [rdi + B_HEAD]
    test r9, r9
    jz .removed
    mov rcx, [r8]
    cmp rax, rcx
    cmova rax, rcx
    mov [r9], rax
.removed:
    mov rax, 1
    clc
    ret
.not_found:
    xor eax, eax
    clc
    ret

jx_map_vector_fail:
    mov rax, -1
    stc
    ret

section .note.GNU-stack noalloc noexec nowrite progbits
