#define _GNU_SOURCE
#include <errno.h>
#include <inttypes.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/mman.h>
#include <time.h>
#include <unistd.h>

typedef int64_t (*jxl_entry_fn)(void);

typedef struct Buffer {
    uint8_t *data;
    size_t size;
} Buffer;

static Buffer read_file(const char *path)
{
    Buffer out = {0};
    FILE *fp = fopen(path, "rb");
    if (fp == NULL) {
        fprintf(stderr, "cannot open %s: %s\n", path, strerror(errno));
        return out;
    }
    if (fseek(fp, 0, SEEK_END) != 0) {
        fclose(fp);
        return out;
    }
    long end = ftell(fp);
    if (end <= 0 || fseek(fp, 0, SEEK_SET) != 0) {
        fclose(fp);
        return out;
    }
    out.size = (size_t)end;
    out.data = (uint8_t *)malloc(out.size);
    if (out.data == NULL) {
        fclose(fp);
        out.size = 0;
        return out;
    }
    if (fread(out.data, 1, out.size, fp) != out.size) {
        free(out.data);
        out.data = NULL;
        out.size = 0;
    }
    fclose(fp);
    return out;
}

static uint64_t now_ns(void)
{
    struct timespec ts;
    clock_gettime(CLOCK_MONOTONIC_RAW, &ts);
    return (uint64_t)ts.tv_sec * UINT64_C(1000000000) + (uint64_t)ts.tv_nsec;
}

int main(int argc, char **argv)
{
    if (argc < 3 || argc > 4) {
        fprintf(stderr, "usage: %s program.jxl expected [iterations]\n", argv[0]);
        return 2;
    }

    Buffer code = read_file(argv[1]);
    if (code.data == NULL || code.size == 0) return 2;

    char *end = NULL;
    errno = 0;
    int64_t expected = strtoll(argv[2], &end, 0);
    if (errno != 0 || end == argv[2] || *end != '\0') {
        fprintf(stderr, "invalid expected result: %s\n", argv[2]);
        free(code.data);
        return 2;
    }

    uint64_t iterations = 1;
    if (argc == 4) {
        errno = 0;
        unsigned long long parsed = strtoull(argv[3], &end, 10);
        if (errno != 0 || end == argv[3] || *end != '\0' || parsed == 0) {
            fprintf(stderr, "invalid iterations: %s\n", argv[3]);
            free(code.data);
            return 2;
        }
        iterations = (uint64_t)parsed;
    }

    long page_size = sysconf(_SC_PAGESIZE);
    if (page_size <= 0) page_size = 4096;
    size_t alloc = (code.size + (size_t)page_size - 1u) & ~((size_t)page_size - 1u);

    void *mem = mmap(NULL, alloc, PROT_READ | PROT_WRITE, MAP_PRIVATE | MAP_ANONYMOUS, -1, 0);
    if (mem == MAP_FAILED) {
        fprintf(stderr, "mmap failed: %s\n", strerror(errno));
        free(code.data);
        return 2;
    }
    memcpy(mem, code.data, code.size);
    free(code.data);

    if (mprotect(mem, alloc, PROT_READ | PROT_EXEC) != 0) {
        fprintf(stderr, "mprotect RX failed: %s\n", strerror(errno));
        munmap(mem, alloc);
        return 2;
    }
    __builtin___clear_cache((char *)mem, (char *)mem + code.size);

    jxl_entry_fn entry = (jxl_entry_fn)mem;
    int64_t first = entry();
    if (first != expected) {
        fprintf(stderr, "native JXL mismatch: expected=%" PRId64 " actual=%" PRId64 "\n", expected, first);
        munmap(mem, alloc);
        return 1;
    }

    uint64_t start = now_ns();
    int64_t last = first;
    for (uint64_t i = 0; i < iterations; i++) last = entry();
    uint64_t elapsed = now_ns() - start;

    if (last != expected) {
        fprintf(stderr, "native JXL changed result during benchmark: expected=%" PRId64 " actual=%" PRId64 "\n", expected, last);
        munmap(mem, alloc);
        return 1;
    }

    double ns_per_call = (double)elapsed / (double)iterations;
    printf(
        "native JXL: ok (%zu code bytes, result=%" PRId64 ", iterations=%" PRIu64 ", %.2f ns/call)\n",
        code.size,
        last,
        iterations,
        ns_per_call
    );

    munmap(mem, alloc);
    return 0;
}
