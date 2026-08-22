; PASL → x86-64 NASM (Linux SysV, freestanding)
; no libc / no write — exit status = result
bits 64
default rel
section .text
global _start
_start:
    push rbp
    mov  rbp, rsp
    sub  rsp, 256
    push rbx
    push r12
    push r13
    push r14
    push r15
; $sum = 0
    mov  rax, 0
    mov  rbx, rax
; $i = 5
    mov  rax, 5
    mov  r12, rax
.Lwhile_0:
    mov  rax, r12
    test rax, rax
    jnz  .Lwbody_1
    jmp  .Lwend_2
.Lwbody_1:
; $sum = $sum + $i
    mov  rax, rbx
    push rax
    mov  rcx, r12
    pop  rax
    add  rax, rcx
    mov  rbx, rax
; $i--
    mov  rax, r12
    dec  rax
    mov  r12, rax
    jmp  .Lwhile_0
.Lwend_2:
    mov  rax, rbx
    mov  rdi, rax
    pop  r15
    pop  r14
    pop  r13
    pop  r12
    pop  rbx
    mov  rsp, rbp
    pop  rbp
    mov  rax, 60
    syscall
