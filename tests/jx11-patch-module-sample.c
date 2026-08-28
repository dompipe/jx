#include "../host/linux/jx11-patch-module.h"

static void activate(const jx11_patch_host_v1 *host) {
    if (host && host->set_background_rgb) host->set_background_rgb(0x123456u);
    if (host && host->log) host->log(1, "sample activated");
}

static void deactivate(const jx11_patch_host_v1 *host) {
    if (host && host->log) host->log(1, "sample deactivated");
}

static void safe_point(const jx11_patch_host_v1 *host) {
    if (host && host->invalidate_desktop) host->invalidate_desktop();
}

static int filter_x_event(const jx11_patch_host_v1 *host, uint8_t response_type, void *event) {
    (void)host;
    (void)event;
    return response_type != 99u;
}

static const jx11_patch_module_v1 module = {
    JX11_PATCH_MODULE_ABI_VERSION,
    sizeof(jx11_patch_module_v1),
    "sample-live-module",
    0u,
    activate,
    deactivate,
    safe_point,
    filter_x_event
};

const jx11_patch_module_v1 *jx11_patch_module_entry_v1(void) {
    return &module;
}
