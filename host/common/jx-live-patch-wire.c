#include "jx-live-patch-wire.h"
#include <string.h>
static void put16(uint8_t *p,uint16_t v){p[0]=(uint8_t)(v>>8);p[1]=(uint8_t)v;}
static void put32(uint8_t *p,uint32_t v){p[0]=(uint8_t)(v>>24);p[1]=(uint8_t)(v>>16);p[2]=(uint8_t)(v>>8);p[3]=(uint8_t)v;}
static void put64(uint8_t *p,uint64_t v){for(int i=7;i>=0;--i){p[i]=(uint8_t)v;v>>=8;}}
static uint16_t get16(const uint8_t *p){return (uint16_t)(((uint16_t)p[0]<<8)|p[1]);}
static uint32_t get32(const uint8_t *p){return ((uint32_t)p[0]<<24)|((uint32_t)p[1]<<16)|((uint32_t)p[2]<<8)|p[3];}
static uint64_t get64(const uint8_t *p){uint64_t v=0;for(int i=0;i<8;++i)v=(v<<8)|p[i];return v;}
int jx_patch_manifest_write(uint8_t out[JX_PATCH_MANIFEST_WIRE_BYTES], const jx_patch_manifest *m){if(!out||!m)return -1;memset(out,0,JX_PATCH_MANIFEST_WIRE_BYTES);out[0]=m->version;out[1]=m->protocol;put16(out+2,m->flags);put64(out+4,m->generation);put64(out+12,m->base_generation);put64(out+20,m->issued_at);put64(out+28,m->expires_at);put64(out+36,m->nonce);put32(out+44,m->capability_mask);put32(out+48,m->patch_length);memcpy(out+52,m->base_digest,32);memcpy(out+84,m->target_digest,32);return 0;}
int jx_patch_manifest_read(const uint8_t *in,size_t length,jx_patch_manifest *m){if(!in||!m||length!=JX_PATCH_MANIFEST_WIRE_BYTES)return -1;memset(m,0,sizeof *m);m->version=in[0];m->protocol=in[1];m->flags=get16(in+2);m->generation=get64(in+4);m->base_generation=get64(in+12);m->issued_at=get64(in+20);m->expires_at=get64(in+28);m->nonce=get64(in+36);m->capability_mask=get32(in+44);m->patch_length=get32(in+48);memcpy(m->base_digest,in+52,32);memcpy(m->target_digest,in+84,32);return 0;}
