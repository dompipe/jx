#include "../host/linux/jx11-patch-module.h"
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

static uint32_t seen_background = 0u;
static int invalidations = 0;
static int logs = 0;

static void host_log(int level, const char *message) {
    if (level == 1 && message && *message) ++logs;
}
static void host_set_background(uint32_t rgb) { seen_background = rgb; }
static void host_invalidate(void) { ++invalidations; }
static uint64_t host_generation(void) { return 7u; }

static const jx11_patch_host_v1 host = {
    JX11_PATCH_MODULE_ABI_VERSION,
    sizeof(jx11_patch_host_v1),
    host_log,
    host_set_background,
    host_invalidate,
    host_generation
};

static uint8_t *read_file(const char *path, size_t *length) {
    FILE *fp = fopen(path, "rb");
    if (!fp) return NULL;
    if (fseek(fp, 0, SEEK_END) != 0) { fclose(fp); return NULL; }
    long n = ftell(fp);
    if (n <= 0 || fseek(fp, 0, SEEK_SET) != 0) { fclose(fp); return NULL; }
    uint8_t *bytes = malloc((size_t)n);
    if (!bytes) { fclose(fp); return NULL; }
    if (fread(bytes, 1u, (size_t)n, fp) != (size_t)n) { free(bytes); fclose(fp); return NULL; }
    fclose(fp);
    *length = (size_t)n;
    return bytes;
}

int main(int argc, char **argv) {
    if (argc != 2) return 2;
    size_t length = 0u;
    uint8_t *bytes = read_file(argv[1], &length);
    if (!bytes) return 3;
    jx11_loaded_patch_module loaded;
    int rc = jx11_patch_module_load(bytes, length, &loaded);
    free(bytes);
    if (rc != 0 || !loaded.module) return 4;
    if (strcmp(loaded.module->name, "sample-live-module") != 0) return 5;
    loaded.module->activate(&host);
    if (seen_background != 0x123456u || logs != 1) return 6;
    loaded.module->safe_point(&host);
    if (invalidations != 1) return 7;
    if (!loaded.module->filter_x_event(&host, 2u, NULL)) return 8;
    if (loaded.module->filter_x_event(&host, 99u, NULL)) return 9;
    loaded.module->deactivate(&host);
    if (logs != 2) return 10;
    jx11_patch_module_unload(&loaded);
    if (loaded.handle || loaded.module) return 11;
    puts("jx11-patch-module: executable memfd load/activate/filter/rollback-hooks ok");
    return 0;
}
