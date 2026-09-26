#define WIN32_LEAN_AND_MEAN
#include <winsock2.h>
#include <ws2tcpip.h>
#include <windows.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "../runtime/jx_css_runtime.h"

#define JX_API_PORT 8765
#define JX_ID_TITLE 1001
#define JX_ID_BADGE 1002
#define JX_ID_BODY 1003
#define JX_ID_APPLY 1004
#define JX_WM_API_UPDATE (WM_APP + 77)

static const char jx_embedded_css[] =
    "body {\n"
    "    font-family: Arial, sans-serif;\n"
    "    background: #101318;\n"
    "    color: #f4f7fb;\n"
    "    margin: 0;\n"
    "    padding: 2rem;\n"
    "}\n"
    "\n"
    ".card {\n"
    "    border: 1px solid #3b4454;\n"
    "    border-radius: 12px;\n"
    "    padding: 1rem;\n"
    "}\n"
    "\n"
    ".badge {\n"
    "    display: inline-block;\n"
    "    font-weight: 700;\n"
    "    letter-spacing: 0.08em;\n"
    "}\n";

typedef struct {
    char title[160];
    char badge[96];
    char body[512];
} JxPageContent;

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
    int standalone_css;
    JxPageContent content;
    CRITICAL_SECTION lock;
} JxPageDemo;

static JxPageDemo g_page;
static HWND g_hwnd = NULL;
static HWND g_title_edit = NULL;
static HWND g_badge_edit = NULL;
static HWND g_body_edit = NULL;
static HANDLE g_api_thread = NULL;
static volatile LONG g_api_stop = 0;

static void die_last(const char *message) {
    DWORD code = GetLastError();
    char text[256];
    snprintf(text, sizeof(text), "%s: error %lu", message, (unsigned long)code);
    MessageBoxA(NULL, text, "JX Native Page", MB_ICONERROR | MB_OK);
    ExitProcess(1);
}

static void copy_text(char *dst, size_t dst_size, const char *src) {
    if (!dst || dst_size == 0) {
        return;
    }
    if (!src) {
        src = "";
    }
    snprintf(dst, dst_size, "%s", src);
}

static char *copy_bytes(const char *data, size_t len) {
    char *bytes = (char *)malloc(len + 1);
    if (!bytes) {
        return NULL;
    }
    if (len > 0) {
        memcpy(bytes, data, len);
    }
    bytes[len] = '\0';
    return bytes;
}

static char *slice_copy(const char *data, size_t len) {
    char *copy = (char *)malloc(len + 1);
    if (!copy) {
        return NULL;
    }
    memcpy(copy, data, len);
    copy[len] = '\0';
    return copy;
}

static const JxCssDeclaration *css_decl(const char *selector, const char *property) {
    return jx_css_find_property(&g_page.css, selector, property);
}

static char *css_value_copy(const char *selector, const char *property) {
    const JxCssDeclaration *decl = css_decl(selector, property);
    if (!decl) {
        return NULL;
    }
    return slice_copy(decl->value, decl->value_length);
}

static int hex_value(char c) {
    if (c >= '0' && c <= '9') return c - '0';
    if (c >= 'a' && c <= 'f') return 10 + c - 'a';
    if (c >= 'A' && c <= 'F') return 10 + c - 'A';
    return -1;
}

static COLORREF parse_color_value(const char *value, COLORREF fallback) {
    if (!value) {
        return fallback;
    }

    const char *hex = strchr(value, '#');
    if (!hex || strlen(hex) < 7) {
        return fallback;
    }

    int r1 = hex_value(hex[1]);
    int r2 = hex_value(hex[2]);
    int g1 = hex_value(hex[3]);
    int g2 = hex_value(hex[4]);
    int b1 = hex_value(hex[5]);
    int b2 = hex_value(hex[6]);
    if (r1 >= 0 && r2 >= 0 && g1 >= 0 && g2 >= 0 && b1 >= 0 && b2 >= 0) {
        return RGB((r1 << 4) | r2, (g1 << 4) | g2, (b1 << 4) | b2);
    }

    return fallback;
}

