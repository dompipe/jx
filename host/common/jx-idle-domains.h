#ifndef JX_IDLE_DOMAINS_H
#define JX_IDLE_DOMAINS_H

#include "jx-idle-bitmap.h"

typedef enum {
    JX_IDLE_DOMAIN_CORE = 0,
    JX_IDLE_DOMAIN_WINDOW = 1,
    JX_IDLE_DOMAIN_SECURITY = 2,
    JX_IDLE_DOMAIN_COUNT = 3
} jx_idle_domain_id;

/*
 * Independent bus-#1 bitstrings.
 *
 * CORE is the small deterministic barrier for services that are part of the
 * runtime itself. WINDOW is the scalable desktop/background barrier for GUI
 * programs and helpers. SECURITY is the scanner/admission barrier used for
 * file, archive, download and executable inspection. A busy or stalled
 * WINDOW task must never delay CORE, and SECURITY can be armed only when
 * inspection work exists.
 */
typedef struct {
    jx_idle_bitmap domain[JX_IDLE_DOMAIN_COUNT];
} jx_idle_domains;

static inline void jx_idle_domains_init(jx_idle_domains *domains) {
    if (!domains) return;
    jx_idle_bitmap_init(&domains->domain[JX_IDLE_DOMAIN_CORE]);
    jx_idle_bitmap_init(&domains->domain[JX_IDLE_DOMAIN_WINDOW]);
    jx_idle_bitmap_init(&domains->domain[JX_IDLE_DOMAIN_SECURITY]);
}

static inline jx_idle_bitmap *jx_idle_domains_core(jx_idle_domains *domains) {
    return domains ? &domains->domain[JX_IDLE_DOMAIN_CORE] : 0;
}

static inline jx_idle_bitmap *jx_idle_domains_window(jx_idle_domains *domains) {
    return domains ? &domains->domain[JX_IDLE_DOMAIN_WINDOW] : 0;
}

static inline jx_idle_bitmap *jx_idle_domains_security(jx_idle_domains *domains) {
    return domains ? &domains->domain[JX_IDLE_DOMAIN_SECURITY] : 0;
}

static inline const jx_idle_bitmap *jx_idle_domains_core_const(const jx_idle_domains *domains) {
    return domains ? &domains->domain[JX_IDLE_DOMAIN_CORE] : 0;
}

static inline const jx_idle_bitmap *jx_idle_domains_window_const(const jx_idle_domains *domains) {
    return domains ? &domains->domain[JX_IDLE_DOMAIN_WINDOW] : 0;
}

static inline const jx_idle_bitmap *jx_idle_domains_security_const(const jx_idle_domains *domains) {
    return domains ? &domains->domain[JX_IDLE_DOMAIN_SECURITY] : 0;
}

#endif
