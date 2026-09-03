; JX JXL fixed-width container executor - x86-64 System V ABI
;
; Native JXL container hot path:
;   JXL[6 bytes] -> prepared binding -> assembly fn -> Bag memory
;
; No PHP object, alias lookup, discipline lookup, method lookup, schema lookup,
; or variable-length instruction parsing occurs here.
;
; Input:
;   RDI = PC, pointing at a 6-byte JXL container instruction
;   RSI = base of runtime PreparedContainerBinding records
;   RDX = pointer to current eight-qword JXL register window
;   RCX = runtime binding count
;
; Output:
;   RAX = next PC on success
;   CF  = 0 on success
;   CF  = 1 on malformed instruction, invalid binding/selector, or native
;         container slow-path/failure. On native failure RAX retains the
;         operation's error value.
;
; Fixed instruction:
;   +0 executable opcode 40h..50h
;   +1 attached binding id bits 0..6
;   +2 attached binding id bits 7..13
;   +3 attached src0 selector: 0..7, 7Fh = unused
;   +4 attached src1 selector: 0..7, 7Fh = unused
;   +5 attached dst  selector: 0..7, 7Fh = discard result
;
; Every operand byte has bit 7 set. This directly follows the JXL law:
;   0xxxxxxx executable
;   1xxxxxxx attached data
;
; Runtime binding record is 80 bytes. Offset zero is the already-resolved
; native function pointer. The remaining layout is defined by jxl_containers.asm.

bits 64
default rel

%define JXL_CONTAINER_FIRST 040h
%define JXL_CONTAINER_LAST  050h
%define ATTACH_BIT          080h
%define ATTACH_PAYLOAD      07Fh
%define UNUSED_SELECTOR     07Fh
%define BINDING_BYTES       80
%define B_FN                0

section .text

global jx_jxl_container_execute
jx_jxl_container_execute:
    ; Preserve SysV callee-saved registers and also align RSP for the native
    ; function call. Entry RSP mod 16 = 8; five pushes leave it 0 before CALL.
    push rbx
    push r12
    push r13
    push r14
    push r15

    mov r12, rdi                    ; pc
    mov r13, rsi                    ; runtime binding table
    mov r14, rdx                    ; 8-qword local register window
    mov r15, rcx                    ; binding count, later reused for dst

    ; Executable byte must have bit 7 clear and belong to the reserved
    ; contiguous container family.
    movzx eax, byte [r12]
    test al, ATTACH_BIT
    jnz .bad
    cmp al, JXL_CONTAINER_FIRST
    jb .bad
    cmp al, JXL_CONTAINER_LAST
    ja .bad

    ; Decode 14-bit binding ID from two attached 7-bit payloads.
    movzx eax, byte [r12 + 1]
    test al, ATTACH_BIT
    jz .bad
    and eax, ATTACH_PAYLOAD
    mov ebx, eax

    movzx eax, byte [r12 + 2]
    test al, ATTACH_BIT
    jz .bad
    and eax, ATTACH_PAYLOAD
    shl eax, 7
    or ebx, eax

    cmp rbx, r15
    jae .bad

    ; Every selector byte must be an attachment even when the selector is the
    ; unused sentinel.
    movzx eax, byte [r12 + 3]
    test al, ATTACH_BIT
    jz .bad
    movzx eax, byte [r12 + 4]
    test al, ATTACH_BIT
    jz .bad
    movzx eax, byte [r12 + 5]
    test al, ATTACH_BIT
    jz .bad

    ; binding = base + id * 80 = base + (id * 5) * 16
    mov rax, rbx
    lea rbx, [rax + rax*4]
    shl rbx, 4
    add rbx, r13

    mov rax, [rbx + B_FN]
    test rax, rax
    jz .bad

    ; Source 0 -> RSI, or zero when unused. Admission has already checked the
    ; opcode's source/result shape; native execution only validates selector
    ; bounds.
    movzx eax, byte [r12 + 3]
    and eax, ATTACH_PAYLOAD
    cmp eax, UNUSED_SELECTOR
    je .src0_unused
    cmp eax, 7
    ja .bad
    mov rsi, [r14 + rax*8]
    jmp .src0_done
.src0_unused:
    xor esi, esi
.src0_done:

    ; Source 1 -> RDX.
    movzx eax, byte [r12 + 4]
    and eax, ATTACH_PAYLOAD
    cmp eax, UNUSED_SELECTOR
    je .src1_unused
    cmp eax, 7
    ja .bad
    mov rdx, [r14 + rax*8]
    jmp .src1_done
.src1_unused:
    xor edx, edx
.src1_done:

    ; Keep destination selector in callee-saved R15 across the native call.
    movzx r15d, byte [r12 + 5]
    and r15d, ATTACH_PAYLOAD
    cmp r15d, UNUSED_SELECTOR
    je .dst_ok
    cmp r15d, 7
    ja .bad
.dst_ok:

    mov rdi, rbx                    ; PreparedContainerBinding*
    call qword [rbx + B_FN]
    jc .native_fail

    cmp r15d, UNUSED_SELECTOR
    je .no_result
    mov [r14 + r15*8], rax
.no_result:
    lea rax, [r12 + 6]

    pop r15
    pop r14
    pop r13
    pop r12
    pop rbx
    clc
    ret

.native_fail:
    ; Preserve native RAX while restoring our frame.
    pop r15
    pop r14
    pop r13
    pop r12
    pop rbx
    stc
    ret

.bad:
    mov rax, -1
    pop r15
    pop r14
    pop r13
    pop r12
    pop rbx
    stc
    ret

section .note.GNU-stack noalloc noexec nowrite progbits
