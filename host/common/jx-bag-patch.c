#include "jx-bag-patch.h"
#include <string.h>

int jx_bag_patch_digest_equal(const uint8_t a[JX_BAG_PATCH_DIGEST_BYTES],
                              const uint8_t b[JX_BAG_PATCH_DIGEST_BYTES]) {
    if (!a || !b) return 0;
    uint8_t diff = 0u;
    for (size_t i = 0; i < JX_BAG_PATCH_DIGEST_BYTES; ++i) diff |= (uint8_t)(a[i] ^ b[i]);
    return diff == 0u;
}

int jx_bag_patch_discipline_valid(uint8_t discipline) {
    return discipline >= (uint8_t)JX_BAG_DISCIPLINE_RECORD &&
           discipline <= (uint8_t)JX_BAG_DISCIPLINE_SET;
}

static int digest_nonzero(const uint8_t digest[JX_BAG_PATCH_DIGEST_BYTES]) {
    uint8_t any = 0u;
    if (!digest) return 0;
    for (size_t i = 0; i < JX_BAG_PATCH_DIGEST_BYTES; ++i) any |= digest[i];
    return any != 0u;
}

int jx_bag_patch_validate(const jx_bag_patch_current *current,
                          const jx_bag_patch_qualifier *qualifier,
                          const uint8_t *json,
                          size_t json_length,
                          const uint8_t actual_json_digest[JX_BAG_PATCH_DIGEST_BYTES]) {
    if (!current || !qualifier || !json || !actual_json_digest || !current->bag_name) return JX_BAG_PATCH_ERR_ARGUMENT;
    if (qualifier->version != JX_BAG_PATCH_VERSION) return JX_BAG_PATCH_ERR_VERSION;
    if (qualifier->bag_name[0] == '\0' || strlen(qualifier->bag_name) > JX_BAG_PATCH_NAME_MAX ||
        strcmp(current->bag_name, qualifier->bag_name) != 0) return JX_BAG_PATCH_ERR_NAME;
    if (!jx_bag_patch_discipline_valid(qualifier->discipline) || current->discipline != qualifier->discipline)
        return JX_BAG_PATCH_ERR_DISCIPLINE;
    if (qualifier->expected_revision != current->revision || qualifier->target_revision <= qualifier->expected_revision)
        return JX_BAG_PATCH_ERR_REVISION;
    if (!jx_bag_patch_digest_equal(current->schema_digest, qualifier->current_schema_digest))
        return JX_BAG_PATCH_ERR_SCHEMA;
    if (!jx_bag_patch_digest_equal(current->content_digest, qualifier->current_content_digest))
        return JX_BAG_PATCH_ERR_CONTENT;
    if (json_length == 0u || json_length > JX_BAG_PATCH_JSON_MAX || qualifier->json_length != (uint32_t)json_length)
        return JX_BAG_PATCH_ERR_JSON_LENGTH;
    if (!digest_nonzero(qualifier->target_schema_digest) || !digest_nonzero(qualifier->target_json_digest))
        return JX_BAG_PATCH_ERR_TARGET;
    if (!jx_bag_patch_digest_equal(actual_json_digest, qualifier->target_json_digest))
        return JX_BAG_PATCH_ERR_JSON_DIGEST;
    return JX_BAG_PATCH_OK;
}
