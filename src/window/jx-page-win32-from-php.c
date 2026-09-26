#define WIN32_LEAN_AND_MEAN
#include <winsock2.h>
#include <ws2tcpip.h>
#include <windows.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "../runtime/jx_css_runtime.h"
#include "jx_php_page_data.h"

#define JX_API_PORT 8765
#define JX_ID_TITLE 2001
#define JX_ID_BADGE 2002
#define JX_ID_BODY 2003
#define JX_ID_APPLY 2004
#define JX_WM_API_UPDATE (WM_APP + 91)

typedef struct {
    char title[192];
    char badge[128];
    char body[768];
} JxPageState;

typedef struct {
    JxCssStylesheet css;
    char *css_bytes;
    size_t css_len;
    COLORREF body_bg;
    COLORREF body_fg;
    COLORREF card_border;
    int body_padding;
    int card_padding;
    int card_radius;
} JxStyleState;

static JxPageState g_page;
static JxStyleState g_style;
static CRITICAL_SECTION g_lock;
static HWND g_hwnd = NULL;
static HWND g_title_edit = NULL;
static HWND g_badge_edit = NULL;
static HWND g_body_edit = NULL;
static HANDLE g_api_thread = NULL;
static volatile LONG g_api_stop = 0;

static void copy_text(char *dst, size_t dst_size, const char *src) {
    if (!dst || dst_size == 0) return;
    if (!src) src = "";
    snprintf(dst, dst_size, "%s", src);
}

static char *copy_bytes(const char *data, size_t len) {
    char *out = (char *)malloc(len + 1);
    if (!out) return NULL;
    if (len) memcpy(out, data, len);
    out[len] = '\0';
    return out;
}

static char *slice_copy(const char *data, size_t len) {
    char *out = (char *)malloc(len + 1);
    if (!out) return NULL;
    memcpy(out, data, len);
    out[len] = '\0';
    return out;
}

static const JxCssDeclaration *css_decl(const char *selector, const char *property) {
    return jx_css_find_property(&g_style.css, selector, property);
}

static char *css_value_copy(const char *selector, const char *property) {
    const JxCssDeclaration *decl = css_decl(selector, property);
    if (!decl) return NULL;
    return slice_copy(decl->value, decl->value_length);
}

static int hex_value(char c) {
    if (c >= '0' && c <= '9') return c - '0';
    if (c >= 'a' && c <= 'f') return 10 + c - 'a';
    if (c >= 'A' && c <= 'F') return 10 + c - 'A';
    return -1;
}

static COLORREF parse_color_value(const char *value, COLORREF fallback) {
    if (!value) return fallback;
    const char *hex = strchr(value, '#');
    if (!hex || strlen(hex) < 7) return fallback;
    int r1 = hex_value(hex[1]);
    int r2 = hex_value(hex[2]);
    int g1 = hex_value(hex[3]);
    int g2 = hex_value(hex[4]);
    int b1 = hex_value(hex[5]);
    int b2 = hex_value(hex[6]);
    if (r1 < 0 || r2 < 0 || g1 < 0 || g2 < 0 || b1 < 0 || b2 < 0) return fallback;
    return RGB((r1 << 4) | r2, (g1 << 4) | g2, (b1 << 4) | b2);
}

static COLORREF css_color(const char *selector, const char *property, COLORREF fallback) {
    char *value = css_value_copy(selector, property);
    COLORREF out = parse_color_value(value, fallback);
    free(value);
    return out;
}

static int css_px(const char *selector, const char *property, int fallback) {
    char *value = css_value_copy(selector, property);
    if (!value) return fallback;
    int n = atoi(value);
    if (strstr(value, "rem")) n *= 16;
    free(value);
    return n > 0 ? n : fallback;
}

