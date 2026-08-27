#include "jx11-shadow.h"

/*
 * Hot shadow mask helpers live inline in jx11-shadow.h so production event
 * dispatch pays no out-of-line call or semantic switch. This translation unit
 * remains intentionally small to preserve the native build/ABI test layout.
 */
