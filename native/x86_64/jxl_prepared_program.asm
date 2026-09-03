; JX global prepared JXL executor - x86-64 System V ABI
;
; One six-byte fixed-width program can mix:
;   0x20..0x37 prepared register/control instructions
;   0x40..0x50 prepared native Bag instructions
;
; Contiguous Bag instructions are discovered as one region and handed to
; jx_jxl_container_execute_stream exactly once. Arithmetic/control and Bag
; regions share the same R0..R7 qword window.
;
; int jx_jxl_prepared_execute(
;     const uint8_t *begin,          RDI
;     const uint8_t *end,            RSI
;     JxJxlContainerBinding *table,  RDX
;     uint64_t window8[8],           RCX
;     uint64_t binding_count         R8
; );
;
; Returns 0 success, -1 malformed program/reference, -2 arithmetic/native
; failure (divide by zero, native slow/failure path).

bits 64
default rel

extern jx_jxl_container_execute_stream

%define P_FIRST      020h
%define P_MOVI       020h
%define P_MOV        021h
%define P_ADD        022h
%define P_SUB        023h
%define P_MUL        024h
%define P_DIV        025h
%define P_MOD        026h
%define P_EQ         027h
%define P_NE         028h
%define P_LT         029h
%define P_LE         02Ah
%define P_GT         02Bh
%define P_GE         02Ch
%define P_BAND       02Dh
%define P_BOR        02Eh
%define P_BXOR       02Fh
%define P_SHL        030h
%define P_SHR        031h
%define P_NEG        032h
%define P_NOT        033h
%define P_JMP        034h
%define P_JZ         035h
%define P_JNZ        036h
%define P_HALT       037h
%define P_LAST       037h
%define C_FIRST      040h
%define C_LAST       050h
%define ATTACH       080h
%define PAYLOAD      07Fh
%define UNUSED       07Fh

section .text

global jx_jxl_prepared_execute
jx_jxl_prepared_execute:
    ; Five callee-saved pushes align RSP for calls. Keep the immutable program
    ; base and current region end in a 16-byte local area.
    push rbx
    push r12
    push r13
    push r14
    push r15
    sub rsp, 16

    mov r12, rdi                    ; pc
    mov r13, rsi                    ; end
    mov r14, rdx                    ; admitted bindings
    mov r15, rcx                    ; R0..R7 window
    mov rbx, r8                     ; binding count
    mov [rsp], rdi                  ; immutable program base

.loop:
    cmp r12, r13
    je .ok
    ja .bad
    lea rax, [r12 + 6]
    cmp rax, r13
    ja .bad

    movzx eax, byte [r12]
    test al, ATTACH
    jnz .bad

    cmp al, C_FIRST
    jb .prepared
    cmp al, C_LAST
    jbe .container_region
    jmp .bad

.prepared:
    cmp al, P_FIRST
    jb .bad
    cmp al, P_LAST
    ja .bad

    ; Every non-opcode byte in prepared global JXL is an attachment.
    movzx ecx, byte [r12 + 1]
    test cl, ATTACH
    jz .bad
    movzx ecx, byte [r12 + 2]
    test cl, ATTACH
    jz .bad
    movzx ecx, byte [r12 + 3]
    test cl, ATTACH
    jz .bad
    movzx ecx, byte [r12 + 4]
    test cl, ATTACH
    jz .bad
    movzx ecx, byte [r12 + 5]
    test cl, ATTACH
    jz .bad

    cmp al, P_MOVI
    je .movi
    cmp al, P_MOV
    je .mov
    cmp al, P_ADD
    je .add
    cmp al, P_SUB
    je .sub
    cmp al, P_MUL
    je .mul
    cmp al, P_DIV
    je .div
    cmp al, P_MOD
    je .mod
    cmp al, P_EQ
    je .eq
    cmp al, P_NE
    je .ne
    cmp al, P_LT
    je .lt
    cmp al, P_LE
    je .le
    cmp al, P_GT
    je .gt
    cmp al, P_GE
    je .ge
    cmp al, P_BAND
    je .band
    cmp al, P_BOR
    je .bor
    cmp al, P_BXOR
    je .bxor
    cmp al, P_SHL
    je .shl
    cmp al, P_SHR
    je .shr
    cmp al, P_NEG
    je .neg
    cmp al, P_NOT
    je .not
    cmp al, P_JMP
    je .jmp
    cmp al, P_JZ
    je .jz
    cmp al, P_JNZ
    je .jnz
    cmp al, P_HALT
    je .ok
    jmp .bad

