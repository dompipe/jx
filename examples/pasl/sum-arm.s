/* PASL O(n) → AArch64 Linux (freestanding, no write) */
    .text
    .global _start
    .align 2
_start:
    stp  x29, x30, [sp, #-16]!
    mov  x29, sp
    sub  sp, sp, #512
    str  x19, [sp, #-16]!
    str  x20, [sp, #-16]!
    str  x21, [sp, #-16]!
    str  x22, [sp, #-16]!
    str  x23, [sp, #-16]!
    mov  x9, #0
    mov  x19, x9
    mov  x9, #5
    mov  x20, x9
while_0:
    mov  x9, x20
    cmp  x9, #0
    b.ne wbody_1
    b    wend_2
wbody_1:
    mov  x9, x19
    mov  x10, x20
    add  x9, x9, x10
    mov  x19, x9
    mov  x9, x20
    sub  x9, x9, #1
    mov  x20, x9
    b    while_0
wend_2:
    mov  x0, x19
    ldr  x23, [sp], #16
    ldr  x22, [sp], #16
    ldr  x21, [sp], #16
    ldr  x20, [sp], #16
    ldr  x19, [sp], #16
    mov  sp, x29
    ldp  x29, x30, [sp], #16
    mov  x8, #93
    svc  #0
