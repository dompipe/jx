#ifndef JX_HOST_H
#define JX_HOST_H

#include <stdint.h>

#ifdef __cplusplus
extern "C" {
#endif

#define JX_HOST_ABI_VERSION 1

typedef struct jx_window_spec {
    const char *id;
    const char *title;
    int32_t x;
    int32_t y;
    int32_t width;
    int32_t height;
} jx_window_spec;

typedef struct jx_host_event {
    uint32_t version;
    const char *type;
    const char *window_id;
    const char *json_payload;
} jx_host_event;

typedef struct jx_host_window jx_host_window;

jx_host_window *jx_host_open(const jx_window_spec *spec);
int jx_host_poll(jx_host_window *window, jx_host_event *event);
int jx_host_set_title(jx_host_window *window, const char *title);
void jx_host_close(jx_host_window *window);

#ifdef __cplusplus
}
#endif

#endif
