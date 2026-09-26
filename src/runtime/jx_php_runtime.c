#include "jx_php_runtime.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>

JxValue jx_null(void) {
    JxValue value;
    value.type = JX_TYPE_NULL;
    value.as.ptr_value = NULL;
    return value;
}

JxValue jx_bool(int input) {
    JxValue value;
    value.type = JX_TYPE_BOOL;
    value.as.bool_value = input ? 1 : 0;
    return value;
}

JxValue jx_int(int64_t input) {
    JxValue value;
    value.type = JX_TYPE_INT;
    value.as.int_value = input;
    return value;
}

JxValue jx_float(double input) {
    JxValue value;
    value.type = JX_TYPE_FLOAT;
    value.as.float_value = input;
    return value;
}

JxValue jx_string_view(const char *bytes, size_t length) {
    JxValue value;
    value.type = JX_TYPE_STRING;
    value.as.string_value.bytes = bytes;
    value.as.string_value.length = length;
    return value;
}

static int jx_value_truthy(const JxValue *value) {
    switch (value->type) {
        case JX_TYPE_NULL: return 0;
        case JX_TYPE_BOOL: return value->as.bool_value != 0;
        case JX_TYPE_INT: return value->as.int_value != 0;
        case JX_TYPE_FLOAT: return value->as.float_value != 0.0;
        case JX_TYPE_STRING: return value->as.string_value.length != 0;
        default: return value->as.ptr_value != NULL;
    }
}

static int64_t jx_value_to_int(const JxValue *value) {
    switch (value->type) {
        case JX_TYPE_NULL: return 0;
        case JX_TYPE_BOOL: return value->as.bool_value ? 1 : 0;
        case JX_TYPE_INT: return value->as.int_value;
        case JX_TYPE_FLOAT: return (int64_t)value->as.float_value;
        case JX_TYPE_STRING: return value->as.string_value.bytes ? strtoll(value->as.string_value.bytes, NULL, 10) : 0;
        default: return value->as.ptr_value ? 1 : 0;
    }
}

static JxStringView jx_value_to_string_view(const JxValue *value, char *scratch, size_t scratch_size) {
    JxStringView view;
    view.bytes = "";
    view.length = 0;

    switch (value->type) {
        case JX_TYPE_NULL:
            return view;
        case JX_TYPE_BOOL:
            if (value->as.bool_value) {
                view.bytes = "1";
                view.length = 1;
            }
            return view;
        case JX_TYPE_INT:
            snprintf(scratch, scratch_size, "%lld", (long long)value->as.int_value);
            view.bytes = scratch;
            view.length = strlen(scratch);
            return view;
        case JX_TYPE_FLOAT:
            snprintf(scratch, scratch_size, "%g", value->as.float_value);
            view.bytes = scratch;
            view.length = strlen(scratch);
            return view;
        case JX_TYPE_STRING:
            return value->as.string_value;
        default:
            view.bytes = "Object";
            view.length = 6;
            return view;
    }
}

int jx_php_strlen(int argc, const JxValue *argv, JxValue *result) {
    if (argc < 1 || argv[0].type != JX_TYPE_STRING) {
        return -1;
    }

    *result = jx_int((int64_t)argv[0].as.string_value.length);
    return 0;
}

int jx_php_count(int argc, const JxValue *argv, JxValue *result) {
    if (argc < 1) {
        return -1;
    }

    if (argv[0].type == JX_TYPE_NULL) {
        *result = jx_int(0);
        return 0;
    }

    if (argv[0].type == JX_TYPE_ARRAY) {
        /* Array payload is not lowered yet. This slot exists for the real array runtime. */
        return -2;
    }

    *result = jx_int(1);
    return 0;
}

int jx_php_intval(int argc, const JxValue *argv, JxValue *result) {
    if (argc < 1) {
        return -1;
    }

    *result = jx_int(jx_value_to_int(&argv[0]));
    return 0;
}

int jx_php_strval(int argc, const JxValue *argv, JxValue *result) {
    static char scratch[128];

    if (argc < 1) {
        return -1;
    }

    JxStringView view = jx_value_to_string_view(&argv[0], scratch, sizeof(scratch));
    *result = jx_string_view(view.bytes, view.length);
    return 0;
}

int jx_php_print(int argc, const JxValue *argv, JxValue *result) {
    char scratch[128];

    if (argc < 1) {
        return -1;
    }

    JxStringView view = jx_value_to_string_view(&argv[0], scratch, sizeof(scratch));
    if (view.length > 0) {
        fwrite(view.bytes, 1, view.length, stdout);
    }

    *result = jx_int(1);
    return 0;
}

static const JxPhpBuiltinSpec JX_PHP_BUILTINS[] = {
    { "strlen", "jx_php_strlen", jx_php_strlen, 1, 1, "jx_string", "string length and truth checks" },
    { "count", "jx_php_count", jx_php_count, 1, 2, "jx_array", "array and Countable size" },
    { "sizeof", "jx_php_count", jx_php_count, 1, 2, "jx_array", "PHP alias for count" },
    { "intval", "jx_php_intval", jx_php_intval, 1, 2, "jx_value", "scalar cast to int" },
    { "strval", "jx_php_strval", jx_php_strval, 1, 1, "jx_value", "scalar cast to string" },
    { "print", "jx_php_print", jx_php_print, 1, 1, "jx_output", "print expression output" },
    { NULL, NULL, NULL, 0, 0, NULL, NULL }
};

const JxPhpBuiltinSpec *jx_php_builtin_lookup(const char *name) {
    for (size_t i = 0; JX_PHP_BUILTINS[i].php_name; ++i) {
        if (strcmp(JX_PHP_BUILTINS[i].php_name, name) == 0) {
            return &JX_PHP_BUILTINS[i];
        }
    }

    return NULL;
}

int jx_php_call_builtin(const char *name, int argc, const JxValue *argv, JxValue *result) {
    const JxPhpBuiltinSpec *spec = jx_php_builtin_lookup(name);
    if (!spec) {
        return -404;
    }

    if (argc < spec->min_args || (spec->max_args >= 0 && argc > spec->max_args)) {
        return -1;
    }

    return spec->fn(argc, argv, result);
}