static void init_state(void) {
    InitializeCriticalSection(&g_lock);
    copy_text(g_page.title, sizeof(g_page.title), JX_PAGE_TITLE);
    copy_text(g_page.badge, sizeof(g_page.badge), JX_PAGE_BADGE);
    copy_text(g_page.body, sizeof(g_page.body), JX_PAGE_BODY);

    jx_css_stylesheet_init(&g_style.css);
    g_style.css_len = strlen(JX_PAGE_CSS);
    g_style.css_bytes = copy_bytes(JX_PAGE_CSS, g_style.css_len);
    if (g_style.css_bytes) {
        JxCssText css;
        css.data = g_style.css_bytes;
        css.length = g_style.css_len;
        jx_css_parse_text(css, &g_style.css);
    }

    g_style.body_bg = css_color("body", "background", RGB(16, 19, 24));
    g_style.body_fg = css_color("body", "color", RGB(244, 247, 251));
    g_style.card_border = css_color(".card", "border", RGB(59, 68, 84));
    g_style.body_padding = css_px("body", "padding", 32);
    g_style.card_padding = css_px(".card", "padding", 16);
    g_style.card_radius = css_px(".card", "border-radius", 12);
}

static void cleanup_state(void) {
    InterlockedExchange(&g_api_stop, 1);
    if (g_api_thread) {
        closesocket(INVALID_SOCKET);
        WaitForSingleObject(g_api_thread, 250);
        CloseHandle(g_api_thread);
    }
    jx_css_stylesheet_free(&g_style.css);
    free(g_style.css_bytes);
    DeleteCriticalSection(&g_lock);
}

static void snapshot_page(JxPageState *out) {
    EnterCriticalSection(&g_lock);
    *out = g_page;
    LeaveCriticalSection(&g_lock);
}

static void set_page(const char *title, const char *badge, const char *body) {
    EnterCriticalSection(&g_lock);
    if (title && *title) copy_text(g_page.title, sizeof(g_page.title), title);
    if (badge && *badge) copy_text(g_page.badge, sizeof(g_page.badge), badge);
    if (body && *body) copy_text(g_page.body, sizeof(g_page.body), body);
    LeaveCriticalSection(&g_lock);
}

static void sync_form(void) {
    JxPageState state;
    snapshot_page(&state);
    if (g_title_edit) SetWindowTextA(g_title_edit, state.title);
    if (g_badge_edit) SetWindowTextA(g_badge_edit, state.badge);
    if (g_body_edit) SetWindowTextA(g_body_edit, state.body);
}

static void apply_form(HWND hwnd) {
    char title[192];
    char badge[128];
    char body[768];
    GetWindowTextA(g_title_edit, title, sizeof(title));
    GetWindowTextA(g_badge_edit, badge, sizeof(badge));
    GetWindowTextA(g_body_edit, body, sizeof(body));
    set_page(title, badge, body);
    InvalidateRect(hwnd, NULL, TRUE);
}

static void fill_round_rect(HDC hdc, RECT rect, int radius, COLORREF fill, COLORREF outline) {
    HBRUSH brush = CreateSolidBrush(fill);
    HPEN pen = CreatePen(PS_SOLID, 1, outline);
    HGDIOBJ old_brush = SelectObject(hdc, brush);
    HGDIOBJ old_pen = SelectObject(hdc, pen);
    RoundRect(hdc, rect.left, rect.top, rect.right, rect.bottom, radius, radius);
    SelectObject(hdc, old_pen);
    SelectObject(hdc, old_brush);
    DeleteObject(pen);
    DeleteObject(brush);
}

static void draw_text_block(HDC hdc, const char *text, RECT *rect, int font_size, int weight, COLORREF color) {
    HFONT font = CreateFontA(-font_size, 0, 0, 0, weight, FALSE, FALSE, FALSE,
        ANSI_CHARSET, OUT_DEFAULT_PRECIS, CLIP_DEFAULT_PRECIS, CLEARTYPE_QUALITY,
        DEFAULT_PITCH | FF_DONTCARE, "Arial");
    HGDIOBJ old_font = SelectObject(hdc, font);
    SetTextColor(hdc, color);
    SetBkMode(hdc, TRANSPARENT);
    DrawTextA(hdc, text, -1, rect, DT_LEFT | DT_TOP | DT_WORDBREAK);
    SelectObject(hdc, old_font);
    DeleteObject(font);
}

