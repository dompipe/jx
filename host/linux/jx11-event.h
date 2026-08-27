#ifndef JX11_EVENT_H
#define JX11_EVENT_H

#include "jx11-register.h"
#include <stdint.h>

#define JX11_EVENT_SOCKET_PATH_MAX 100u
#define JX11_EVENT_PACKET_MAX 8192u

typedef struct {
    int fd;
    int enabled;
    int warned;
    char path[JX11_EVENT_SOCKET_PATH_MAX + 1u];
} jx11_event_sender;

typedef struct {
    const char *event;
    uint32_t xid;
    jx11_window_ref hotref;
    int32_t x;
    int32_t y;
    uint32_t width;
    uint32_t height;
    uint8_t focused;
    uint8_t mapped;
    uint16_t workspace;
    const char *title;
    const char *class_name;
} jx11_event_window;

void jx11_event_sender_reset(jx11_event_sender *sender);
int jx11_event_sender_open(jx11_event_sender *sender, const char *path);
void jx11_event_sender_close(jx11_event_sender *sender);
int jx11_event_sender_send(jx11_event_sender *sender, const jx11_event_window *window);

#endif