static COLORREF css_color(const char *selector, const char *property, COLORREF fallback) {
    char *value = css_value_copy(selector, property);
    COLORREF color = parse_color_value(value, fallback);
    free(value);
    return color;
}

static int css_px(const char *selector, const char *property, int fallback) {
    char *value = css_value_copy(selector, property);
    if (!value) {
        return fallback;
    }

    int n = atoi(value);
    if (strstr(value, "rem")) {
        n *= 16;
    }
    free(value);
    return n > 0 ? n : fallback;
}

static void init_default_content(void) {
    copy_text(g_page.content.title, sizeof(g_page.content.title), "JX Native Dynamic Page");
    copy_text(g_page.content.badge, sizeof(g_page.content.badge), "LOCAL API + FORM");
    copy_text(
        g_page.content.body,
        sizeof(g_page.content.body),
        "This standalone .exe draws a native page without WebView. Update this text with the form, or send a local URL command to the app."
    );
}

static void load_page_css(void) {
    memset(&g_page, 0, sizeof(g_page));
    InitializeCriticalSection(&g_page.lock);
    jx_css_stylesheet_init(&g_page.css);
    init_default_content();

    g_page.css_len = strlen(jx_embedded_css);
    g_page.css_bytes = copy_bytes(jx_embedded_css, g_page.css_len);
    g_page.standalone_css = 1;

    if (g_page.css_bytes) {
        JxCssText css;
        css.data = g_page.css_bytes;
        css.length = g_page.css_len;
        if (!jx_css_parse_text(css, &g_page.css)) {
            MessageBoxA(NULL, "JX could not parse embedded CSS.", "JX Native Page", MB_ICONWARNING | MB_OK);
        }
    }

    g_page.body_bg = css_color("body", "background", RGB(16, 19, 24));
    g_page.body_fg = css_color("body", "color", RGB(244, 247, 251));
    g_page.card_border = css_color(".card", "border", RGB(59, 68, 84));
    g_page.body_padding = css_px("body", "padding", 32);
    g_page.card_padding = css_px(".card", "padding", 16);
    g_page.card_radius = css_px(".card", "border-radius", 12);
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
    HFONT font = CreateFontA(
        -font_size,
        0,
        0,
        0,
        weight,
        FALSE,
        FALSE,
        FALSE,
        ANSI_CHARSET,
        OUT_DEFAULT_PRECIS,
        CLIP_DEFAULT_PRECIS,
        CLEARTYPE_QUALITY,
        DEFAULT_PITCH | FF_DONTCARE,
        "Arial"
    );
    HGDIOBJ old_font = SelectObject(hdc, font);
    SetTextColor(hdc, color);
    SetBkMode(hdc, TRANSPARENT);
    DrawTextA(hdc, text, -1, rect, DT_LEFT | DT_TOP | DT_WORDBREAK);
    SelectObject(hdc, old_font);
    DeleteObject(font);
}

static void snapshot_content(JxPageContent *out) {
    EnterCriticalSection(&g_page.lock);
    *out = g_page.content;
    LeaveCriticalSection(&g_page.lock);
}

static void set_content(const char *title, const char *badge, const char *body) {
    EnterCriticalSection(&g_page.lock);
    if (title && *title) {
        copy_text(g_page.content.title, sizeof(g_page.content.title), title);
    }
    if (badge && *badge) {
        copy_text(g_page.content.badge, sizeof(g_page.content.badge), badge);
    }
    if (body && *body) {
        copy_text(g_page.content.body, sizeof(g_page.content.body), body);
    }
    LeaveCriticalSection(&g_page.lock);
}

