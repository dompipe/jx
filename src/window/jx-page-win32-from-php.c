#define WIN32_LEAN_AND_MEAN
#include <winsock2.h>
#include <ws2tcpip.h>
#include <windows.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "../runtime/jx_css_runtime.h"
#include "jx_php_page_data.h"

#ifndef JX_PAGE_MODAL_TITLE
#define JX_PAGE_MODAL_TITLE "JX Native Modal"
#endif
#ifndef JX_PAGE_MODAL_BODY
#define JX_PAGE_MODAL_BODY "This modal was declared in PHP and compiled into the native executable."
#endif
#ifndef JX_PAGE_IFRAME_TITLE
#define JX_PAGE_IFRAME_TITLE "Native Iframe"
#endif
#ifndef JX_PAGE_IFRAME_HTML
#define JX_PAGE_IFRAME_HTML "<p>Iframe HTML declared in PHP.</p>"
#endif

#define JX_API_PORT 8765
#define JX_ID_TITLE 1001
#define JX_ID_BADGE 1002
#define JX_ID_BODY 1003
#define JX_ID_APPLY 1004
#define JX_ID_MODAL 1005
#define JX_WM_API_UPDATE (WM_APP + 77)
#define JX_WM_API_MODAL (WM_APP + 78)
#define JX_WM_API_IFRAME (WM_APP + 79)

typedef struct { char title[160]; char badge[96]; char body[512]; } JxPageContent;
typedef struct { char title[160]; char body[512]; int visible; } JxModalContent;
typedef struct { char title[160]; char html[1024]; } JxIframeContent;

typedef struct {
    JxCssStylesheet css;
    char *css_bytes;
    size_t css_len;

    COLORREF body_bg;
    COLORREF body_fg;
    COLORREF card_bg;
    COLORREF card_border;
    COLORREF badge_fg;
    COLORREF panel_bg;
    COLORREF panel_border;
    COLORREF iframe_bg;
    COLORREF iframe_fg;
    COLORREF iframe_border;
    COLORREF iframe_chrome_bg;
    COLORREF iframe_chrome_fg;
    COLORREF modal_overlay_bg;
    COLORREF modal_bg;
    COLORREF modal_fg;
    COLORREF modal_border;
    COLORREF modal_close_bg;
    COLORREF modal_close_fg;
    COLORREF modal_close_border;

    int body_padding;
    int card_padding;
    int card_radius;
    int panel_radius;
    int iframe_radius;
    int modal_radius;
    int window_width;
    int window_height;
    int panel_width;
    int layout_gap;
    int min_content_width;
    int api_port;

    JxPageContent content;
    JxModalContent modal;
    JxIframeContent iframe;
    CRITICAL_SECTION lock;
} JxPageDemo;

static JxPageDemo g_page;
static HWND g_hwnd = NULL;
static HWND g_title_label = NULL;
static HWND g_badge_label = NULL;
static HWND g_body_label = NULL;
static HWND g_title_edit = NULL;
static HWND g_badge_edit = NULL;
static HWND g_body_edit = NULL;
static HWND g_apply_button = NULL;
static HWND g_modal_button = NULL;
static HANDLE g_api_thread = NULL;
static volatile LONG g_api_stop = 0;
static RECT g_modal_close_rect;

static int jx_max_i(int a, int b) { return a > b ? a : b; }
static int jx_min_i(int a, int b) { return a < b ? a : b; }

static void die_last(const char *message) {
    DWORD code = GetLastError();
    char text[256];
    snprintf(text, sizeof(text), "%s: error %lu", message, (unsigned long)code);
    MessageBoxA(NULL, text, "JX Native Page", MB_ICONERROR | MB_OK);
    ExitProcess(1);
}

static void warn_text(const char *message) {
    MessageBoxA(NULL, message, "JX Native Page", MB_ICONWARNING | MB_OK);
}

