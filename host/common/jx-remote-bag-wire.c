#include "jx-remote-bag-wire.h"
#include <string.h>

static void put16(uint8_t *p, uint16_t v) { p[0]=(uint8_t)(v>>8); p[1]=(uint8_t)v; }
static void put32(uint8_t *p, uint32_t v) { p[0]=(uint8_t)(v>>24); p[1]=(uint8_t)(v>>16); p[2]=(uint8_t)(v>>8); p[3]=(uint8_t)v; }
static void put64(uint8_t *p, uint64_t v) { for (int i=7;i>=0;--i) { p[i]=(uint8_t)v; v>>=8; } }
static uint16_t get16(const uint8_t *p) { return (uint16_t)(((uint16_t)p[0]<<8)|p[1]); }
static uint32_t get32(const uint8_t *p) { return ((uint32_t)p[0]<<24)|((uint32_t)p[1]<<16)|((uint32_t)p[2]<<8)|p[3]; }
static uint64_t get64(const uint8_t *p) { uint64_t v=0; for (int i=0;i<8;++i) v=(v<<8)|p[i]; return v; }

int jx_remote_bag_request_write(uint8_t out[JX_REMOTE_BAG_REQUEST_WIRE_BYTES],
                                const jx_remote_bag_request *r) {
    if (!out || !r) return -1;
    memset(out,0,JX_REMOTE_BAG_REQUEST_WIRE_BYTES);
    out[0]=r->version; out[1]=r->transport; put16(out+2,r->requested_capabilities);
    memcpy(out+4,r->source_id,JX_REMOTE_BAG_SOURCE_MAX+1u);
    memcpy(out+68,r->installation_id,JX_REMOTE_BAG_INSTALLATION_MAX+1u);
    put64(out+132,r->sequence); put64(out+140,r->issued_at); put64(out+148,r->expires_at);
    out[156]=r->qualifier.version; out[157]=r->qualifier.discipline; put16(out+158,r->qualifier.flags);
    put64(out+160,r->qualifier.expected_revision); put64(out+168,r->qualifier.target_revision);
    memcpy(out+176,r->qualifier.bag_name,JX_BAG_PATCH_NAME_MAX+1u);
    memcpy(out+240,r->qualifier.current_schema_digest,JX_BAG_PATCH_DIGEST_BYTES);
    memcpy(out+272,r->qualifier.current_content_digest,JX_BAG_PATCH_DIGEST_BYTES);
    memcpy(out+304,r->qualifier.target_schema_digest,JX_BAG_PATCH_DIGEST_BYTES);
    memcpy(out+336,r->qualifier.target_json_digest,JX_BAG_PATCH_DIGEST_BYTES);
    put32(out+368,r->qualifier.json_length);
    return 0;
}

int jx_remote_bag_request_read(const uint8_t *in, size_t length,
                               jx_remote_bag_request *r) {
    if (!in || !r || length != JX_REMOTE_BAG_REQUEST_WIRE_BYTES) return -1;
    memset(r,0,sizeof *r);
    r->version=in[0]; r->transport=in[1]; r->requested_capabilities=get16(in+2);
    memcpy(r->source_id,in+4,JX_REMOTE_BAG_SOURCE_MAX+1u);
    memcpy(r->installation_id,in+68,JX_REMOTE_BAG_INSTALLATION_MAX+1u);
    r->source_id[JX_REMOTE_BAG_SOURCE_MAX]='\0';
    r->installation_id[JX_REMOTE_BAG_INSTALLATION_MAX]='\0';
    r->sequence=get64(in+132); r->issued_at=get64(in+140); r->expires_at=get64(in+148);
    r->qualifier.version=in[156]; r->qualifier.discipline=in[157]; r->qualifier.flags=get16(in+158);
    r->qualifier.expected_revision=get64(in+160); r->qualifier.target_revision=get64(in+168);
    memcpy(r->qualifier.bag_name,in+176,JX_BAG_PATCH_NAME_MAX+1u);
    r->qualifier.bag_name[JX_BAG_PATCH_NAME_MAX]='\0';
    memcpy(r->qualifier.current_schema_digest,in+240,JX_BAG_PATCH_DIGEST_BYTES);
    memcpy(r->qualifier.current_content_digest,in+272,JX_BAG_PATCH_DIGEST_BYTES);
    memcpy(r->qualifier.target_schema_digest,in+304,JX_BAG_PATCH_DIGEST_BYTES);
    memcpy(r->qualifier.target_json_digest,in+336,JX_BAG_PATCH_DIGEST_BYTES);
    r->qualifier.json_length=get32(in+368);
    return 0;
}
