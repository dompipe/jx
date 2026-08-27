#include <stdint.h>
#include <stdio.h>
#include <string.h>
#include "../host/common/jx64-probe.h"

int main(int argc, char **argv) {
    if (argc != 2) {
        fputs("usage: jx64-probe <package>\n", stderr);
        return 2;
    }

    jx64_identity id;
    memset(&id, 0, sizeof id);
    int rc = jx64_probe_file(argv[1], &id);
    if (rc != 1) {
        fprintf(stderr, "jx64-probe: package not recognized rc=%d\n", rc);
        return 1;
    }
    if (id.major != 1u || id.minor != 0u || id.sections != 3u) {
        fprintf(stderr, "jx64-probe: identity mismatch %u.%u sections=%u\n",
                (unsigned)id.major, (unsigned)id.minor, (unsigned)id.sections);
        return 3;
    }

    int all_zero = 1;
    for (unsigned i = 0; i < 32u; ++i) if (id.manifest_sha256[i] != 0u) all_zero = 0;
    if (all_zero) {
        fputs("jx64-probe: manifest digest missing\n", stderr);
        return 4;
    }

    printf("jx64-probe: ok JX64 %u.%u sections=%u\n",
           (unsigned)id.major, (unsigned)id.minor, (unsigned)id.sections);
    return 0;
}
