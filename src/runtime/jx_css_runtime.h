#ifndef JX_CSS_RUNTIME_H
#define JX_CSS_RUNTIME_H

#include <stddef.h>

#ifdef __cplusplus
extern "C" {
#endif

typedef struct {
    const char *data;
    size_t length;
} JxCssText;

typedef struct {
    const char *selector;
    size_t selector_length;
    const char *property;
    size_t property_length;
    const char *value;
    size_t value_length;
} JxCssDeclaration;

typedef struct {
    JxCssDeclaration *items;
    size_t count;
    size_t capacity;
} JxCssStylesheet;

void jx_css_stylesheet_init(JxCssStylesheet *sheet);
void jx_css_stylesheet_free(JxCssStylesheet *sheet);
int jx_css_parse_text(JxCssText css, JxCssStylesheet *sheet);
const JxCssDeclaration *jx_css_find_property(
    const JxCssStylesheet *sheet,
    const char *selector,
    const char *property
);

#ifdef __cplusplus
}
#endif

#endif