static void apply_form_update(HWND hwnd) {
    char title[160];
    char badge[96];
    char body[512];
    GetWindowTextA(g_title_edit, title, sizeof(title));
    GetWindowTextA(g_badge_edit, badge, sizeof(badge));
    GetWindowTextA(g_body_edit, body, sizeof(body));
    set_content(title, badge, body);
    InvalidateRect(hwnd, NULL, TRUE);
}

static void sync_form_from_state(void) {
    JxPageContent current;
    snapshot_content(&current);
    if (g_title_edit) SetWindowTextA(g_title_edit, current.title);
    if (g_badge_edit) SetWindowTextA(g_badge_edit, current.badge);
    if (g_body_edit) SetWindowTextA(g_body_edit, current.body);
}

static void paint_page(HWND hwnd, HDC hdc) {
    RECT client;
    GetClientRect(hwnd, &client);

    HBRUSH bg = CreateSolidBrush(g_page.body_bg);
    FillRect(hdc, &client, bg);
    DeleteObject(bg);

    JxPageContent content;
    snapshot_content(&content);

    int pad = g_page.body_padding;
    RECT page = client;
    page.left += pad;
    page.top += pad;
    page.right -= pad;
    page.bottom -= pad;

    RECT render_area = page;
    render_area.right -= 330;

    RECT hero = render_area;
    hero.bottom = hero.top + 84;
    draw_text_block(hdc, content.title, &hero, 32, FW_BOLD, g_page.body_fg);

    RECT intro = render_area;
    intro.top += 56;
    intro.bottom = intro.top + 78;
    draw_text_block(
        hdc,
        "This page is native Win32 GDI. No WebView. No browser. The form and local URL API update this layout live.",
        &intro,
        18,
        FW_NORMAL,
        g_page.body_fg
    );

    RECT card = render_area;
    card.top += 146;
    card.bottom = card.top + 290;
    fill_round_rect(hdc, card, g_page.card_radius, RGB(24, 30, 40), g_page.card_border);

    RECT inner = card;
    inner.left += g_page.card_padding;
    inner.top += g_page.card_padding;
    inner.right -= g_page.card_padding;
    inner.bottom -= g_page.card_padding;

    RECT badge = inner;
    badge.bottom = badge.top + 30;
    draw_text_block(hdc, content.badge, &badge, 16, FW_BOLD, RGB(145, 220, 255));

    RECT body = inner;
    body.top += 46;
    body.bottom = body.top + 110;
    draw_text_block(hdc, content.body, &body, 19, FW_NORMAL, g_page.body_fg);

    RECT api = inner;
    api.top += 168;
    api.bottom = api.top + 76;
    draw_text_block(
        hdc,
        "Local API: http://127.0.0.1:8765/update?title=Hello&badge=LIVE&body=Updated+from+URL",
        &api,
        15,
        FW_NORMAL,
        RGB(210, 220, 232)
    );

    RECT panel = page;
    panel.left = panel.right - 300;
    panel.bottom = panel.top + 430;
    fill_round_rect(hdc, panel, 14, RGB(28, 34, 45), RGB(67, 78, 96));

    RECT panel_title = panel;
    panel_title.left += 18;
    panel_title.top += 16;
    panel_title.right -= 18;
    panel_title.bottom = panel_title.top + 28;
    draw_text_block(hdc, "Dynamic Form", &panel_title, 20, FW_BOLD, g_page.body_fg);

    RECT note = panel_title;
    note.top += 300;
    note.bottom = note.top + 70;
    draw_text_block(
        hdc,
        "URL listener is bound only to 127.0.0.1. Use it for local testing of page updates.",
        &note,
        14,
        FW_NORMAL,
        RGB(210, 220, 232)
    );
}

static int from_hex(char c) {
    if (c >= '0' && c <= '9') return c - '0';
    if (c >= 'a' && c <= 'f') return 10 + c - 'a';
    if (c >= 'A' && c <= 'F') return 10 + c - 'A';
    return -1;
}