static void paint_page(HWND hwnd, HDC hdc) {
    RECT client;
    GetClientRect(hwnd, &client);
    HBRUSH bg = CreateSolidBrush(g_style.body_bg);
    FillRect(hdc, &client, bg);
    DeleteObject(bg);

    JxPageState page_state;
    snapshot_page(&page_state);

    int pad = g_style.body_padding;
    RECT page = client;
    page.left += pad;
    page.top += pad;
    page.right -= pad;
    page.bottom -= pad;

    RECT render = page;
    render.right -= 330;

    RECT title = render;
    title.bottom = title.top + 86;
    draw_text_block(hdc, page_state.title, &title, 32, FW_BOLD, g_style.body_fg);

    RECT intro = render;
    intro.top += 56;
    intro.bottom = intro.top + 74;
    draw_text_block(hdc, "Generated from a PHP page into native Win32 drawing. No WebView. No browser engine.", &intro, 18, FW_NORMAL, g_style.body_fg);

    RECT card = render;
    card.top += 146;
    card.bottom = card.top + 300;
    fill_round_rect(hdc, card, g_style.card_radius, RGB(24, 30, 40), g_style.card_border);

    RECT inner = card;
    inner.left += g_style.card_padding;
    inner.top += g_style.card_padding;
    inner.right -= g_style.card_padding;
    inner.bottom -= g_style.card_padding;

    RECT badge = inner;
    badge.bottom = badge.top + 30;
    draw_text_block(hdc, page_state.badge, &badge, 16, FW_BOLD, RGB(145, 220, 255));

    RECT body = inner;
    body.top += 48;
    body.bottom = body.top + 130;
    draw_text_block(hdc, page_state.body, &body, 19, FW_NORMAL, g_style.body_fg);

    RECT api = inner;
    api.top += 190;
    api.bottom = api.top + 82;
    draw_text_block(hdc, "Local API: http://127.0.0.1:8765/update?title=Hello&badge=LIVE&body=Updated+from+URL", &api, 15, FW_NORMAL, RGB(210, 220, 232));

    RECT panel = page;
    panel.left = panel.right - 300;
    panel.bottom = panel.top + 430;
    fill_round_rect(hdc, panel, 14, RGB(28, 34, 45), RGB(67, 78, 96));

    RECT panel_title = panel;
    panel_title.left += 18;
    panel_title.top += 16;
    panel_title.right -= 18;
    panel_title.bottom = panel_title.top + 32;
    draw_text_block(hdc, "Dynamic Form", &panel_title, 20, FW_BOLD, g_style.body_fg);

    RECT note = panel;
    note.left += 18;
    note.right -= 18;
    note.top += 336;
    note.bottom = note.top + 70;
    draw_text_block(hdc, "This .exe was compiled from PHP declarations and embedded CSS. URL API is local-only.", &note, 14, FW_NORMAL, RGB(210, 220, 232));
}

static int from_hex(char c) {
    if (c >= '0' && c <= '9') return c - '0';
    if (c >= 'a' && c <= 'f') return 10 + c - 'a';
    if (c >= 'A' && c <= 'F') return 10 + c - 'A';
    return -1;
}

static void url_decode(char *text) {
    char *r = text;
    char *w = text;
    while (*r) {
        if (*r == '+') {
            *w++ = ' ';
            ++r;
        } else if (*r == '%' && r[1] && r[2]) {
            int hi = from_hex(r[1]);
            int lo = from_hex(r[2]);
            if (hi >= 0 && lo >= 0) {
                *w++ = (char)((hi << 4) | lo);
                r += 3;
            } else {
                *w++ = *r++;
            }
        } else {
            *w++ = *r++;
        }
    }
    *w = '\0';
}

