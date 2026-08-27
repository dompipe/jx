#include "../host/linux/jx11-event.h"
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/un.h>
#include <unistd.h>

int main(void) {
    char path[96];
    snprintf(path, sizeof path, "/tmp/jx11-event-test-%ld.sock", (long)getpid());
    unlink(path);

    int rx = socket(AF_UNIX, SOCK_DGRAM, 0);
    if (rx < 0) return 1;
    struct sockaddr_un addr;
    memset(&addr, 0, sizeof addr);
    addr.sun_family = AF_UNIX;
    memcpy(addr.sun_path, path, strlen(path) + 1u);
    if (bind(rx, (struct sockaddr *)&addr, sizeof addr) != 0) return 2;

    jx11_event_sender sender;
    if (jx11_event_sender_open(&sender, path) != 0) return 3;

    jx11_event_window w;
    memset(&w, 0, sizeof w);
    w.event = "window-focus";
    w.xid = 0x0012ab34u;
    w.hotref = jx11_window_ref_make(0u, 17u, 3u);
    w.x = 10;
    w.y = 20;
    w.width = 800;
    w.height = 600;
    w.focused = 1u;
    w.mapped = 1u;
    w.workspace = 2u;
    w.title = "JX Window";
    w.class_name = "XTerm";

    if (jx11_event_sender_send(&sender, &w) != 1) return 4;

    char packet[8192];
    ssize_t n = recv(rx, packet, sizeof packet - 1u, 0);
    if (n <= 0) return 5;
    packet[n] = '\0';

    const char *expected =
        "JX11E/1|window-focus|x11:0012ab34|0|4355|10|20|800|600|1|1|2|"
        "4a582057696e646f77|585465726d";
    if (strcmp(packet, expected) != 0) {
        fprintf(stderr, "jx11-event-sender mismatch:\n%s\n!=\n%s\n", packet, expected);
        return 6;
    }

    jx11_event_sender_close(&sender);
    close(rx);
    unlink(path);
    puts("jx11-event-sender: ok");
    return 0;
}