static void url_decode(char *text) {
    char *read = text;
    char *write = text;
    while (*read) {
        if (*read == '+') {
            *write++ = ' ';
            ++read;
        } else if (*read == '%' && read[1] && read[2]) {
            int hi = from_hex(read[1]);
            int lo = from_hex(read[2]);
            if (hi >= 0 && lo >= 0) {
                *write++ = (char)((hi << 4) | lo);
                read += 3;
            } else {
                *write++ = *read++;
            }
        } else {
            *write++ = *read++;
        }
    }
    *write = '\0';
}

static void parse_query_value(const char *query, const char *key, char *out, size_t out_size) {
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
        if (p) {
            ++p;
        }
    }
}

static void send_http_response(SOCKET client, const char *body) {
    char response[512];
    snprintf(
        response,
        sizeof(response),
        "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\nAccess-Control-Allow-Origin: *\r\nContent-Length: %u\r\nConnection: close\r\n\r\n%s",
        (unsigned)strlen(body),
        body
    );
    send(client, response, (int)strlen(response), 0);
}

static DWORD WINAPI api_thread_proc(LPVOID arg) {
    (void)arg;

    WSADATA wsa;
    if (WSAStartup(MAKEWORD(2, 2), &wsa) != 0) {
        return 1;
    }

    SOCKET server = socket(AF_INET, SOCK_STREAM, IPPROTO_TCP);
    if (server == INVALID_SOCKET) {
        WSACleanup();
        return 1;
    }

    u_long non_blocking = 1;
    ioctlsocket(server, FIONBIO, &non_blocking);

    struct sockaddr_in addr;
    memset(&addr, 0, sizeof(addr));
    addr.sin_family = AF_INET;
    addr.sin_addr.s_addr = htonl(INADDR_LOOPBACK);
    addr.sin_port = htons(JX_API_PORT);

    if (bind(server, (struct sockaddr *)&addr, sizeof(addr)) == SOCKET_ERROR) {
        closesocket(server);
        WSACleanup();
        return 1;
    }

    if (listen(server, 8) == SOCKET_ERROR) {
        closesocket(server);
        WSACleanup();
        return 1;
    }

    while (InterlockedCompareExchange(&g_api_stop, 0, 0) == 0) {
        SOCKET client = accept(server, NULL, NULL);
        if (client == INVALID_SOCKET) {
            Sleep(30);
            continue;
        }

        char request[4096];
        int got = recv(client, request, sizeof(request) - 1, 0);
        if (got > 0) {
            request[got] = '\0';
            char *path_start = strchr(request, ' ');
            char *path_end = NULL;
            if (path_start) {
                ++path_start;
                path_end = strchr(path_start, ' ');
            }
            if (path_start && path_end) {
                *path_end = '\0';
                if (strncmp(path_start, "/update?", 8) == 0) {
                    const char *query = path_start + 8;
                    JxPageContent *update = (JxPageContent *)calloc(1, sizeof(JxPageContent));
                    if (update) {
                        parse_query_value(query, "title", update->title, sizeof(update->title));
                        parse_query_value(query, "badge", update->badge, sizeof(update->badge));
                        parse_query_value(query, "body", update->body, sizeof(update->body));
                        PostMessageA(g_hwnd, JX_WM_API_UPDATE, 0, (LPARAM)update);
                    }
                    send_http_response(client, "JX update accepted\n");
                } else {
                    send_http_response(client, "JX native page API. Use /update?title=...&badge=...&body=...\n");
                }
            }
        }
        closesocket(client);
    }

    closesocket(server);
    WSACleanup();
    return 0;
}

static void start_api_thread(void) {
    g_api_thread = CreateThread(NULL, 0, api_thread_proc, NULL, 0, NULL);
}

