#ifndef JX_BAG_PATCH_H
#define JX_BAG_PATCH_H

#include <stddef.h>
#include <stdint.h>

#define JX_BAG_PATCH_VERSION 1u
#define JX_BAG_PATCH_DIGEST_BYTES 32u
#define JX_BAG_PATCH_NAME_MAX 63u
#define JX_BAG_PATCH_JSON_MAX (16u * 1024u * 1024u)

typedef enum {
    JX_BAG_DISCIPLINE_RECORD = 1,
    JX_BAG_DISCIPLINE_VECTOR = 2,
    JX_BAG_DISCIPLINE_STACK = 3,
    JX_BAG_DISCIPLINE_QUEUE = 4,
    JX_BAG_DISCIPLINE_DEQUE = 5,
    JX_BAG_DISCIPLINE_MAP = 6,
    JX_BAG_DISCIPLINE_SET = 7
} jx_bag_discipline;

typedef struct {
    uint8_t version;
    uint8_t discipline;
    uint16_t flags;
    uint64_t expected_revision;
    uint64_t target_revision;
    char bag_name[JX_BAG_PATCH_NAME_MAX + 1u];
    uint8_t current_schema_digest[JX_BAG_PATCH_DIGEST_BYTES];
    uint8_t current_content_digest[JX_BAG_PATCH_DIGEST_BYTES];
    uint8_t target_schema_digest[JX_BAG_PATCH_DIGEST_BYTES];
    uint8_t target_json_digest[JX_BAG_PATCH_DIGEST_BYTES];
    uint32_t json_length;
} jx_bag_patch_qualifier;

typedef struct {
    const char *bag_name;
    uint8_t discipline;
    uint64_t revision;
    uint8_t schema_digest[JX_BAG_PATCH_DIGEST_BYTES];
    uint8_t content_digest[JX_BAG_PATCH_DIGEST_BYTES];
} jx_bag_patch_current;

typedef enum {
    JX_BAG_PATCH_OK = 0,
    JX_BAG_PATCH_ERR_ARGUMENT = -1,
    JX_BAG_PATCH_ERR_VERSION = -2,
    JX_BAG_PATCH_ERR_NAME = -3,
    JX_BAG_PATCH_ERR_DISCIPLINE = -4,
    JX_BAG_PATCH_ERR_REVISION = -5,
    JX_BAG_PATCH_ERR_SCHEMA = -6,
    JX_BAG_PATCH_ERR_CONTENT = -7,
    JX_BAG_PATCH_ERR_JSON_LENGTH = -8,
    JX_BAG_PATCH_ERR_JSON_DIGEST = -9,
    JX_BAG_PATCH_ERR_TARGET = -10
} jx_bag_patch_result;

int jx_bag_patch_digest_equal(const uint8_t a[JX_BAG_PATCH_DIGEST_BYTES],
                              const uint8_t b[JX_BAG_PATCH_DIGEST_BYTES]);
int jx_bag_patch_discipline_valid(uint8_t discipline);
int jx_bag_patch_validate(const jx_bag_patch_current *current,
                          const jx_bag_patch_qualifier *qualifier,
                          const uint8_t *json,
                          size_t json_length,
                          const uint8_t actual_json_digest[JX_BAG_PATCH_DIGEST_BYTES]);

#endif