; ---- operand helpers are inlined so no call/return pollutes the hot loop. ----
; Binary prepared layout: op,dst,src0,src1,unused,unused.
%macro LOAD_BINARY 0
    movzx r9d, byte [r12 + 1]
    and r9d, PAYLOAD
    cmp r9d, 7
    ja .bad
    movzx r10d, byte [r12 + 2]
    and r10d, PAYLOAD
    cmp r10d, 7
    ja .bad
    movzx r11d, byte [r12 + 3]
    and r11d, PAYLOAD
    cmp r11d, 7
    ja .bad
    mov rdx, [r15 + r10*8]
    mov rcx, [r15 + r11*8]
%endmacro

%macro STORE_ADVANCE 0
    mov [r15 + r9*8], rax
    add r12, 6
    jmp .loop
%endmacro

.movi:
    movzx r9d, byte [r12 + 1]
    and r9d, PAYLOAD
    cmp r9d, 7
    ja .bad
    xor eax, eax
    movzx ecx, byte [r12 + 2]
    and ecx, PAYLOAD
    or eax, ecx
    movzx ecx, byte [r12 + 3]
    and ecx, PAYLOAD
    shl ecx, 7
    or eax, ecx
    movzx ecx, byte [r12 + 4]
    and ecx, PAYLOAD
    shl ecx, 14
    or eax, ecx
    movzx ecx, byte [r12 + 5]
    and ecx, PAYLOAD
    shl ecx, 21
    or eax, ecx                    ; EAX = 28-bit zigzag
    mov ecx, eax
    shr eax, 1
    and ecx, 1
    neg rcx
    xor rax, rcx                   ; zigzag decode: (z>>1) ^ -(z&1)
    mov [r15 + r9*8], rax
    add r12, 6
    jmp .loop

.mov:
    movzx r9d, byte [r12 + 1]
    and r9d, PAYLOAD
    cmp r9d, 7
    ja .bad
    movzx r10d, byte [r12 + 2]
    and r10d, PAYLOAD
    cmp r10d, 7
    ja .bad
    mov rax, [r15 + r10*8]
    STORE_ADVANCE

.add:
    LOAD_BINARY
    mov rax, rdx
    add rax, rcx
    STORE_ADVANCE
.sub:
    LOAD_BINARY
    mov rax, rdx
    sub rax, rcx
    STORE_ADVANCE
.mul:
    LOAD_BINARY
    mov rax, rdx
    imul rax, rcx
    STORE_ADVANCE
.div:
    LOAD_BINARY
    test rcx, rcx
    jz .fail
    mov rax, 08000000000000000h
    cmp rdx, rax
    jne .div_safe
    cmp rcx, -1
    je .fail
.div_safe:
    mov rax, rdx
    cqo
    idiv rcx
    STORE_ADVANCE
.mod:
    LOAD_BINARY
    test rcx, rcx
    jz .fail
    mov rax, 08000000000000000h
    cmp rdx, rax
    jne .mod_safe
    cmp rcx, -1
    je .fail
.mod_safe:
    mov rax, rdx
    cqo
    idiv rcx
    mov rax, rdx
    STORE_ADVANCE
.eq:
    LOAD_BINARY
    xor eax, eax
    cmp rdx, rcx
    sete al
    STORE_ADVANCE
.ne:
    LOAD_BINARY
    xor eax, eax
    cmp rdx, rcx
    setne al
    STORE_ADVANCE
.lt:
    LOAD_BINARY
    xor eax, eax
    cmp rdx, rcx
    setl al
    STORE_ADVANCE
.le:
    LOAD_BINARY
    xor eax, eax
    cmp rdx, rcx
    setle al
    STORE_ADVANCE
.gt:
    LOAD_BINARY
    xor eax, eax
    cmp rdx, rcx
    setg al
    STORE_ADVANCE
.ge:
    LOAD_BINARY
    xor eax, eax
    cmp rdx, rcx
    setge al
    STORE_ADVANCE
.band:
    LOAD_BINARY
    mov rax, rdx
    and rax, rcx
    STORE_ADVANCE
.bor:
    LOAD_BINARY
    mov rax, rdx
    or rax, rcx
    STORE_ADVANCE
