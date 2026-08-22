/* PASL O(n) → C (portable binary via gcc/clang/mingw) */
#include <stdint.h>
#if defined(_WIN32)
#  include <windows.h>
#endif
static inline int64_t pasl_main(void) {
    int64_t slot0=0, slot1=0, slot2=0, slot3=0, slot4=0;
    int64_t acc = 0, s1 = 0;
    acc = 0LL;
    slot0 = acc;
    acc = 5LL;
    slot2 = acc;
while_0:
    acc = slot2;
    if (acc != 0) goto wbody_1;
    goto wend_2;
wbody_1:
    acc = slot0;
    s1 = slot2;
    acc = acc + s1;
    slot0 = acc;
    slot2--;
    goto while_0;
wend_2:
    return slot0;
}

int main(void) {
    int64_t r = pasl_main();
    return (int)(r & 0xff);
}
