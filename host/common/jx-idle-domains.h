#ifndef JX_IDLE_DOMAINS_H
#define JX_IDLE_DOMAINS_H

#include "jx-idle-bitmap.h"

typedef enum {
    JX_IDLE_DOMAIN_CORE = 0,
    JX_IDLE_DOMAIN_WINDOW = 1,
    JX_IDLE_DOMAIN_COUNT = 2
} jx_idle_domain_id;

/*
 * Two independent bus-#1 bitstrings.
 *
 * CORE is the small deterministic barrier for services that are part of the
 * runtime itself. WINDOW is the scalable desktop/background barrier for GUI
 * programs and helpers. A busy or stalled window must never delay the core
 * synchronization epoch merely because it exists.
 */
typedef struct {
    jx_idle_bitmap domain[JX_IDLE_DOMAIN_COUNT];
} jx_idle_domains;

static inline void jx_idle_domains_init(jx_idle_domains *domains) {
    if (!domains) return;
    jx_idle_bitmap_init(&domains->domain[JX_IDLE_DOMAIN_CORE]);
    jx_idle_bitmap_init(&domains->domain[JX_IDLE_DOMAIN_WINDOW]);
}

static inline jx_idle_bitmap *jx_idle_domains_core(jx_idle_domains *domains) {
    return domains ? &domains->domain[JX_IDLE_DOMAIN_CORE] : 0;
}

static inline jx_idle_bitmap *jx_idle_domains_window(jx_idle_domains *domains) {
    return domains ? &domains->domain[JX_IDLE_DOMAIN_WINDOW] : 0;
}

static inline const jx_idle_bitmap *jx_idle_domains_core_const(const jx_idle_domains *domains) {
    return domains ? &domains->domain[JX_IDLE_DOMAIN_CORE] : 0;
}

static inline const jx_idle_bitmap *jx_idle_domains_window_const(const jx_idle_domains *domains) {
    return domains ? &domains->domain[JX_IDLE_DOMAIN_WINDOW] : 0;
}

#endif
