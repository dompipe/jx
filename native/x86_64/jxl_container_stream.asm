; JX JXL container stream executor - x86-64 System V ABI
;
; Executes a contiguous stream of fixed six-byte prepared container JXL while
; keeping PC/end/binding-table/register-window/binding-count resident across
; the whole stream. Canonical names and disciplines never enter this loop.
;
; int jx_jxl_container_execute_stream(
;     const uint8_t *begin,          RDI
;     const uint8_t *end,            RSI
;     JxJxlContainerBinding *table,  RDX
;     uint64_t window8[8],           RCX
;     uint64_t binding_count         R8
; );
;
; Returns:
;    0 success (PC reached end exactly)
;   -1 malformed/truncated JXL or bad binding/selector
;   -2 admitted native routine reported failure/slow path
;
; Runtime binding record = 80 bytes, with native function pointer at +0.

bits 64
default rel

%define JXL_CONTAINER_FIRST 040h
%define JXL_CONTAINER_LAST  050h
%define ATTACH_BIT          080h
%define ATTACH_PAYLOAD      07Fh
%define UNUSED_SELECTOR     07Fh
%define BINDING_BYTES       80
%define B_FN                 0

section .text

global jx_jxl_container_execute_stream
jx_jxl_container_execute_stream:
    ; Entry RSP mod 16 = 8. Five callee-saved pushes leave RSP aligned before
    ; the admitted native CALL. Reserve 16 bytes for destination selector and
    ; one spare qword while preserving alignment.
    push rbx
    push r12
    push r13
    push r14
    push r15
    sub rsp, 16

    mov r12, rdi                    ; pc
    mov r13, rsi                    ; end
    mov r14, rdx                    ; runtime binding table
    mov r15, rcx                    ; eight-qword register window
    mov rbx, r8                     ; binding count

.stream_loop:
    cmp r12, r13
    je .stream_ok
    ja .stream_bad

    ; A stream is structurally exact: no trailing partial instruction.
    lea rax, [r12 + 6]
    cmp rax, r13
    ja .stream_bad

    movzx eax, byte [r12]
    test al, ATTACH_BIT
    jnz .stream_bad
    cmp al, JXL_CONTAINER_FIRST
    jb .stream_bad
    cmp al, JXL_CONTAINER_LAST
    ja .stream_bad

    ; 14-bit prepared binding ID.
    movzx eax, byte [r12 + 1]
    test al, ATTACH_BIT
    jz .stream_bad
    and eax, ATTACH_PAYLOAD
    mov r9d, eax

    movzx eax, byte [r12 + 2]
    test al, ATTACH_BIT
    jz .stream_bad
    and eax, ATTACH_PAYLOAD
    shl eax, 7
    or r9d, eax
    cmp r9, rbx
    jae .stream_bad

    ; All selectors remain attachments, including the unused sentinel.
    movzx eax, byte [r12 + 3]
    test al, ATTACH_BIT
    jz .stream_bad
    movzx eax, byte [r12 + 4]
    test al, ATTACH_BIT
    jz .stream_bad
    movzx eax, byte [r12 + 5]
    test al, ATTACH_BIT
    jz .stream_bad

    ; binding = table + id*80 = table + (id*5)*16.
    mov rax, r9
    lea r9, [rax + rax*4]
    shl r9, 4
    add r9, r14
    mov rax, [r9 + B_FN]
    test rax, rax
    jz .stream_bad

    ; src0 -> RSI.
    movzx eax, byte [r12 + 3]
    and eax, ATTACH_PAYLOAD
    cmp eax, UNUSED_SELECTOR
    je .src0_unused
    cmp eax, 7
    ja .stream_bad
    mov rsi, [r15 + rax*8]
    jmp .src0_done
.src0_unused:
    xor esi, esi
.src0_done:

    ; src1 -> RDX.
    movzx eax, byte [r12 + 4]
    and eax, ATTACH_PAYLOAD
    cmp eax, UNUSED_SELECTOR
    je .src1_unused
    cmp eax, 7
    ja .stream_bad
    mov rdx, [r15 + rax*8]
    jmp .src1_done
.src1_unused:
    xor edx, edx
.src1_done:

    ; Save destination selector across the native call without consuming one
    ; of the five resident stream registers.
    movzx eax, byte [r12 + 5]
    and eax, ATTACH_PAYLOAD
    cmp eax, UNUSED_SELECTOR
    je .dst_valid
    cmp eax, 7
    ja .stream_bad
.dst_valid:
    mov [rsp], rax

    mov rdi, r9                     ; prepared runtime binding
    call qword [r9 + B_FN]
    jc .stream_native_fail

    mov rcx, [rsp]
    cmp ecx, UNUSED_SELECTOR
    je .advance
    mov [r15 + rcx*8], rax

.advance:
    add r12, 6
    jmp .stream_loop

.stream_ok:
    xor eax, eax
    jmp .stream_return

.stream_bad:
    mov eax, -1
    jmp .stream_return

.stream_native_fail:
    mov eax, -2

.stream_return:
    add rsp, 16
    pop r15
    pop r14
    pop r13
    pop r12
    pop rbx
    ret

section .note.GNU-stack noalloc noexec nowrite progbits