static void create_form_controls(HWND hwnd) {
    CreateWindowExA(0, "STATIC", "Title", WS_CHILD | WS_VISIBLE, 696, 96, 260, 18, hwnd, NULL, NULL, NULL);
    g_title_edit = CreateWindowExA(WS_EX_CLIENTEDGE, "EDIT", g_page.content.title, WS_CHILD | WS_VISIBLE | ES_AUTOHSCROLL, 696, 116, 250, 24, hwnd, (HMENU)JX_ID_TITLE, NULL, NULL);

    CreateWindowExA(0, "STATIC", "Badge", WS_CHILD | WS_VISIBLE, 696, 152, 260, 18, hwnd, NULL, NULL, NULL);
    g_badge_edit = CreateWindowExA(WS_EX_CLIENTEDGE, "EDIT", g_page.content.badge, WS_CHILD | WS_VISIBLE | ES_AUTOHSCROLL, 696, 172, 250, 24, hwnd, (HMENU)JX_ID_BADGE, NULL, NULL);

    CreateWindowExA(0, "STATIC", "Body", WS_CHILD | WS_VISIBLE, 696, 208, 260, 18, hwnd, NULL, NULL, NULL);
    g_body_edit = CreateWindowExA(WS_EX_CLIENTEDGE, "EDIT", g_page.content.body, WS_CHILD | WS_VISIBLE | WS_VSCROLL | ES_MULTILINE | ES_AUTOVSCROLL, 696, 228, 250, 100, hwnd, (HMENU)JX_ID_BODY, NULL, NULL);

    CreateWindowExA(0, "BUTTON", "Apply", WS_CHILD | WS_VISIBLE | BS_DEFPUSHBUTTON, 696, 346, 120, 32, hwnd, (HMENU)JX_ID_APPLY, NULL, NULL);
}

static LRESULT CALLBACK window_proc(HWND hwnd, UINT msg, WPARAM wp, LPARAM lp) {
    switch (msg) {
        case WM_CREATE:
            create_form_controls(hwnd);
            start_api_thread();
            return 0;
        case WM_COMMAND:
            if (LOWORD(wp) == JX_ID_APPLY) {
                apply_form_update(hwnd);
                return 0;
            }
            break;
        case JX_WM_API_UPDATE: {
            JxPageContent *update = (JxPageContent *)lp;
            if (update) {
                set_content(update->title, update->badge, update->body);
                free(update);
                sync_form_from_state();
                InvalidateRect(hwnd, NULL, TRUE);
            }
            return 0;
        }
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
            InterlockedExchange(&g_api_stop, 1);
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

    load_page_css();

    const char *class_name = "JXNativePageWindow";

    WNDCLASSA wc;
    ZeroMemory(&wc, sizeof(wc));
    wc.lpfnWndProc = window_proc;
    wc.hInstance = instance;
    wc.lpszClassName = class_name;
    wc.hbrBackground = NULL;
    wc.hCursor = LoadCursor(NULL, IDC_ARROW);

    if (!RegisterClassA(&wc)) {
        die_last("cannot register native page window class");
    }

    HWND hwnd = CreateWindowExA(
        0,
        class_name,
        "JX Native Page Demo - Dynamic Standalone EXE",
        WS_OVERLAPPEDWINDOW | WS_VISIBLE,
        CW_USEDEFAULT,
        CW_USEDEFAULT,
        1040,
        720,
        NULL,
        NULL,
        instance,
        NULL
    );

    if (!hwnd) {
        die_last("cannot create native page window");
    }

    g_hwnd = hwnd;

    MSG msg;
    while (GetMessageA(&msg, NULL, 0, 0) > 0) {
        TranslateMessage(&msg);
        DispatchMessageA(&msg);
    }

    if (g_api_thread) {
        WaitForSingleObject(g_api_thread, 250);
        CloseHandle(g_api_thread);
    }

    jx_css_stylesheet_free(&g_page.css);
    free(g_page.css_bytes);
    DeleteCriticalSection(&g_page.lock);
    return 0;
}

int main(int argc, char **argv) {
    HINSTANCE instance = GetModuleHandleA(NULL);
    (void)argc;
    (void)argv;
    return WinMain(instance, NULL, GetCommandLineA(), SW_SHOWDEFAULT);
}
