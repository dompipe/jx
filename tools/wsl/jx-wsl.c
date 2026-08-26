#define _GNU_SOURCE
#include <errno.h>
#include <limits.h>
#include <stdarg.h>
#include <stdbool.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>
#include <unistd.h>

#ifndef JX_ROOT_COMPILED
#define JX_ROOT_COMPILED ""
#endif

static void die(const char *fmt, ...)
{
    va_list ap;
    va_start(ap, fmt);
    fputs("jx: ", stderr);
    vfprintf(stderr, fmt, ap);
    fputc('\n', stderr);
    va_end(ap);
    exit(1);
}

static bool is_file(const char *path)
{
    struct stat st;
    return stat(path, &st) == 0 && S_ISREG(st.st_mode);
}

static char *join2(const char *a, const char *b)
{
    size_t n = strlen(a) + strlen(b) + 2;
    char *out = calloc(n, 1);
    if (out == NULL) {
        die("out of memory");
    }
    snprintf(out, n, "%s/%s", a, b);
    return out;
}

static char *dir_name(const char *path)
{
    char *copy = strdup(path);
    if (copy == NULL) {
        die("out of memory");
    }
    char *slash = strrchr(copy, '/');
    if (slash == NULL) {
        strcpy(copy, ".");
    } else if (slash == copy) {
        slash[1] = '\0';
    } else {
        *slash = '\0';
    }
    return copy;
}

static bool valid_root(const char *root)
{
    char *jx = join2(root, "jx-run.php");
    char *xi = join2(root, "pasl/xi/xi.php");
    bool ok = is_file(jx) && is_file(xi);
    free(jx);
    free(xi);
    return ok;
}

static char *real_or_dup(const char *path)
{
    char resolved[PATH_MAX];
    if (realpath(path, resolved) != NULL) {
        return strdup(resolved);
    }
    return strdup(path);
}

static char *find_root(const char *argv0)
{
    const char *env = getenv("JX_ROOT");
    if (env != NULL && env[0] != '\0' && valid_root(env)) {
        return real_or_dup(env);
    }

    if (JX_ROOT_COMPILED[0] != '\0' && valid_root(JX_ROOT_COMPILED)) {
        return real_or_dup(JX_ROOT_COMPILED);
    }

    char exe[PATH_MAX];
    ssize_t got = readlink("/proc/self/exe", exe, sizeof(exe) - 1);
    if (got > 0) {
        exe[got] = '\0';
        char *dir = dir_name(exe);
        char *candidate = join2(dir, "..");
        if (valid_root(candidate)) {
            free(dir);
            return real_or_dup(candidate);
        }
        free(candidate);
        free(dir);
    }

    char *argv_dir = dir_name(argv0);
    char *candidate = join2(argv_dir, "..");
    if (valid_root(candidate)) {
        free(argv_dir);
        return real_or_dup(candidate);
    }
    free(candidate);
    free(argv_dir);

    die("cannot locate JX root; set JX_ROOT");
    return NULL;
}

static void exec_php(int argc, char **argv, const char *script)
{
    char **args = calloc((size_t)argc + 3, sizeof(char *));
    if (args == NULL) {
        die("out of memory");
    }
    args[0] = "php";
    args[1] = (char *)script;
    for (int i = 0; i < argc; i++) {
        args[i + 2] = argv[i];
    }
    execvp("php", args);
    die("failed to exec php: %s", strerror(errno));
}

static void usage(void)
{
    puts("jx WSL native launcher");
    puts("");
    puts("Usage:");
    puts("  jx [jx-run args...]");
    puts("  jx xi <host:port> <start|stop|status> [config.json] [--foreground]");
    puts("  jx book open [book] [host:port]");
    puts("");
    puts("Examples:");
    puts("  jx --print examples/hello.jx");
    puts("  jx xi localhost:8766 status");
    puts("  jx book open language localhost:8766");
}

int main(int argc, char **argv)
{
    char *root = find_root(argv[0]);
    char *jx_run = join2(root, "jx-run.php");
    char *xi = join2(root, "pasl/xi/xi.php");
    char *xi_config = join2(root, "pasl/xi/config.json");

    if (argc <= 1 || strcmp(argv[1], "-h") == 0 || strcmp(argv[1], "--help") == 0) {
        usage();
        free(root);
        free(jx_run);
        free(xi);
        free(xi_config);
        return 0;
    }

    if (strcmp(argv[1], "xi") == 0) {
        exec_php(argc - 2, argv + 2, xi);
    }

    if (strcmp(argv[1], "book") == 0 && argc >= 3 && strcmp(argv[2], "open") == 0) {
        const char *book = argc >= 4 ? argv[3] : "cover";
        const char *hostport = argc >= 5 ? argv[4] : "localhost:8766";
        printf("jx: opening Book %s at http://%s/?book=%s\n", book, hostport, book);
        fflush(stdout);

        char *args[] = {
            (char *)hostport,
            "start",
            xi_config,
            "--foreground",
            NULL
        };
        exec_php(4, args, xi);
    }

    exec_php(argc - 1, argv + 1, jx_run);
}
