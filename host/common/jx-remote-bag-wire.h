#ifndef JX_REMOTE_BAG_WIRE_H
#define JX_REMOTE_BAG_WIRE_H

#include <stddef.h>
#include <stdint.h>
#include "jx-remote-bag.h"

#define JX_REMOTE_BAG_REQUEST_WIRE_BYTES 372u

int jx_remote_bag_request_write(uint8_t out[JX_REMOTE_BAG_REQUEST_WIRE_BYTES],
                                const jx_remote_bag_request *request);
int jx_remote_bag_request_read(const uint8_t *in, size_t length,
                               jx_remote_bag_request *request);

#endif
