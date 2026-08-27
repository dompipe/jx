#include "jx11-ewmh.h"
#include <stddef.h>
#include <string.h>

static xcb_atom_t intern_atom(xcb_connection_t *conn, const char *name) {
    xcb_intern_atom_cookie_t ck = xcb_intern_atom(conn, 0, (uint16_t)strlen(name), name);
    xcb_intern_atom_reply_t *reply = xcb_intern_atom_reply(conn, ck, NULL);
    if (!reply) return XCB_ATOM_NONE;
    xcb_atom_t atom = reply->atom;
    free(reply);
    return atom;
}

void jx11_ewmh_clients_reset(jx11_ewmh_clients *clients) {
    if (!clients) return;
    memset(clients, 0, sizeof *clients);
}

int jx11_ewmh_clients_contains(const jx11_ewmh_clients *clients, xcb_window_t window) {
    if (!clients || window == XCB_NONE) return 0;
    for (uint16_t i = 0; i < clients->count; ++i) if (clients->clients[i] == window) return 1;
    return 0;
}

int jx11_ewmh_clients_add(jx11_ewmh_clients *clients, xcb_window_t window) {
    if (!clients || window == XCB_NONE) return -1;
    if (jx11_ewmh_clients_contains(clients, window)) return 0;
    if (clients->count >= JX11_EWMH_MAX_CLIENTS) return -2;
    clients->clients[clients->count++] = window;
    return 1;
}

int jx11_ewmh_clients_remove(jx11_ewmh_clients *clients, xcb_window_t window) {
    if (!clients || window == XCB_NONE) return -1;
    for (uint16_t i = 0; i < clients->count; ++i) {
        if (clients->clients[i] != window) continue;
        for (uint16_t j = i + 1u; j < clients->count; ++j) clients->clients[j - 1u] = clients->clients[j];
        --clients->count;
        if (clients->count < JX11_EWMH_MAX_CLIENTS) clients->clients[clients->count] = XCB_NONE;
        return 1;
    }
    return 0;
}

int jx11_ewmh_init_atoms(xcb_connection_t *conn, jx11_ewmh_atoms *atoms) {
    if (!conn || !atoms) return -1;
    memset(atoms, 0, sizeof *atoms);
#define INTERN(field, name) do { atoms->field = intern_atom(conn, name); if (atoms->field == XCB_ATOM_NONE) return -2; } while (0)
    INTERN(net_supported, "_NET_SUPPORTED");
    INTERN(net_client_list, "_NET_CLIENT_LIST");
    INTERN(net_active_window, "_NET_ACTIVE_WINDOW");
    INTERN(net_number_of_desktops, "_NET_NUMBER_OF_DESKTOPS");
    INTERN(net_current_desktop, "_NET_CURRENT_DESKTOP");
    INTERN(net_wm_desktop, "_NET_WM_DESKTOP");
    INTERN(net_workarea, "_NET_WORKAREA");
    INTERN(net_wm_name, "_NET_WM_NAME");
    INTERN(net_wm_pid, "_NET_WM_PID");
    INTERN(utf8_string, "UTF8_STRING");
    INTERN(wm_protocols, "WM_PROTOCOLS");
    INTERN(wm_delete_window, "WM_DELETE_WINDOW");
#undef INTERN
    return 0;
}

void jx11_ewmh_publish_supported(xcb_connection_t *conn, xcb_window_t root, const jx11_ewmh_atoms *atoms) {
    if (!conn || !atoms) return;
    const xcb_atom_t supported[] = {
        atoms->net_client_list, atoms->net_active_window, atoms->net_number_of_desktops,
        atoms->net_current_desktop, atoms->net_wm_desktop, atoms->net_workarea,
        atoms->net_wm_name, atoms->net_wm_pid
    };
    xcb_change_property(conn, XCB_PROP_MODE_REPLACE, root, atoms->net_supported,
                        XCB_ATOM_ATOM, 32, (uint32_t)(sizeof supported / sizeof supported[0]), supported);
}

void jx11_ewmh_publish_clients(xcb_connection_t *conn, xcb_window_t root, const jx11_ewmh_atoms *atoms, const jx11_ewmh_clients *clients) {
    if (!conn || !atoms || !clients) return;
    xcb_change_property(conn, XCB_PROP_MODE_REPLACE, root, atoms->net_client_list,
                        XCB_ATOM_WINDOW, 32, clients->count, clients->clients);
}

void jx11_ewmh_publish_active(xcb_connection_t *conn, xcb_window_t root, const jx11_ewmh_atoms *atoms, xcb_window_t active) {
    if (!conn || !atoms) return;
    xcb_window_t value = active;
    xcb_change_property(conn, XCB_PROP_MODE_REPLACE, root, atoms->net_active_window,
                        XCB_ATOM_WINDOW, 32, 1, &value);
}

void jx11_ewmh_publish_desktops(xcb_connection_t *conn, xcb_window_t root, const jx11_ewmh_atoms *atoms, uint32_t count, uint32_t current) {
    if (!conn || !atoms) return;
    xcb_change_property(conn, XCB_PROP_MODE_REPLACE, root, atoms->net_number_of_desktops,
                        XCB_ATOM_CARDINAL, 32, 1, &count);
    xcb_change_property(conn, XCB_PROP_MODE_REPLACE, root, atoms->net_current_desktop,
                        XCB_ATOM_CARDINAL, 32, 1, &current);
}

void jx11_ewmh_publish_workarea(xcb_connection_t *conn, xcb_window_t root, const jx11_ewmh_atoms *atoms,
                                uint32_t x, uint32_t y, uint32_t width, uint32_t height) {
    if (!conn || !atoms) return;
    uint32_t area[4] = { x, y, width, height };
    xcb_change_property(conn, XCB_PROP_MODE_REPLACE, root, atoms->net_workarea,
                        XCB_ATOM_CARDINAL, 32, 4, area);
}
