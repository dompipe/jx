#include "../host/common/jx-bag-patch.h"
#include <stdio.h>
#include <string.h>

static void fill(uint8_t out[32], uint8_t value) { memset(out, value, 32u); }

int main(void) {
    jx_bag_patch_current current;
    memset(&current, 0, sizeof current);
    current.bag_name = "Controls";
    current.discipline = JX_BAG_DISCIPLINE_VECTOR;
    current.revision = 41u;
    fill(current.schema_digest, 0x11u);
    fill(current.content_digest, 0x22u);

    jx_bag_patch_qualifier q;
    memset(&q, 0, sizeof q);
    q.version = JX_BAG_PATCH_VERSION;
    q.discipline = JX_BAG_DISCIPLINE_VECTOR;
    q.expected_revision = 41u;
    q.target_revision = 42u;
    strcpy(q.bag_name, "Controls");
    fill(q.current_schema_digest, 0x11u);
    fill(q.current_content_digest, 0x22u);
    fill(q.target_schema_digest, 0x33u);
    fill(q.target_json_digest, 0x44u);
    const uint8_t json[] = "[{\"type\":\"button\",\"gap\":8}]";
    q.json_length = (uint32_t)(sizeof json - 1u);
    uint8_t digest[32]; fill(digest, 0x44u);

    if (jx_bag_patch_validate(&current, &q, json, sizeof json - 1u, digest) != JX_BAG_PATCH_OK) return 1;
    q.expected_revision = 40u;
    if (jx_bag_patch_validate(&current, &q, json, sizeof json - 1u, digest) != JX_BAG_PATCH_ERR_REVISION) return 2;
    q.expected_revision = 41u;
    q.current_schema_digest[0] ^= 1u;
    if (jx_bag_patch_validate(&current, &q, json, sizeof json - 1u, digest) != JX_BAG_PATCH_ERR_SCHEMA) return 3;
    q.current_schema_digest[0] ^= 1u;
    q.current_content_digest[0] ^= 1u;
    if (jx_bag_patch_validate(&current, &q, json, sizeof json - 1u, digest) != JX_BAG_PATCH_ERR_CONTENT) return 4;
    q.current_content_digest[0] ^= 1u;
    digest[0] ^= 1u;
    if (jx_bag_patch_validate(&current, &q, json, sizeof json - 1u, digest) != JX_BAG_PATCH_ERR_JSON_DIGEST) return 5;

    puts("jx-bag-patch: over-qualified revision/schema/content/json validation ok");
    return 0;
}