static int parse_port_argument(const char *command_line, int fallback) {
    const char *keys[] = { "--port", "/port", "-port", NULL };
    if (!command_line || !*command_line) return fallback;
    for (int i = 0; keys[i]; ++i) {
        const char *p = strstr(command_line, keys[i]);
        if (!p) continue;
        p += strlen(keys[i]);
        while (*p == ' ' || *p == '\t' || *p == '=' || *p == ':') ++p;
        if (*p == '"' || *p == '\'') ++p;
        int port = atoi(p);
        if (port >= 1024 && port <= 65535) return port;
    }
    return fallback;
}

static void copy_text(char *dst, size_t dst_size, const char *src) {
    if (!dst || dst_size == 0) return;
    if (!src) src = "";
    snprintf(dst, dst_size, "%s", src);
}

static char *copy_bytes(const char *data, size_t len) {
    char *bytes = (char *)malloc(len + 1);
    if (!bytes) return NULL;
    if (len > 0) memcpy(bytes, data, len);
    bytes[len] = '\0';
    return bytes;
}

static char *slice_copy(const char *data, size_t len) {
    char *copy = (char *)malloc(len + 1);
    if (!copy) return NULL;
    memcpy(copy, data, len);
    copy[len] = '\0';
    return copy;
}

static const JxCssDeclaration *css_decl(const char *selector, const char *property) {
    return jx_css_find_property(&g_page.css, selector, property);
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
    if (!value) return fallback;
    int n = atoi(value);
    if (strstr(value, "rem")) n *= 16;
    free(value);
    return n > 0 ? n : fallback;
}

static void init_default_content(void) {
    copy_text(g_page.content.title, sizeof(g_page.content.title), JX_PAGE_TITLE);
    copy_text(g_page.content.badge, sizeof(g_page.content.badge), JX_PAGE_BADGE);
    copy_text(g_page.content.body, sizeof(g_page.content.body), JX_PAGE_BODY);
    copy_text(g_page.modal.title, sizeof(g_page.modal.title), JX_PAGE_MODAL_TITLE);
    copy_text(g_page.modal.body, sizeof(g_page.modal.body), JX_PAGE_MODAL_BODY);
    g_page.modal.visible = 0;
    copy_text(g_page.iframe.title, sizeof(g_page.iframe.title), JX_PAGE_IFRAME_TITLE);
    copy_text(g_page.iframe.html, sizeof(g_page.iframe.html), JX_PAGE_IFRAME_HTML);
}

