#ifndef JX11_EWMH_H
#define JX11_EWMH_H

#include <xcb/xcb.h>
#include <stdint.h>

#define JX11_EWMH_MAX_CLIENTS 256u

typedef struct {
    xcb_atom_t net_supported;
    xcb_atom_t net_client_list;
    xcb_atom_t net_active_window;
    xcb_atom_t net_close_window;
    xcb_atom_t net_number_of_desktops;
    xcb_atom_t net_current_desktop;
    xcb_atom_t net_wm_desktop;
    xcb_atom_t net_workarea;
    xcb_atom_t net_wm_name;
    xcb_atom_t net_wm_pid;
    xcb_atom_t utf8_string;
    xcb_atom_t wm_protocols;
    xcb_atom_t wm_delete_window;
} jx11_ewmh_atoms;

typedef struct {
    xcb_window_t clients[JX11_EWMH_MAX_CLIENTS];
    uint16_t count;
} jx11_ewmh_clients;

void jx11_ewmh_clients_reset(jx11_ewmh_clients *clients);
int jx11_ewmh_clients_add(jx11_ewmh_clients *clients, xcb_window_t window);
int jx11_ewmh_clients_remove(jx11_ewmh_clients *clients, xcb_window_t window);
int jx11_ewmh_clients_contains(const jx11_ewmh_clients *clients, xcb_window_t window);

int jx11_ewmh_init_atoms(xcb_connection_t *conn, jx11_ewmh_atoms *atoms);
void jx11_ewmh_publish_supported(xcb_connection_t *conn, xcb_window_t root, const jx11_ewmh_atoms *atoms);
void jx11_ewmh_publish_clients(xcb_connection_t *conn, xcb_window_t root, const jx11_ewmh_atoms *atoms, const jx11_ewmh_clients *clients);
void jx11_ewmh_publish_active(xcb_connection_t *conn, xcb_window_t root, const jx11_ewmh_atoms *atoms, xcb_window_t active);
void jx11_ewmh_publish_desktops(xcb_connection_t *conn, xcb_window_t root, const jx11_ewmh_atoms *atoms, uint32_t count, uint32_t current);
void jx11_ewmh_publish_workarea(xcb_connection_t *conn, xcb_window_t root, const jx11_ewmh_atoms *atoms, uint32_t x, uint32_t y, uint32_t width, uint32_t height);
int jx11_ewmh_supports_delete(xcb_connection_t *conn, xcb_window_t window, const jx11_ewmh_atoms *atoms);
void jx11_ewmh_request_close(xcb_connection_t *conn, xcb_window_t window, const jx11_ewmh_atoms *atoms, int supports_delete);

#endif
