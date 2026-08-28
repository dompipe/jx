#include "../host/linux/jx11-patch-service.h"
#include <string.h>

int main(void) {
    jx11_patch_service service;
    jx11_live_patch manager;
    memset(&service, 0, sizeof service);
    memset(&manager, 0, sizeof manager);
    service.fd = -1;
    service.manager = &manager;
    jx11_patch_service_set_host(&service, NULL);
    return jx11_patch_service_fd(&service) == -1 ? 0 : 1;
}