.bxor:
    LOAD_BINARY
    mov rax, rdx
    xor rax, rcx
    STORE_ADVANCE
.shl:
    LOAD_BINARY
    mov rax, rdx
    ; RCX already contains the shift count; x86 uses CL modulo 64.
    shl rax, cl
    STORE_ADVANCE
.shr:
    LOAD_BINARY
    mov rax, rdx
    sar rax, cl
    STORE_ADVANCE

.neg:
    movzx r9d, byte [r12 + 1]
    and r9d, PAYLOAD
    cmp r9d, 7
    ja .bad
    movzx r10d, byte [r12 + 2]
    and r10d, PAYLOAD
    cmp r10d, 7
    ja .bad
    mov rax, [r15 + r10*8]
    neg rax
    STORE_ADVANCE
.not:
    movzx r9d, byte [r12 + 1]
    and r9d, PAYLOAD
    cmp r9d, 7
    ja .bad
    movzx r10d, byte [r12 + 2]
    and r10d, PAYLOAD
    cmp r10d, 7
    ja .bad
    xor eax, eax
    cmp qword [r15 + r10*8], 0
    sete al
    STORE_ADVANCE

.jmp:
    xor eax, eax
    movzx ecx, byte [r12 + 1]
    and ecx, PAYLOAD
    or eax, ecx
    movzx ecx, byte [r12 + 2]
    and ecx, PAYLOAD
    shl ecx, 7
    or eax, ecx
    movzx ecx, byte [r12 + 3]
    and ecx, PAYLOAD
    shl ecx, 14
    or eax, ecx
    movzx ecx, byte [r12 + 4]
    and ecx, PAYLOAD
    shl ecx, 21
    or eax, ecx
    jmp .branch_target

.jz:
    movzx r9d, byte [r12 + 1]
    and r9d, PAYLOAD
    cmp r9d, 7
    ja .bad
    cmp qword [r15 + r9*8], 0
    jne .advance_core
    jmp .conditional_target
.jnz:
    movzx r9d, byte [r12 + 1]
    and r9d, PAYLOAD
    cmp r9d, 7
    ja .bad
    cmp qword [r15 + r9*8], 0
    je .advance_core
.conditional_target:
    xor eax, eax
    movzx ecx, byte [r12 + 2]
    and ecx, PAYLOAD
    or eax, ecx
    movzx ecx, byte [r12 + 3]
    and ecx, PAYLOAD
    shl ecx, 7
    or eax, ecx
    movzx ecx, byte [r12 + 4]
    and ecx, PAYLOAD
    shl ecx, 14
    or eax, ecx
    movzx ecx, byte [r12 + 5]
    and ecx, PAYLOAD
    shl ecx, 21
    or eax, ecx
.branch_target:
    test eax, 5                       ; targets must be divisible by 6; cheap
    ; bit test alone is insufficient for division by 6, do exact check below.
    mov r10d, eax
    xor edx, edx
    mov ecx, 6
    mov eax, r10d
    div ecx
    test edx, edx
    jnz .bad
    mov rax, [rsp]
    add rax, r10
    cmp rax, r13
    ja .bad
    mov r12, rax
    jmp .loop

.advance_core:
    add r12, 6
    jmp .loop

.container_region:
    ; Scan only first bytes to find the maximal contiguous container run. The
    ; resident container executor validates every attachment and binding.
    mov r10, r12
.find_region_end:
    lea rax, [r10 + 6]
    cmp rax, r13
    ja .bad
    add r10, 6
    cmp r10, r13
    je .run_region
    movzx eax, byte [r10]
    cmp al, C_FIRST
    jb .run_region
    cmp al, C_LAST
    jbe .find_region_end
.run_region:
    mov [rsp + 8], r10
    mov rdi, r12
    mov rsi, r10
    mov rdx, r14
    mov rcx, r15
    mov r8, rbx
    call jx_jxl_container_execute_stream
    test eax, eax
    jnz .container_fail
    mov r12, [rsp + 8]
    jmp .loop

.container_fail:
    cmp eax, -1
    je .bad
    jmp .fail

.ok:
    xor eax, eax
    jmp .return
.bad:
    mov eax, -1
    jmp .return
.fail:
    mov eax, -2
.return:
    add rsp, 16
    pop r15
    pop r14
    pop r13
    pop r12
    pop rbx
    ret

section .note.GNU-stack noalloc noexec nowrite progbits
