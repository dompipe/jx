#include "../common/jx-native-image.h"

#include <fcntl.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/mman.h>
#include <sys/stat.h>
#include <unistd.h>

/*
 * Minimal native JXL launcher for the current no-import/no-relocation profile.
 * The JXNI file is mapped read-only, CODE is copied once RW -> RX, then the
 * CODE-relative entrypoint is called as `int64_t entry(void)`.
 */
int main(int argc, char **argv) {
    int fd = -1;
    struct stat st;
    void *file_map = MAP_FAILED;
    void *code_map = MAP_FAILED;
    jx_native_image_view image;
    jx_native_section_view code;
    jx_native_section_view unsupported;
    int64_t (*entry)(void);
    int64_t result;
    int rc = 1;

    if (argc != 2) {
        fprintf(stderr, "usage: %s program.jxl\n", argv[0]);
        return 2;
    }

    fd = open(argv[1], O_RDONLY);
    if (fd < 0) { perror("open"); goto done; }
    if (fstat(fd, &st) != 0 || st.st_size <= 0) { fprintf(stderr, "invalid JXL file size\n"); goto done; }

    file_map = mmap(NULL, (size_t)st.st_size, PROT_READ, MAP_PRIVATE, fd, 0);
    if (file_map == MAP_FAILED) { perror("mmap"); goto done; }

    if (jx_native_image_open(file_map, (size_t)st.st_size, &image) != 0) {
        fprintf(stderr, "invalid JXL native image\n"); goto done;
    }
    if ((image.flags & JX_NATIVE_FLAG_EXECUTABLE) == 0 || !image.has_entrypoint) {
        fprintf(stderr, "image has no executable entrypoint\n"); goto done;
    }
    if (image.architecture != JX_NATIVE_ARCH_X86_64_SYSV) {
        fprintf(stderr, "unsupported JXL architecture %u\n", image.architecture); goto done;
    }
    if (jx_native_image_section(&image, "IMPORTS", &unsupported) == 0 && unsupported.size != 0) {
        fprintf(stderr, "imports are not admitted by this launcher yet\n"); goto done;
    }
    if (jx_native_image_section(&image, "RELOCATIONS", &unsupported) == 0 && unsupported.size != 0) {
        fprintf(stderr, "relocations are not admitted by this launcher yet\n"); goto done;
    }
    if (jx_native_image_section(&image, "CODE", &code) != 0 || code.size == 0 || image.entrypoint >= code.size) {
        fprintf(stderr, "invalid CODE/entrypoint\n"); goto done;
    }

    code_map = mmap(NULL, code.size, PROT_READ | PROT_WRITE, MAP_PRIVATE | MAP_ANONYMOUS, -1, 0);
    if (code_map == MAP_FAILED) { perror("mmap CODE"); goto done; }
    memcpy(code_map, code.data, code.size);
    if (mprotect(code_map, code.size, PROT_READ | PROT_EXEC) != 0) { perror("mprotect"); goto done; }

    entry = (int64_t (*)(void))((uint8_t *)code_map + (size_t)image.entrypoint);
    result = entry();
    printf("%lld\n", (long long)result);
    rc = 0;

done:
    if (code_map != MAP_FAILED) munmap(code_map, code.size);
    if (file_map != MAP_FAILED) munmap(file_map, (size_t)st.st_size);
    if (fd >= 0) close(fd);
    return rc;
}
