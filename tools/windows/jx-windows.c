#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#include <direct.h>
#include <errno.h>
#include <process.h>
#include <stdbool.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#ifndef JX_ROOT_COMPILED
#define JX_ROOT_COMPILED ""
#endif

static void die(const char *message)
{
    fprintf(stderr, "jx.exe: %s\n", message);
    exit(1);
}

static bool is_file(const char *path)
{
    DWORD attrs = GetFileAttributesA(path);
    return attrs != INVALID_FILE_ATTRIBUTES && (attrs & FILE_ATTRIBUTE_DIRECTORY) == 0;
}

static char *dupstr(const char *value)
{
    char *out = _strdup(value);
    if (out == NULL) die("out of memory");
    return out;
}

static char *join2(const char *a, const char *b)
{
    size_t n = strlen(a) + strlen(b) + 2;
    char *out = (char *)calloc(n, 1);
    if (out == NULL) die("out of memory");
    snprintf(out, n, "%s\\%s", a, b);
    return out;
}

static char *dir_name(const char *path)
{
    char *copy = dupstr(path);
    char *slash = strrchr(copy, '\\');
    char *alt = strrchr(copy, '/');
    if (alt != NULL && (slash == NULL || alt > slash)) slash = alt;
    if (slash == NULL) strcpy(copy, ".");
    else if (slash == copy) slash[1] = '\0';
    else *slash = '\0';
    return copy;
}

static char *real_or_dup(const char *path)
{
    char full[MAX_PATH];
    if (_fullpath(full, path, MAX_PATH) != NULL) return dupstr(full);
    return dupstr(path);
}

static bool valid_root(const char *root)
{
    char *jx = join2(root, "jx-run.php");
    char *xi = join2(root, "pasl\\xi\\xi.php");
    bool ok = is_file(jx) && is_file(xi);
    free(jx);
    free(xi);
    return ok;
}

static char *find_root(const char *argv0)
{
    char env[32767];
    DWORD env_len = GetEnvironmentVariableA("JX_ROOT", env, sizeof(env));
    if (env_len > 0 && env_len < sizeof(env) && valid_root(env)) return real_or_dup(env);
    if (JX_ROOT_COMPILED[0] != '\0' && valid_root(JX_ROOT_COMPILED)) return real_or_dup(JX_ROOT_COMPILED);

    char exe[MAX_PATH];
    DWORD got = GetModuleFileNameA(NULL, exe, sizeof(exe));
    if (got > 0 && got < sizeof(exe)) {
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
    char **args = (char **)calloc((size_t)argc + 4, sizeof(char *));
    if (args == NULL) die("out of memory");

    args[0] = "php";
    args[1] = (char *)script;
    for (int i = 0; i < argc; i++) args[i + 2] = argv[i];
    args[argc + 2] = NULL;

    _execvp("php", (const char * const *)args);
    fprintf(stderr, "jx.exe: failed to exec php: %s\n", strerror(errno));
    exit(1);
}

static void usage(void)
{
    puts("jx.exe - JX compiler / runtime");
    puts("");
    puts("Compile / run:");
    puts("  jx.exe [-O0|-O1] [-o out.pbc] [--report[=compact|verbose|json]|--quiet] file.jx");
    puts("  jx.exe --print file.jx");
    puts("  jx.exe -c \"$a = 1; $result = $a * 2;\"");
    puts("");
    puts("Hosts:");
    puts("  jx.exe window-server <start|stop|status|open> [...]");
    puts("  jx.exe xi <host:port> <start|stop|status> [config.json] [--foreground]");
    puts("  jx.exe book open [book] [host:port]");
    puts("");
    puts("Bytecode page output:");
    puts("  jx.exe PAGE 001  OK  42B  O1  deps:0  regs:0  iter:0  target:PASM");
    puts("");
    puts("Examples:");
    puts("  jx.exe -O1 -o app.pbc --report=verbose app.jx");
    puts("  jx.exe --print examples\\hello.jx");
    puts("  jx.exe window-server status localhost:8766");
    puts("  jx.exe xi localhost:8766 status");
    puts("  jx.exe book open language localhost:8766");
}

int main(int argc, char **argv)
{
    char *root = find_root(argv[0]);
    char *jx_run = join2(root, "jx-run.php");
    char *window_server = join2(root, "jx-window-server.php");
    char *xi = join2(root, "pasl\\xi\\xi.php");

    if (argc <= 1 || strcmp(argv[1], "-h") == 0 || strcmp(argv[1], "--help") == 0) {
        usage();
        free(root); free(jx_run); free(window_server); free(xi);
        return 0;
    }

    if (strcmp(argv[1], "window-server") == 0 || strcmp(argv[1], "windows") == 0) {
        exec_php(argc - 2, argv + 2, window_server);
    }

    if (strcmp(argv[1], "xi") == 0) {
        exec_php(argc - 2, argv + 2, xi);
    }

    if (strcmp(argv[1], "book") == 0 && argc >= 3 && strcmp(argv[2], "open") == 0) {
        const char *book = argc >= 4 ? argv[3] : "cover";
        const char *hostport = argc >= 5 ? argv[4] : "localhost:8766";
        printf("jx.exe: opening Book %s at http://%s/?book=%s\n", book, hostport, book);
        fflush(stdout);
        char *args[] = {"open", (char *)book, (char *)hostport, "--native", NULL};
        exec_php(4, args, window_server);
    }

    exec_php(argc - 1, argv + 1, jx_run);
}