static void query_value(const char *query, const char *key, char *out, size_t out_size) {
    out[0] = '\0';
    size_t key_len = strlen(key);
    const char *p = query;
    while (p && *p) {
        if (strncmp(p, key, key_len) == 0 && p[key_len] == '=') {
            p += key_len + 1;
            size_t len = 0;
            while (p[len] && p[len] != '&' && len + 1 < out_size) {
                out[len] = p[len];
                ++len;
            }
            out[len] = '\0';
            url_decode(out);
            return;
        }
        p = strchr(p, '&');
        if (p) ++p;
    }
}

static void send_http_response(SOCKET client) {
    const char *body = "OK JX page updated\n";
    char response[256];
    snprintf(response, sizeof(response),
        "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\nContent-Length: %u\r\nConnection: close\r\n\r\n%s",
        (unsigned)strlen(body), body);
    send(client, response, (int)strlen(response), 0);
}

static DWORD WINAPI api_thread_proc(LPVOID arg) {
    (void)arg;
    WSADATA wsa;
    if (WSAStartup(MAKEWORD(2, 2), &wsa) != 0) return 1;

    SOCKET server = socket(AF_INET, SOCK_STREAM, IPPROTO_TCP);
    if (server == INVALID_SOCKET) {
        WSACleanup();
        return 1;
    }

    u_long nonblocking = 1;
    ioctlsocket(server, FIONBIO, &nonblocking);

    struct sockaddr_in addr;
    memset(&addr, 0, sizeof(addr));
    addr.sin_family = AF_INET;
    addr.sin_port = htons(JX_API_PORT);
    addr.sin_addr.s_addr = inet_addr("127.0.0.1");

    if (bind(server, (struct sockaddr *)&addr, sizeof(addr)) == SOCKET_ERROR) {
        closesocket(server);
        WSACleanup();
        return 1;
    }
    listen(server, 8);

    while (!InterlockedCompareExchange(&g_api_stop, 0, 0)) {
        SOCKET client = accept(server, NULL, NULL);
        if (client == INVALID_SOCKET) {
            Sleep(35);
            continue;
        }

        char req[2048];
        int got = recv(client, req, sizeof(req) - 1, 0);
        if (got > 0) {
            req[got] = '\0';
            char *start = strstr(req, "GET /update?");
            if (start) {
                char *query = start + strlen("GET /update?");
                char *space = strchr(query, ' ');
                if (space) *space = '\0';
                char title[192];
                char badge[128];
                char body[768];
                query_value(query, "title", title, sizeof(title));
                query_value(query, "badge", badge, sizeof(badge));
                query_value(query, "body", body, sizeof(body));
                set_page(title, badge, body);
                if (g_hwnd) PostMessageA(g_hwnd, JX_WM_API_UPDATE, 0, 0);
            }
            send_http_response(client);
        }
        closesocket(client);
    }

    closesocket(server);
    WSACleanup();
    return 0;
}

