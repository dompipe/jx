#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "../runtime/jx_css_runtime.h"

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
} JxPageDemo;

static JxPageDemo g_page;

static void die_last(const char *message) {
    DWORD code = GetLastError();
    fprintf(stderr, "jx-page-win32: %s: error %lu\n", message, (unsigned long)code);
    ExitProcess(1);
}

static char *read_file(const char *path, size_t *len) {
    FILE *fp = fopen(path, "rb");
    if (!fp) {
        fprintf(stderr, "jx-page-win32: cannot open %s\n", path);
        return NULL;
    }

    if (fseek(fp, 0, SEEK_END) != 0) {
        fclose(fp);
        return NULL;
    }

    long size = ftell(fp);
    if (size < 0) {
        fclose(fp);
        return NULL;
    }

    if (fseek(fp, 0, SEEK_SET) != 0) {
        fclose(fp);
        return NULL;
    }

    char *bytes = (char *)malloc((size_t)size + 1);
    if (!bytes) {
        fclose(fp);
        return NULL;
    }

    if (size > 0 && fread(bytes, 1, (size_t)size, fp) != (size_t)size) {
        free(bytes);
        fclose(fp);
        return NULL;
    }

    fclose(fp);
    bytes[size] = '\0';
    *len = (size_t)size;
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
    if (!value || value[0] != '#') {
        return fallback;
    }

    if (strlen(value) >= 7) {
        int r1 = hex_value(value[1]);
        int r2 = hex_value(value[2]);
        int g1 = hex_value(value[3]);
        int g2 = hex_value(value[4]);
        int b1 = hex_value(value[5]);
        int b2 = hex_value(value[6]);
        if (r1 >= 0 && r2 >= 0 && g1 >= 0 && g2 >= 0 && b1 >= 0 && b2 >= 0) {
            return RGB((r1 << 4) | r2, (g1 << 4) | g2, (b1 << 4) | b2);
        }
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

static void load_page_css(const char *css_path) {
    memset(&g_page, 0, sizeof(g_page));
    jx_css_stylesheet_init(&g_page.css);

    g_page.css_bytes = read_file(css_path, &g_page.css_len);
    if (g_page.css_bytes) {
        JxCssText css;
        css.data = g_page.css_bytes;
        css.length = g_page.css_len;
        if (!jx_css_parse_text(css, &g_page.css)) {
            fprintf(stderr, "jx-page-win32: warning: could not parse %s\n", css_path);
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

static void paint_page(HWND hwnd, HDC hdc) {
    RECT client;
    GetClientRect(hwnd, &client);

    HBRUSH bg = CreateSolidBrush(g_page.body_bg);
    FillRect(hdc, &client, bg);
    DeleteObject(bg);

    int pad = g_page.body_padding;
    RECT page = client;
    page.left += pad;
    page.top += pad;
    page.right -= pad;
    page.bottom -= pad;

    RECT hero = page;
    hero.bottom = hero.top + 84;
    draw_text_block(hdc, "JX Native Page Demo", &hero, 32, FW_BOLD, g_page.body_fg);

    RECT intro = page;
    intro.top += 52;
    intro.bottom = intro.top + 68;
    draw_text_block(
        hdc,
        "This is drawn as native Win32 GDI boxes and text. No WebView. No browser engine. CSS is parsed by JX and applied to a small page layout.",
        &intro,
        18,
        FW_NORMAL,
        g_page.body_fg
    );

    RECT card = page;
    card.top += 142;
    card.bottom = card.top + 240;
    fill_round_rect(hdc, card, g_page.card_radius, RGB(24, 30, 40), g_page.card_border);

    RECT inner = card;
    inner.left += g_page.card_padding;
    inner.top += g_page.card_padding;
    inner.right -= g_page.card_padding;
    inner.bottom -= g_page.card_padding;

    RECT badge = inner;
    badge.bottom = badge.top + 26;
    draw_text_block(hdc, "CSS RUNTIME", &badge, 16, FW_BOLD, RGB(145, 220, 255));

    RECT line1 = inner;
    line1.top += 44;
    line1.bottom = line1.top + 34;
    draw_text_block(hdc, "body background, body color, card padding, border, and border-radius are read from examples/style.css.", &line1, 18, FW_NORMAL, g_page.body_fg);

    RECT line2 = inner;
    line2.top += 92;
    line2.bottom = line2.top + 70;
    char summary[256];
    snprintf(summary, sizeof(summary), "Declarations parsed: %zu\nNative layout: block page + card + text\nRenderer: Win32 GDI", g_page.css.count);
    draw_text_block(hdc, summary, &line2, 17, FW_NORMAL, RGB(210, 220, 232));
}

static LRESULT CALLBACK window_proc(HWND hwnd, UINT msg, WPARAM wp, LPARAM lp) {
    switch (msg) {
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
}

int main(int argc, char **argv) {
    const char *css_path = argc > 1 ? argv[1] : "examples/style.css";
    load_page_css(css_path);

    HINSTANCE instance = GetModuleHandleA(NULL);
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
        "JX Native Page Demo - No WebView",
        WS_OVERLAPPEDWINDOW | WS_VISIBLE,
        CW_USEDEFAULT,
        CW_USEDEFAULT,
        980,
        680,
        NULL,
        NULL,
        instance,
        NULL
    );

    if (!hwnd) {
        die_last("cannot create native page window");
    }

    MSG msg;
    while (GetMessageA(&msg, NULL, 0, 0) > 0) {
        TranslateMessage(&msg);
        DispatchMessageA(&msg);
    }

    jx_css_stylesheet_free(&g_page.css);
    free(g_page.css_bytes);
    return 0;
}
