#ifndef JX_MAINTAINER_SSH_H
#define JX_MAINTAINER_SSH_H

#include <stddef.h>
#include <stdint.h>
#include "jx-remote-bag-wire.h"

#define JX_MAINTAINER_SSH_VERSION 1u
#define JX_MAINTAINER_SSH_LINE_MAX 128u

typedef enum {
    JX_MAINTAINER_SSH_INVALID = 0,
    JX_MAINTAINER_SSH_BAG = 1
} jx_maintainer_ssh_kind;

typedef struct {
    jx_maintainer_ssh_kind kind;
    uint32_t request_length;
    uint32_t json_length;
} jx_maintainer_ssh_command;

/** Parse exactly: JX-MAINT/1 BAG <request-bytes> <json-bytes> */
int jx_maintainer_ssh_parse_line(const char *line, size_t length,
                                 jx_maintainer_ssh_command *out);

#endif
