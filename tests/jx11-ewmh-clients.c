#include "../host/linux/jx11-ewmh.h"
#include <stdio.h>

int main(void) {
    jx11_ewmh_clients clients;
    jx11_ewmh_clients_reset(&clients);
    if (clients.count != 0) return 1;
    if (jx11_ewmh_clients_add(&clients, 0x1001u) != 1) return 2;
    if (jx11_ewmh_clients_add(&clients, 0x1002u) != 1) return 3;
    if (jx11_ewmh_clients_add(&clients, 0x1001u) != 0) return 4;
    if (clients.count != 2) return 5;
    if (!jx11_ewmh_clients_contains(&clients, 0x1001u)) return 6;
    if (jx11_ewmh_clients_remove(&clients, 0x1001u) != 1) return 7;
    if (jx11_ewmh_clients_contains(&clients, 0x1001u)) return 8;
    if (clients.count != 1 || clients.clients[0] != 0x1002u) return 9;
    if (jx11_ewmh_clients_remove(&clients, 0x9999u) != 0) return 10;
    puts("jx11-ewmh-clients: ok");
    return 0;
}