static void load_page_css(void) {
    memset(&g_page, 0, sizeof(g_page));
    InitializeCriticalSection(&g_page.lock);
    jx_css_stylesheet_init(&g_page.css);
    init_default_content();
    g_page.api_port = JX_API_PORT;

    g_page.css_len = strlen(JX_PAGE_CSS);
    g_page.css_bytes = copy_bytes(JX_PAGE_CSS, g_page.css_len);
    if (g_page.css_bytes) {
        JxCssText css;
        css.data = g_page.css_bytes;
        css.length = g_page.css_len;
        if (!jx_css_parse_text(css, &g_page.css)) {
            MessageBoxA(NULL, "JX could not parse PHP page CSS.", "JX Native Page", MB_ICONWARNING | MB_OK);
        }
    }

    g_page.window_width = css_px("window", "width", 1040);
    g_page.window_height = css_px("window", "height", 760);
    g_page.panel_width = css_px("window", "panel-width", 300);
    g_page.layout_gap = css_px("window", "gap", 30);
    g_page.min_content_width = css_px("window", "min-content-width", 480);

    g_page.body_bg = css_color("#demo-page", "background", css_color("body", "background", RGB(16, 19, 24)));
    g_page.body_fg = css_color("#demo-page", "color", css_color("body", "color", RGB(244, 247, 251)));

    g_page.card_bg = css_color("#main-card", "background", css_color(".card", "background", RGB(24, 30, 40)));
    g_page.card_border = css_color("#main-card", "border", css_color(".card", "border", RGB(59, 68, 84)));
    g_page.card_padding = css_px("#main-card", "padding", css_px(".card", "padding", 16));
    g_page.card_radius = css_px("#main-card", "border-radius", css_px(".card", "border-radius", 12));
    g_page.badge_fg = css_color("#badge", "color", css_color(".badge", "color", RGB(145, 220, 255)));

    g_page.panel_bg = css_color("#dynamic-form", "background", css_color(".panel", "background", RGB(28, 34, 45)));
    g_page.panel_border = css_color("#dynamic-form", "border", css_color(".panel", "border", RGB(67, 78, 96)));
    g_page.panel_radius = css_px("#dynamic-form", "border-radius", css_px(".panel", "border-radius", 14));

    g_page.iframe_bg = css_color("#native-iframe", "background", css_color(".iframe", "background", RGB(14, 18, 26)));
    g_page.iframe_fg = css_color("#native-iframe", "color", css_color(".iframe", "color", RGB(236, 240, 247)));
    g_page.iframe_border = css_color("#native-iframe", "border", css_color(".iframe", "border", RGB(75, 88, 110)));
    g_page.iframe_radius = css_px("#native-iframe", "border-radius", css_px(".iframe", "border-radius", 10));
    g_page.iframe_chrome_bg = css_color("#native-iframe-chrome", "background", css_color(".iframe-chrome", "background", RGB(32, 39, 52)));
    g_page.iframe_chrome_fg = css_color("#native-iframe-chrome", "color", css_color(".iframe-chrome", "color", RGB(220, 230, 242)));

    g_page.modal_overlay_bg = css_color("#help-modal-overlay", "background", css_color(".modal-overlay", "background", RGB(4, 6, 10)));
    g_page.modal_bg = css_color("#help-modal", "background", css_color(".modal", "background", RGB(28, 34, 45)));
    g_page.modal_fg = css_color("#help-modal", "color", css_color(".modal", "color", RGB(226, 233, 242)));
    g_page.modal_border = css_color("#help-modal", "border", css_color(".modal", "border", RGB(90, 110, 136)));
    g_page.modal_radius = css_px("#help-modal", "border-radius", css_px(".modal", "border-radius", 18));
    g_page.modal_close_bg = css_color("#help-modal-close", "background", css_color(".modal-close", "background", RGB(50, 60, 76)));
    g_page.modal_close_fg = css_color("#help-modal-close", "color", css_color(".modal-close", "color", RGB(245, 248, 252)));
    g_page.modal_close_border = css_color("#help-modal-close", "border", css_color(".modal-close", "border", RGB(120, 140, 160)));

    g_page.body_padding = css_px("body", "padding", 32);
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

static void html_to_plain(const char *html, char *out, size_t out_size) {
    size_t j = 0;
    int in_tag = 0;
    if (out_size == 0) return;
    for (size_t i = 0; html && html[i] && j + 1 < out_size; ++i) {
        char c = html[i];
        if (c == '<') { in_tag = 1; if (j > 0 && out[j - 1] != '\n' && j + 1 < out_size) out[j++] = '\n'; continue; }
        if (c == '>') { in_tag = 0; continue; }
        if (!in_tag) {
            if (strncmp(html + i, "&lt;", 4) == 0) { out[j++] = '<'; i += 3; }
            else if (strncmp(html + i, "&gt;", 4) == 0) { out[j++] = '>'; i += 3; }
            else if (strncmp(html + i, "&amp;", 5) == 0) { out[j++] = '&'; i += 4; }
            else out[j++] = c;
        }
    }
    out[j] = '\0';
}

static void snapshot_content(JxPageContent *out) { EnterCriticalSection(&g_page.lock); *out = g_page.content; LeaveCriticalSection(&g_page.lock); }
static void snapshot_modal(JxModalContent *out) { EnterCriticalSection(&g_page.lock); *out = g_page.modal; LeaveCriticalSection(&g_page.lock); }
static void snapshot_iframe(JxIframeContent *out) { EnterCriticalSection(&g_page.lock); *out = g_page.iframe; LeaveCriticalSection(&g_page.lock); }

static void set_one_control_visible(HWND hwnd, int visible) {
    if (!hwnd) return;
    ShowWindow(hwnd, visible ? SW_SHOW : SW_HIDE);
    EnableWindow(hwnd, visible ? TRUE : FALSE);
}

static void set_form_controls_visible(int visible) {
    set_one_control_visible(g_title_label, visible);
    set_one_control_visible(g_title_edit, visible);
    set_one_control_visible(g_badge_label, visible);
    set_one_control_visible(g_badge_edit, visible);
    set_one_control_visible(g_body_label, visible);
    set_one_control_visible(g_body_edit, visible);
    set_one_control_visible(g_apply_button, visible);
    set_one_control_visible(g_modal_button, visible);
    if (!visible && g_hwnd) SetFocus(g_hwnd);
}

static void sync_controls_for_modal(void) {
    JxModalContent modal;
    snapshot_modal(&modal);
    set_form_controls_visible(!modal.visible);
}

static void set_content(const char *title, const char *badge, const char *body) {
    EnterCriticalSection(&g_page.lock);
    if (title && *title) copy_text(g_page.content.title, sizeof(g_page.content.title), title);
    if (badge && *badge) copy_text(g_page.content.badge, sizeof(g_page.content.badge), badge);
    if (body && *body) copy_text(g_page.content.body, sizeof(g_page.content.body), body);
    LeaveCriticalSection(&g_page.lock);
}

static void show_modal(const char *title, const char *body) {
    EnterCriticalSection(&g_page.lock);
    if (title && *title) copy_text(g_page.modal.title, sizeof(g_page.modal.title), title);
    if (body && *body) copy_text(g_page.modal.body, sizeof(g_page.modal.body), body);
    g_page.modal.visible = 1;
    LeaveCriticalSection(&g_page.lock);
}

static void hide_modal(void) { EnterCriticalSection(&g_page.lock); g_page.modal.visible = 0; LeaveCriticalSection(&g_page.lock); }

static void set_iframe(const char *title, const char *html) {
    EnterCriticalSection(&g_page.lock);
    if (title && *title) copy_text(g_page.iframe.title, sizeof(g_page.iframe.title), title);
    if (html && *html) copy_text(g_page.iframe.html, sizeof(g_page.iframe.html), html);
    LeaveCriticalSection(&g_page.lock);
}

static void sync_form_from_state(void) {
    JxPageContent current;
    snapshot_content(&current);
    if (g_title_edit) SetWindowTextA(g_title_edit, current.title);
    if (g_badge_edit) SetWindowTextA(g_badge_edit, current.badge);
    if (g_body_edit) SetWindowTextA(g_body_edit, current.body);
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

static void layout_form_controls(HWND hwnd) {
    RECT client;
    GetClientRect(hwnd, &client);
    int pad = g_page.body_padding;
    int client_width = client.right - client.left;
    int available = client_width - (pad * 2);
    int panel_width = g_page.panel_width;
    if (available < g_page.panel_width + g_page.layout_gap + g_page.min_content_width) {
        panel_width = jx_max_i(230, available - g_page.layout_gap - g_page.min_content_width);
    }
    panel_width = jx_min_i(g_page.panel_width, panel_width);
    int left = jx_max_i(pad, client.right - pad - panel_width + 18);
    int width = jx_max_i(180, panel_width - 36);
    int y = pad + 68;

    MoveWindow(g_title_label, left, y, width, 20, TRUE); y += 22;
    MoveWindow(g_title_edit, left, y, width, 26, TRUE); y += 36;
    MoveWindow(g_badge_label, left, y, width, 20, TRUE); y += 22;
    MoveWindow(g_badge_edit, left, y, width, 26, TRUE); y += 36;
    MoveWindow(g_body_label, left, y, width, 20, TRUE); y += 22;
    MoveWindow(g_body_edit, left, y, width, 92, TRUE); y += 108;
    MoveWindow(g_apply_button, left, y, (width - 14) / 2, 32, TRUE);
    MoveWindow(g_modal_button, left + ((width - 14) / 2) + 14, y, (width - 14) / 2, 32, TRUE);
}

static void paint_iframe(HDC hdc, RECT frame, const JxIframeContent *iframe) {
    fill_round_rect(hdc, frame, g_page.iframe_radius, g_page.iframe_bg, g_page.iframe_border);
    RECT chrome = frame;
    chrome.bottom = chrome.top + 34;
    HBRUSH bar = CreateSolidBrush(g_page.iframe_chrome_bg);
    FillRect(hdc, &chrome, bar);
    DeleteObject(bar);
    RECT title = chrome;
    title.left += 14;
    title.top += 7;
    title.right -= 14;
    draw_text_block(hdc, iframe->title, &title, 15, FW_BOLD, g_page.iframe_chrome_fg);
    RECT body = frame;
    body.left += 14;
    body.top += 46;
    body.right -= 14;
    body.bottom -= 14;
    char plain[1200];
    html_to_plain(iframe->html, plain, sizeof(plain));
    draw_text_block(hdc, plain, &body, 16, FW_NORMAL, g_page.iframe_fg);
}

static void paint_modal(HWND hwnd, HDC hdc, const JxModalContent *modal) {
    if (!modal->visible) return;
    RECT client;
    GetClientRect(hwnd, &client);
    HBRUSH shade = CreateSolidBrush(g_page.modal_overlay_bg);
    FillRect(hdc, &client, shade);
    DeleteObject(shade);
    int width = jx_min_i(520, (client.right - client.left) - 80);
    int height = 260;
    RECT box;
    box.left = client.left + ((client.right - client.left) - width) / 2;
    box.top = client.top + ((client.bottom - client.top) - height) / 2;
    box.right = box.left + width;
    box.bottom = box.top + height;
    fill_round_rect(hdc, box, g_page.modal_radius, g_page.modal_bg, g_page.modal_border);
    RECT title = box;
    title.left += 28;
    title.top += 24;
    title.right -= 80;
    title.bottom = title.top + 42;
    draw_text_block(hdc, modal->title, &title, 24, FW_BOLD, g_page.body_fg);
    g_modal_close_rect.left = box.right - 62;
    g_modal_close_rect.top = box.top + 22;
    g_modal_close_rect.right = box.right - 24;
    g_modal_close_rect.bottom = box.top + 58;
    fill_round_rect(hdc, g_modal_close_rect, 10, g_page.modal_close_bg, g_page.modal_close_border);
    RECT close_text = g_modal_close_rect;
    close_text.left += 12;
    close_text.top += 7;
    draw_text_block(hdc, "X", &close_text, 16, FW_BOLD, g_page.modal_close_fg);
    RECT body = box;
    body.left += 28;
    body.top += 84;
    body.right -= 28;
    body.bottom -= 28;
    draw_text_block(hdc, modal->body, &body, 18, FW_NORMAL, g_page.modal_fg);
}

static void paint_page(HWND hwnd, HDC hdc) {
    RECT client;
    GetClientRect(hwnd, &client);
    HBRUSH bg = CreateSolidBrush(g_page.body_bg);
    FillRect(hdc, &client, bg);
    DeleteObject(bg);

    JxPageContent content;
    JxModalContent modal;
    JxIframeContent iframe;
    snapshot_content(&content);
    snapshot_modal(&modal);
    snapshot_iframe(&iframe);

    int pad = g_page.body_padding;
    RECT page = client;
    page.left += pad;
    page.top += pad;
    page.right -= pad;
    page.bottom -= pad;

    int available = page.right - page.left;
    int panel_width = g_page.panel_width;
    if (available < g_page.panel_width + g_page.layout_gap + g_page.min_content_width) {
        panel_width = jx_max_i(230, available - g_page.layout_gap - g_page.min_content_width);
    }
    panel_width = jx_min_i(g_page.panel_width, panel_width);

    RECT render_area = page;
    render_area.right -= panel_width + g_page.layout_gap;
    if (render_area.right < render_area.left + 260) {
        render_area.right = render_area.left + jx_max_i(260, available - panel_width - g_page.layout_gap);
    }

    RECT hero = render_area;
    hero.bottom = hero.top + 84;
    draw_text_block(hdc, content.title, &hero, 32, FW_BOLD, g_page.body_fg);

    RECT intro = render_area;
    intro.top += 56;
    intro.bottom = intro.top + 78;
    char intro_text[256];
    snprintf(intro_text, sizeof(intro_text), "This instance listens on http://127.0.0.1:%d for local URL updates.", g_page.api_port);
    draw_text_block(hdc, intro_text, &intro, 18, FW_NORMAL, g_page.body_fg);

    RECT card = render_area;
    card.top += 146;
    card.bottom = card.top + 235;
    fill_round_rect(hdc, card, g_page.card_radius, g_page.card_bg, g_page.card_border);

    RECT inner = card;
    inner.left += g_page.card_padding;
    inner.top += g_page.card_padding;
    inner.right -= g_page.card_padding;
    inner.bottom -= g_page.card_padding;
    RECT badge = inner;
    badge.bottom = badge.top + 30;
    draw_text_block(hdc, content.badge, &badge, 16, FW_BOLD, g_page.badge_fg);
    RECT body = inner;
    body.top += 46;
    body.bottom = body.top + 108;
    draw_text_block(hdc, content.body, &body, 18, FW_NORMAL, g_page.body_fg);

    RECT iframe_rect = render_area;
    iframe_rect.top += 400;
    iframe_rect.bottom = jx_min_i(iframe_rect.top + 190, page.bottom);
    paint_iframe(hdc, iframe_rect, &iframe);

    RECT panel = page;
    panel.left = panel.right - panel_width;
    panel.bottom = panel.top + 500;
    fill_round_rect(hdc, panel, g_page.panel_radius, g_page.panel_bg, g_page.panel_border);
    RECT panel_title = panel;
    panel_title.left += 18;
    panel_title.top += 16;
    panel_title.right -= 18;
    panel_title.bottom = panel_title.top + 28;
    draw_text_block(hdc, "Dynamic Form + Modal", &panel_title, 20, FW_BOLD, g_page.body_fg);
    RECT note = panel_title;
    note.top += 340;
    note.bottom = note.top + 120;
    char note_text[256];
    snprintf(note_text, sizeof(note_text), "Local APIs on this copy:\n/update, /modal, /iframe\nPort: %d\nRun another copy with --port 8766", g_page.api_port);
    draw_text_block(hdc, note_text, &note, 14, FW_NORMAL, RGB(210, 220, 232));

    paint_modal(hwnd, hdc, &modal);
}

static int from_hex(char c) { if (c >= '0' && c <= '9') return c - '0'; if (c >= 'a' && c <= 'f') return 10 + c - 'a'; if (c >= 'A' && c <= 'F') return 10 + c - 'A'; return -1; }

static void url_decode(char *text) {
    char *read = text;
    char *write = text;
    while (*read) {
        if (*read == '+') { *write++ = ' '; ++read; }
        else if (*read == '%' && read[1] && read[2]) {
            int hi = from_hex(read[1]);
            int lo = from_hex(read[2]);
            if (hi >= 0 && lo >= 0) { *write++ = (char)((hi << 4) | lo); read += 3; }
            else { *write++ = *read++; }
        } else { *write++ = *read++; }
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
            while (p[len] && p[len] != '&' && len + 1 < out_size) { out[len] = p[len]; ++len; }
            out[len] = '\0';
            url_decode(out);
            return;
        }
        p = strchr(p, '&');
        if (p) ++p;
    }
}

static void send_http_response(SOCKET client, const char *body) {
    char response[1024];
    snprintf(response, sizeof(response),
        "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\nAccess-Control-Allow-Origin: *\r\nContent-Length: %zu\r\nConnection: close\r\n\r\n%s",
        strlen(body), body);
    send(client, response, (int)strlen(response), 0);
}

static DWORD WINAPI api_thread_proc(LPVOID unused) {
    (void)unused;
    WSADATA wsa;
    if (WSAStartup(MAKEWORD(2, 2), &wsa) != 0) return 1;
    SOCKET server = socket(AF_INET, SOCK_STREAM, IPPROTO_TCP);
    if (server == INVALID_SOCKET) { WSACleanup(); return 1; }
    BOOL opt = TRUE;
    setsockopt(server, SOL_SOCKET, SO_REUSEADDR, (const char *)&opt, sizeof(opt));
    struct sockaddr_in addr;
    ZeroMemory(&addr, sizeof(addr));
    addr.sin_family = AF_INET;
    addr.sin_port = htons((u_short)g_page.api_port);
    addr.sin_addr.s_addr = htonl(INADDR_LOOPBACK);
    if (bind(server, (struct sockaddr *)&addr, sizeof(addr)) == SOCKET_ERROR || listen(server, 8) == SOCKET_ERROR) {
        char text[256];
        snprintf(text, sizeof(text), "Local API port %d is already in use. Run this copy with --port 8766 or another free port.", g_page.api_port);
        warn_text(text);
        closesocket(server);
        WSACleanup();
        return 1;
    }
    while (InterlockedCompareExchange(&g_api_stop, 0, 0) == 0) {
        SOCKET client = accept(server, NULL, NULL);
        if (client == INVALID_SOCKET) break;
        char request[4096];
        int received = recv(client, request, sizeof(request) - 1, 0);
        if (received <= 0) { closesocket(client); continue; }
        request[received] = '\0';
        char *path_start = strchr(request, ' ');
        char *path_end = NULL;
        if (path_start) { ++path_start; path_end = strchr(path_start, ' '); }
        if (!path_start || !path_end) { send_http_response(client, "bad request\n"); closesocket(client); continue; }
        *path_end = '\0';
        char *query = strchr(path_start, '?');
        if (query) *query++ = '\0';
        if (strcmp(path_start, "/update") == 0) {
            char title[160], badge[96], body[512];
            parse_query_value(query ? query : "", "title", title, sizeof(title));
            parse_query_value(query ? query : "", "badge", badge, sizeof(badge));
            parse_query_value(query ? query : "", "body", body, sizeof(body));
            set_content(title, badge, body);
            if (g_hwnd) PostMessageA(g_hwnd, JX_WM_API_UPDATE, 0, 0);
            send_http_response(client, "updated\n");
        } else if (strcmp(path_start, "/modal") == 0) {
            char title[160], body[512];
            parse_query_value(query ? query : "", "title", title, sizeof(title));
            parse_query_value(query ? query : "", "body", body, sizeof(body));
            show_modal(title, body);
            if (g_hwnd) PostMessageA(g_hwnd, JX_WM_API_MODAL, 0, 0);
            send_http_response(client, "modal opened\n");
        } else if (strcmp(path_start, "/iframe") == 0) {
            char title[160], html[1024];
            parse_query_value(query ? query : "", "title", title, sizeof(title));
            parse_query_value(query ? query : "", "html", html, sizeof(html));
            set_iframe(title, html);
            if (g_hwnd) PostMessageA(g_hwnd, JX_WM_API_IFRAME, 0, 0);
            send_http_response(client, "iframe updated\n");
        } else {
            char help[512];
            snprintf(help, sizeof(help), "JX native page API on port %d\n/update?title=...&badge=...&body=...\n/modal?title=...&body=...\n/iframe?title=...&html=...\n", g_page.api_port);
            send_http_response(client, help);
        }
        closesocket(client);
    }
    closesocket(server);
    WSACleanup();
    return 0;
}

static void create_form_controls(HWND hwnd) {
    HINSTANCE instance = GetModuleHandleA(NULL);
    g_title_label = CreateWindowExA(0, "STATIC", "Title", WS_CHILD | WS_VISIBLE, 0, 0, 1, 1, hwnd, NULL, instance, NULL);
    g_title_edit = CreateWindowExA(WS_EX_CLIENTEDGE, "EDIT", "", WS_CHILD | WS_VISIBLE | ES_AUTOHSCROLL, 0, 0, 1, 1, hwnd, (HMENU)JX_ID_TITLE, instance, NULL);
    g_badge_label = CreateWindowExA(0, "STATIC", "Badge", WS_CHILD | WS_VISIBLE, 0, 0, 1, 1, hwnd, NULL, instance, NULL);
    g_badge_edit = CreateWindowExA(WS_EX_CLIENTEDGE, "EDIT", "", WS_CHILD | WS_VISIBLE | ES_AUTOHSCROLL, 0, 0, 1, 1, hwnd, (HMENU)JX_ID_BADGE, instance, NULL);
    g_body_label = CreateWindowExA(0, "STATIC", "Body", WS_CHILD | WS_VISIBLE, 0, 0, 1, 1, hwnd, NULL, instance, NULL);
    g_body_edit = CreateWindowExA(WS_EX_CLIENTEDGE, "EDIT", "", WS_CHILD | WS_VISIBLE | ES_MULTILINE | ES_AUTOVSCROLL | WS_VSCROLL, 0, 0, 1, 1, hwnd, (HMENU)JX_ID_BODY, instance, NULL);
    g_apply_button = CreateWindowExA(0, "BUTTON", "Apply", WS_CHILD | WS_VISIBLE | BS_PUSHBUTTON, 0, 0, 1, 1, hwnd, (HMENU)JX_ID_APPLY, instance, NULL);
    g_modal_button = CreateWindowExA(0, "BUTTON", "Show Modal", WS_CHILD | WS_VISIBLE | BS_PUSHBUTTON, 0, 0, 1, 1, hwnd, (HMENU)JX_ID_MODAL, instance, NULL);
    layout_form_controls(hwnd);
    sync_form_from_state();
    sync_controls_for_modal();
}

static LRESULT CALLBACK window_proc(HWND hwnd, UINT msg, WPARAM wp, LPARAM lp) {
    switch (msg) {
        case WM_CREATE:
            create_form_controls(hwnd);
            return 0;
        case WM_COMMAND:
            if (LOWORD(wp) == JX_ID_APPLY) { apply_form_update(hwnd); return 0; }
            if (LOWORD(wp) == JX_ID_MODAL) { show_modal(JX_PAGE_MODAL_TITLE, JX_PAGE_MODAL_BODY); sync_controls_for_modal(); InvalidateRect(hwnd, NULL, TRUE); return 0; }
            break;
        case WM_LBUTTONDOWN: {
            JxModalContent modal;
            snapshot_modal(&modal);
            if (modal.visible) {
                POINT pt;
                pt.x = LOWORD(lp);
                pt.y = HIWORD(lp);
                if (PtInRect(&g_modal_close_rect, pt)) { hide_modal(); sync_controls_for_modal(); InvalidateRect(hwnd, NULL, TRUE); return 0; }
            }
            break;
        }
        case JX_WM_API_UPDATE:
            sync_form_from_state();
            InvalidateRect(hwnd, NULL, TRUE);
            return 0;
        case JX_WM_API_MODAL:
            sync_controls_for_modal();
            InvalidateRect(hwnd, NULL, TRUE);
            return 0;
        case JX_WM_API_IFRAME:
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
            layout_form_controls(hwnd);
            sync_controls_for_modal();
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
    (void)show_command;
    load_page_css();
    g_page.api_port = parse_port_argument(command_line, JX_API_PORT);

    const char *class_name = "JXPhpNativePageWindow";
    WNDCLASSA wc;
    ZeroMemory(&wc, sizeof(wc));
    wc.lpfnWndProc = window_proc;
    wc.hInstance = instance;
    wc.lpszClassName = class_name;
    wc.hbrBackground = NULL;
    wc.hCursor = LoadCursor(NULL, IDC_ARROW);
    if (!RegisterClassA(&wc)) die_last("cannot register native page window class");

    char window_title[256];
    snprintf(window_title, sizeof(window_title), "JX PHP Native Page - API Port %d", g_page.api_port);
    g_hwnd = CreateWindowExA(0, class_name, window_title,
        WS_OVERLAPPEDWINDOW | WS_VISIBLE, CW_USEDEFAULT, CW_USEDEFAULT, g_page.window_width, g_page.window_height,
        NULL, NULL, instance, NULL);
    if (!g_hwnd) die_last("cannot create native page window");
    g_api_thread = CreateThread(NULL, 0, api_thread_proc, NULL, 0, NULL);
    MSG msg;
    while (GetMessageA(&msg, NULL, 0, 0) > 0) {
        TranslateMessage(&msg);
        DispatchMessageA(&msg);
    }
    if (g_api_thread) CloseHandle(g_api_thread);
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
