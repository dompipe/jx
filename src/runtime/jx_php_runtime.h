#ifndef JX_PHP_RUNTIME_H
#define JX_PHP_RUNTIME_H

#include <stddef.h>
#include <stdint.h>

#ifdef __cplusplus
extern "C" {
#endif

typedef enum {
    JX_TYPE_NULL = 0,
    JX_TYPE_BOOL,
    JX_TYPE_INT,
    JX_TYPE_FLOAT,
    JX_TYPE_STRING,
    JX_TYPE_ARRAY,
    JX_TYPE_OBJECT,
    JX_TYPE_RESOURCE
} JxType;

typedef struct {
    const char *bytes;
    size_t length;
} JxStringView;

typedef struct JxValue JxValue;

typedef int (*JxPhpBuiltinFn)(int argc, const JxValue *argv, JxValue *result);

typedef struct {
    const char *php_name;
    const char *c_name;
    JxPhpBuiltinFn fn;
    int min_args;
    int max_args;
    const char *module;
    const char *needed_for;
} JxPhpBuiltinSpec;

struct JxValue {
    JxType type;
    union {
        int bool_value;
        int64_t int_value;
        double float_value;
        JxStringView string_value;
        void *ptr_value;
    } as;
};

JxValue jx_null(void);
JxValue jx_bool(int value);
JxValue jx_int(int64_t value);
JxValue jx_float(double value);
JxValue jx_string_view(const char *bytes, size_t length);

const JxPhpBuiltinSpec *jx_php_builtin_lookup(const char *name);
int jx_php_call_builtin(const char *name, int argc, const JxValue *argv, JxValue *result);

int jx_php_strlen(int argc, const JxValue *argv, JxValue *result);
int jx_php_count(int argc, const JxValue *argv, JxValue *result);
int jx_php_intval(int argc, const JxValue *argv, JxValue *result);
int jx_php_strval(int argc, const JxValue *argv, JxValue *result);
int jx_php_print(int argc, const JxValue *argv, JxValue *result);

#ifdef __cplusplus
}
#endif

#endif
