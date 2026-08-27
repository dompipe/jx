#include "jx11-event.h"
#include <errno.h>
#include <fcntl.h>
#include <stddef.h>
#include <stdio.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/un.h>
#include <unistd.h>

static int event_name_ok(const char *event) {
    return event && (!strcmp(event, "window-open") || !strcmp(event, "window-update") ||
                     !strcmp(event, "window-focus") || !strcmp(event, "window-close") ||
                     !strcmp(event, "window-unmap"));
}

static size_t bounded_strlen(const char *s, size_t limit) {
    size_t n = 0u;
    if (!s) return 0u;
    while (n < limit && s[n] != '\0') ++n;
    return n;
}

static int append_char(char *out, size_t cap, size_t *at, char c) {
    if (*at + 1u >= cap) return -1;
    out[(*at)++] = c;
    out[*at] = '\0';
    return 0;
}

static int append_text(char *out, size_t cap, size_t *at, const char *s) {
    if (!s) s = "";
    size_t n = strlen(s);
    if (*at + n >= cap) return -1;
    memcpy(out + *at, s, n);
    *at += n;
    out[*at] = '\0';
    return 0;
}

static int append_u32(char *out, size_t cap, size_t *at, uint32_t value) {
    char tmp[32];
    int n = snprintf(tmp, sizeof tmp, "%u", value);
    if (n < 0 || (size_t)n >= sizeof tmp) return -1;
    return append_text(out, cap, at, tmp);
}

static int append_i32(char *out, size_t cap, size_t *at, int32_t value) {
    char tmp[32];
    int n = snprintf(tmp, sizeof tmp, "%d", value);
    if (n < 0 || (size_t)n >= sizeof tmp) return -1;
    return append_text(out, cap, at, tmp);
}

static int append_hex(char *out, size_t cap, size_t *at, const char *s, size_t max_bytes) {
    static const char hex[] = "0123456789abcdef";
    if (!s) s = "";
    size_t n = bounded_strlen(s, max_bytes + 1u);
    if (n > max_bytes) n = max_bytes;
    if (*at + n * 2u >= cap) return -1;
    for (size_t i = 0; i < n; ++i) {
        unsigned char c = (unsigned char)s[i];
        out[(*at)++] = hex[c >> 4];
        out[(*at)++] = hex[c & 0x0fu];
    }
    out[*at] = '\0';
    return 0;
}

void jx11_event_sender_reset(jx11_event_sender *sender) {
    if (!sender) return;
    sender->fd = -1;
    sender->enabled = 0;
    sender->warned = 0;
    sender->path[0] = '\0';
}

int jx11_event_sender_open(jx11_event_sender *sender, const char *path) {
    if (!sender || !path || !*path) return -1;
    size_t n = strlen(path);
    if (n > JX11_EVENT_SOCKET_PATH_MAX) return -2;

    jx11_event_sender_reset(sender);
    int fd = socket(AF_UNIX, SOCK_DGRAM, 0);
    if (fd < 0) return -3;

    int flags = fcntl(fd, F_GETFL, 0);
    if (flags < 0 || fcntl(fd, F_SETFL, flags | O_NONBLOCK) < 0) {
        close(fd);
        return -4;
    }

    sender->fd = fd;
    sender->enabled = 1;
    memcpy(sender->path, path, n + 1u);
    return 0;
}

void jx11_event_sender_close(jx11_event_sender *sender) {
    if (!sender) return;
    if (sender->fd >= 0) close(sender->fd);
    jx11_event_sender_reset(sender);
}

int jx11_event_sender_send(jx11_event_sender *sender, const jx11_event_window *window) {
    if (!sender || !window || !sender->enabled || sender->fd < 0) return 0;
    if (!event_name_ok(window->event)) return -1;

    char packet[JX11_EVENT_PACKET_MAX];
    size_t at = 0;
    packet[0] = '\0';

#define SEP() do { if (append_char(packet, sizeof packet, &at, '|') != 0) return -2; } while (0)
#define TXT(v) do { if (append_text(packet, sizeof packet, &at, (v)) != 0) return -2; } while (0)
#define U32(v) do { if (append_u32(packet, sizeof packet, &at, (uint32_t)(v)) != 0) return -2; } while (0)
#define I32(v) do { if (append_i32(packet, sizeof packet, &at, (int32_t)(v)) != 0) return -2; } while (0)

    TXT("JX11E/1"); SEP(); TXT(window->event); SEP();
    char host_id[32];
    int hn = snprintf(host_id, sizeof host_id, "x11:%08x", window->xid);
    if (hn < 0 || (size_t)hn >= sizeof host_id) return -2;
    TXT(host_id); SEP(); U32(window->hotref.reg); SEP(); U32(window->hotref.ref); SEP();
    I32(window->x); SEP(); I32(window->y); SEP(); U32(window->width); SEP(); U32(window->height); SEP();
    U32(window->focused ? 1u : 0u); SEP(); U32(window->mapped ? 1u : 0u); SEP(); U32(window->workspace); SEP();
    if (append_hex(packet, sizeof packet, &at, window->title, 1024u) != 0) return -2;
    SEP();
    if (append_hex(packet, sizeof packet, &at, window->class_name, 256u) != 0) return -2;

#undef SEP
#undef TXT
#undef U32
#undef I32

    struct sockaddr_un addr;
    memset(&addr, 0, sizeof addr);
    addr.sun_family = AF_UNIX;
    memcpy(addr.sun_path, sender->path, strlen(sender->path) + 1u);

    ssize_t sent = sendto(sender->fd, packet, at, MSG_DONTWAIT,
                          (const struct sockaddr *)&addr, sizeof addr);
    if (sent == (ssize_t)at) {
        sender->warned = 0;
        return 1;
    }

    if (errno == EAGAIN || errno == EWOULDBLOCK || errno == ENOENT || errno == ECONNREFUSED) {
        if (!sender->warned) {
            fprintf(stderr, "jx11: event bridge unavailable at %s; continuing without blocking\n", sender->path);
            sender->warned = 1;
        }
        return 0;
    }
    return -3;
}
