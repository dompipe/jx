#include <assert.h>
#include <stdint.h>
#include <stdio.h>
#include <string.h>
#include "../host/common/jx-security.h"
#include "../host/common/jx-security-hash.h"
#include "../host/common/jx-security-import.h"

static int hex_value(char c) {
    if (c >= '0' && c <= '9') return c - '0';
    if (c >= 'a' && c <= 'f') return c - 'a' + 10;
    if (c >= 'A' && c <= 'F') return c - 'A' + 10;
    return -1;
}

static void assert_hex(const uint8_t *got, size_t n, const char *hex) {
    size_t i;
    assert(strlen(hex) == n * 2u);
    for (i = 0u; i < n; ++i) {
        int hi = hex_value(hex[i * 2u]);
        int lo = hex_value(hex[i * 2u + 1u]);
        assert(hi >= 0 && lo >= 0);
        assert(got[i] == (uint8_t)((hi << 4) | lo));
    }
}

int main(void) {
    static const uint8_t abc[] = {'a','b','c'};
    uint8_t md5[JX_SECURITY_MD5_BYTES];
    uint8_t sha1[JX_SECURITY_SHA1_BYTES];
    uint8_t sha256[JX_SECURITY_SHA256_BYTES];
    jx_security_signature sigs[8];
    jx_security_import_report report;
    jx_security_result result;
    int count;

    assert(jx_security_md5(abc, sizeof abc, md5) == 0);
    assert_hex(md5, sizeof md5, "900150983cd24fb0d6963f7d28e17f72");
    assert(jx_security_sha1(abc, sizeof abc, sha1) == 0);
    assert_hex(sha1, sizeof sha1, "a9993e364706816aba3e25717850c26c9cd0d89d");
    assert(jx_security_sha256(abc, sizeof abc, sha256) == 0);
    assert_hex(sha256, sizeof sha256,
               "ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad");

    {
        static const uint8_t db[] =
            "# ClamAV/phpMussel-compatible whole-file hashes\n"
            "ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad:3:Test.SHA256\n"
            "900150983cd24fb0d6963f7d28e17f72:3:Test.MD5\n"
            "a9993e364706816aba3e25717850c26c9cd0d89d:*:Test.SHA1:73\n"
            "not-a-hash:3:Rejected.Rule\n";
        memset(&report, 0, sizeof report);
        count = jx_security_import_hash_text(db, sizeof db - 1u, sigs, 8u, 100u, &report);
        assert(count == 3);
        assert(report.imported == 3u);
        assert(report.ignored == 1u);
        assert(report.errors == 1u);
        assert(sigs[0].id == 100u);
        assert(sigs[0].hash_algorithm == JX_SECURITY_HASH_SHA256);
        assert(sigs[0].file_size == 3u);
        assert(strcmp(sigs[0].name, "Test.SHA256") == 0);
        assert(sigs[2].hash_algorithm == JX_SECURITY_HASH_SHA1);
        assert(sigs[2].file_size == JX_SECURITY_SIZE_ANY);

        assert(jx_security_scan_buffer(abc, sizeof abc, sigs, (size_t)count, &result) == 1);
        assert(result.verdict == JX_SECURITY_MALWARE);
        assert(result.signature_id == 100u);
        assert(strcmp(result.signature_name, "Test.SHA256") == 0);
    }

    {
        static const uint8_t pm[] =
            "phpMussel-hash-header\n"
            "900150983cd24fb0d6963f7d28e17f72:3:phpMussel.MD5.Sample\n";
        memset(&report, 0, sizeof report);
        count = jx_security_import_hash_text(pm, sizeof pm - 1u, sigs, 8u, 200u, &report);
        assert(count == 1);
        assert(report.imported == 1u && report.errors == 0u);
        assert(sigs[0].hash_algorithm == JX_SECURITY_HASH_MD5);
        assert(jx_security_scan_buffer(abc, sizeof abc, sigs, 1u, &result) == 1);
        assert(result.signature_id == 200u);
    }

    {
        static const uint8_t wrong_size[] =
            "ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad:4:Wrong.Size\n";
        count = jx_security_import_hash_text(wrong_size, sizeof wrong_size - 1u,
                                             sigs, 8u, 300u, &report);
        assert(count == 1);
        assert(jx_security_scan_buffer(abc, sizeof abc, sigs, 1u, &result) == 0);
    }

    puts("PASS JX MD5/SHA1/SHA256 and ClamAV/phpMussel hash importer");
    return 0;
}
