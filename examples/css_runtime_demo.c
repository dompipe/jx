#include <stdio.h>
#include <stdlib.h>

#include "../src/runtime/jx_css_runtime.h"

static unsigned char *read_file(const char *path, size_t *len) {
    FILE *fp = fopen(path, "rb");
    if (!fp) {
        fprintf(stderr, "css-runtime-demo: cannot open %s\n", path);
        return NULL;
    }

    if (fseek(fp, 0, SEEK_END) != 0) {
        fclose(fp);
        fprintf(stderr, "css-runtime-demo: cannot seek %s\n", path);
        return NULL;
    }

    long size = ftell(fp);
    if (size < 0) {
        fclose(fp);
        fprintf(stderr, "css-runtime-demo: cannot measure %s\n", path);
        return NULL;
    }

    if (fseek(fp, 0, SEEK_SET) != 0) {
        fclose(fp);
        fprintf(stderr, "css-runtime-demo: cannot rewind %s\n", path);
        return NULL;
    }

    unsigned char *bytes = NULL;
    if (size > 0) {
        bytes = (unsigned char *)malloc((size_t)size + 1);
        if (!bytes) {
            fclose(fp);
            fprintf(stderr, "css-runtime-demo: out of memory\n");
            return NULL;
        }

        if (fread(bytes, 1, (size_t)size, fp) != (size_t)size) {
            free(bytes);
            fclose(fp);
            fprintf(stderr, "css-runtime-demo: cannot read %s\n", path);
            return NULL;
        }
        bytes[size] = 0;
    }

    fclose(fp);
    *len = (size_t)size;
    return bytes;
}

static void print_slice(const char *data, size_t length) {
    printf("%.*s", (int)length, data);
}

static void print_property(const JxCssStylesheet *sheet, const char *selector, const char *property) {
    const JxCssDeclaration *decl = jx_css_find_property(sheet, selector, property);
    if (!decl) {
        printf("%s { %s: <missing>; }\n", selector, property);
        return;
    }

    print_slice(decl->selector, decl->selector_length);
    printf(" { ");
    print_slice(decl->property, decl->property_length);
    printf(": ");
    print_slice(decl->value, decl->value_length);
    printf("; }\n");
}

int main(int argc, char **argv) {
    const char *path = argc > 1 ? argv[1] : "examples/style.css";
    size_t len = 0;
    unsigned char *bytes = read_file(path, &len);
    if (!bytes && len != 0) {
        return 1;
    }

    JxCssText css;
    css.data = (const char *)bytes;
    css.length = len;

    JxCssStylesheet sheet;
    jx_css_stylesheet_init(&sheet);

    int rc = jx_css_parse_text(css, &sheet);
    if (rc != 0) {
        fprintf(stderr, "css-runtime-demo: parse failed for %s\n", path);
        free(bytes);
        jx_css_stylesheet_free(&sheet);
        return 1;
    }

    printf("CSS runtime demo\n");
    printf("source: %s\n", path);
    printf("declarations: %zu\n", sheet.count);

    print_property(&sheet, "body", "font-family");
    print_property(&sheet, "body", "background");
    print_property(&sheet, ".card", "padding");
    print_property(&sheet, ".card", "border-radius");

    jx_css_stylesheet_free(&sheet);
    free(bytes);
    return 0;
}
