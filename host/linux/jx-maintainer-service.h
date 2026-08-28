#ifndef JX_MAINTAINER_SERVICE_LINUX_H
#define JX_MAINTAINER_SERVICE_LINUX_H

#include <stddef.h>
#include "../common/jx-maintainer-service.h"

#define JX_MAINTAINER_SOCKET_PATH_MAX 103u

typedef struct {
    int fd;
    char path[JX_MAINTAINER_SOCKET_PATH_MAX + 1u];
    jx_maintainer_service *core;
} jx_linux_maintainer_service;

int jx_linux_maintainer_service_open(jx_linux_maintainer_service *service,
                                     const char *path,
                                     jx_maintainer_service *core);
void jx_linux_maintainer_service_close(jx_linux_maintainer_service *service);
int jx_linux_maintainer_service_fd(const jx_linux_maintainer_service *service);
int jx_linux_maintainer_service_process_one(jx_linux_maintainer_service *service,
                                            uint64_t now);

#endif