static void create_controls(HWND hwnd) {
    HFONT font = (HFONT)GetStockObject(DEFAULT_GUI_FONT);
    int x = 700;
    int y = 118;
    int w = 245;

    g_title_edit = CreateWindowExA(WS_EX_CLIENTEDGE, "EDIT", "", WS_CHILD | WS_VISIBLE | ES_AUTOHSCROLL, x, y, w, 26, hwnd, (HMENU)JX_ID_TITLE, GetModuleHandleA(NULL), NULL);
    g_badge_edit = CreateWindowExA(WS_EX_CLIENTEDGE, "EDIT", "", WS_CHILD | WS_VISIBLE | ES_AUTOHSCROLL, x, y + 62, w, 26, hwnd, (HMENU)JX_ID_BADGE, GetModuleHandleA(NULL), NULL);
    g_body_edit = CreateWindowExA(WS_EX_CLIENTEDGE, "EDIT", "", WS_CHILD | WS_VISIBLE | ES_MULTILINE | ES_AUTOVSCROLL | WS_VSCROLL, x, y + 124, w, 108, hwnd, (HMENU)JX_ID_BODY, GetModuleHandleA(NULL), NULL);
    HWND button = CreateWindowA("BUTTON", "Apply", WS_CHILD | WS_VISIBLE | BS_PUSHBUTTON, x, y + 250, w, 32, hwnd, (HMENU)JX_ID_APPLY, GetModuleHandleA(NULL), NULL);

    SendMessageA(g_title_edit, WM_SETFONT, (WPARAM)font, TRUE);
    SendMessageA(g_badge_edit, WM_SETFONT, (WPARAM)font, TRUE);
    SendMessageA(g_body_edit, WM_SETFONT, (WPARAM)font, TRUE);
    SendMessageA(button, WM_SETFONT, (WPARAM)font, TRUE);
    sync_form();
}

static LRESULT CALLBACK window_proc(HWND hwnd, UINT msg, WPARAM wp, LPARAM lp) {
    switch (msg) {
        case WM_CREATE:
            g_hwnd = hwnd;
            create_controls(hwnd);
            g_api_thread = CreateThread(NULL, 0, api_thread_proc, NULL, 0, NULL);
            return 0;
        case WM_COMMAND:
            if (LOWORD(wp) == JX_ID_APPLY) {
                apply_form(hwnd);
                return 0;
            }
            break;
        case JX_WM_API_UPDATE:
            sync_form();
            InvalidateRect(hwnd, NULL, TRUE);
            return 0;
        case WM_PAINT: {
            PAINTSTRUCT ps;
            HDC hdc = BeginPaint(hwnd, &ps);
            paint_page(hwnd, hdc);
            EndPaint(hwnd, &ps);
            return 0;
        }
        case WM_SIZE:
            InvalidateRect(hwnd, NULL, TRUE);
            return 0;
        case WM_DESTROY:
            PostQuitMessage(0);
            return 0;
        default:
            return DefWindowProcA(hwnd, msg, wp, lp);
    }
    return DefWindowProcA(hwnd, msg, wp, lp);
}

int WINAPI WinMain(HINSTANCE instance, HINSTANCE prev_instance, LPSTR command_line, int show_command) {
    (void)prev_instance;
    (void)command_line;
    (void)show_command;
    init_state();

    const char *class_name = "JXPHPNativePageWindow";
    WNDCLASSA wc;
    memset(&wc, 0, sizeof(wc));
    wc.lpfnWndProc = window_proc;
    wc.hInstance = instance;
    wc.lpszClassName = class_name;
    wc.hbrBackground = NULL;
    wc.hCursor = LoadCursor(NULL, IDC_ARROW);

    if (!RegisterClassA(&wc)) {
        MessageBoxA(NULL, "Cannot register window class.", "JX Native Page", MB_ICONERROR | MB_OK);
        return 1;
    }

    HWND hwnd = CreateWindowExA(0, class_name, "JX PHP Native Page - Standalone EXE", WS_OVERLAPPEDWINDOW | WS_VISIBLE,
        CW_USEDEFAULT, CW_USEDEFAULT, 1010, 700, NULL, NULL, instance, NULL);
    if (!hwnd) {
        MessageBoxA(NULL, "Cannot create window.", "JX Native Page", MB_ICONERROR | MB_OK);
        cleanup_state();
        return 1;
    }

    MSG msg;
    while (GetMessageA(&msg, NULL, 0, 0) > 0) {
        TranslateMessage(&msg);
        DispatchMessageA(&msg);
    }

    cleanup_state();
    return 0;
}

int main(int argc, char **argv) {
    (void)argc;
    (void)argv;
    return WinMain(GetModuleHandleA(NULL), NULL, GetCommandLineA(), SW_SHOWDEFAULT);
}
