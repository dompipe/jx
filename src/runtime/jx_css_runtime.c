#include "jx_css_runtime.h"

#include <ctype.h>
#include <stdlib.h>
#include <string.h>

static const char *jx_css_skip_ws(const char *p, const char *end) {
    while (p < end) {
        if (isspace((unsigned char)*p)) {
            ++p;
            continue;
        }
        if (p + 1 < end && p[0] == '/' && p[1] == '*') {
            p += 2;
            while (p + 1 < end && !(p[0] == '*' && p[1] == '/')) {
                ++p;
            }
            if (p + 1 < end) {
                p += 2;
            }
            continue;
        }
        break;
    }
    return p;
}

static const char *jx_css_trim_end(const char *start, const char *end) {
    while (end > start && isspace((unsigned char)end[-1])) {
        --end;
    }
    return end;
}

static int jx_css_push_declaration(
    JxCssStylesheet *sheet,
    const char *selector,
    size_t selector_length,
    const char *property,
    size_t property_length,
    const char *value,
    size_t value_length
) {
    if (sheet->count == sheet->capacity) {
        size_t next_capacity = sheet->capacity ? sheet->capacity * 2 : 16;
        JxCssDeclaration *next = (JxCssDeclaration *)realloc(
            sheet->items,
            next_capacity * sizeof(JxCssDeclaration)
        );
        if (!next) {
            return 0;
        }
        sheet->items = next;
        sheet->capacity = next_capacity;
    }

    JxCssDeclaration *decl = &sheet->items[sheet->count++];
    decl->selector = selector;
    decl->selector_length = selector_length;
    decl->property = property;
    decl->property_length = property_length;
    decl->value = value;
    decl->value_length = value_length;
    return 1;
}

void jx_css_stylesheet_init(JxCssStylesheet *sheet) {
    sheet->items = NULL;
    sheet->count = 0;
    sheet->capacity = 0;
}

void jx_css_stylesheet_free(JxCssStylesheet *sheet) {
    free(sheet->items);
    sheet->items = NULL;
    sheet->count = 0;
    sheet->capacity = 0;
}

int jx_css_parse_text(JxCssText css, JxCssStylesheet *sheet) {
    const char *p = css.data;
    const char *end = css.data + css.length;

    while ((p = jx_css_skip_ws(p, end)) < end) {
        const char *selector_start = p;
        while (p < end && *p != '{') {
            ++p;
        }
        if (p >= end) {
            break;
        }

        const char *selector_end = jx_css_trim_end(selector_start, p);
        ++p;

        while ((p = jx_css_skip_ws(p, end)) < end && *p != '}') {
            const char *property_start = p;
            while (p < end && *p != ':' && *p != '}' && *p != ';') {
                ++p;
            }
            const char *property_end = jx_css_trim_end(property_start, p);
            if (p >= end || *p != ':') {
                while (p < end && *p != ';' && *p != '}') {
                    ++p;
                }
                if (p < end && *p == ';') {
                    ++p;
                }
                continue;
            }
            ++p;

            p = jx_css_skip_ws(p, end);
            const char *value_start = p;
            while (p < end && *p != ';' && *p != '}') {
                ++p;
            }
            const char *value_end = jx_css_trim_end(value_start, p);

            if (selector_end > selector_start && property_end > property_start) {
                if (!jx_css_push_declaration(
                    sheet,
                    selector_start,
                    (size_t)(selector_end - selector_start),
                    property_start,
                    (size_t)(property_end - property_start),
                    value_start,
                    (size_t)(value_end - value_start)
                )) {
                    return 0;
                }
            }

            if (p < end && *p == ';') {
                ++p;
            }
        }

        if (p < end && *p == '}') {
            ++p;
        }
    }

    return 1;
}

static int jx_css_slice_equals(const char *slice, size_t slice_len, const char *text) {
    size_t text_len = strlen(text);
    return slice_len == text_len && memcmp(slice, text, text_len) == 0;
}

const JxCssDeclaration *jx_css_find_property(
    const JxCssStylesheet *sheet,
    const char *selector,
    const char *property
) {
    for (size_t i = 0; i < sheet->count; ++i) {
        const JxCssDeclaration *decl = &sheet->items[i];
        if (jx_css_slice_equals(decl->selector, decl->selector_length, selector) &&
            jx_css_slice_equals(decl->property, decl->property_length, property)) {
            return decl;
        }
    }
    return NULL;
}
