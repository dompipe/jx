#include "../host/common/jx-maintainer-service.h"
#include "../host/common/jx-live-bag.h"
#include <assert.h>
#include <stdlib.h>
#include <stdio.h>
#include <string.h>

typedef struct { jx_live_bag_registry registry; uint8_t digest[32]; } ctx_t;
typedef struct { uint8_t *json; size_t length; } candidate_t;
static void fill(uint8_t *p,uint8_t v){memset(p,v,32);}
static int resolve_cb(const char *name,jx_bag_patch_current *out,void *context){return jx_live_bag_current(&((ctx_t*)context)->registry,name,out);}
static int build_cb(const jx_bag_patch_current *current,const jx_bag_patch_qualifier *target,const uint8_t *json,size_t length,void **out,uint8_t digest[32],void *context){
    (void)current;(void)target; candidate_t *c=(candidate_t*)calloc(1,sizeof *c); if(!c)return -1; c->json=(uint8_t*)malloc(length); if(!c->json){free(c);return -1;} memcpy(c->json,json,length); c->length=length; memcpy(digest,((ctx_t*)context)->digest,32); *out=c; return 0;
}
static int commit_cb(void *candidate,const jx_bag_patch_qualifier *target,void *context){candidate_t *c=(candidate_t*)candidate; return jx_live_bag_replace(&((ctx_t*)context)->registry,target,c->json,c->length);}
static void discard_cb(void *candidate,void *context){(void)context; candidate_t *c=(candidate_t*)candidate; if(c){free(c->json);free(c);}}
int main(void){
    ctx_t ctx; memset(&ctx,0,sizeof ctx); jx_live_bag_registry_init(&ctx.registry); fill(ctx.digest,0x44);
    uint8_t schema[32],content[32],trust[32]; fill(schema,0x11); fill(content,0x22); fill(trust,0x55); const uint8_t old_json[]="{\"gap\":4}"; const uint8_t new_json[]="{\"gap\":8}";
    assert(jx_live_bag_add(&ctx.registry,"Controls",JX_BAG_DISCIPLINE_RECORD,7,schema,content,old_json,sizeof old_json-1u)==0);
    jx_remote_bag_source source={0}; source.version=JX_REMOTE_BAG_VERSION; source.transport=JX_REMOTE_BAG_TRANSPORT_SSH; source.capability_mask=JX_REMOTE_BAG_CAP_WRITE; source.enabled=1; source.maintainer=1; strcpy(source.source_id,"corp"); strcpy(source.installation_id,"prod"); strcpy(source.bag_name,"Controls"); memcpy(source.maintainer_trust_digest,trust,32);
    jx_maintainer_service svc={0}; svc.version=JX_MAINTAINER_SERVICE_VERSION; svc.plane.version=JX_REMOTE_BAG_VERSION; svc.plane.provisioned=1; strcpy(svc.plane.installation_id,"prod"); memcpy(svc.plane.maintainer_trust_digest,trust,32); svc.sources=&source; svc.source_count=1; svc.resolve=resolve_cb; svc.build=build_cb; svc.commit=commit_cb; svc.discard=discard_cb; svc.context=&ctx;
    jx_remote_bag_request r={0}; r.version=JX_REMOTE_BAG_VERSION; r.transport=JX_REMOTE_BAG_TRANSPORT_SSH; r.requested_capabilities=JX_REMOTE_BAG_CAP_WRITE; strcpy(r.source_id,"corp"); strcpy(r.installation_id,"prod"); r.sequence=1; r.issued_at=10; r.expires_at=20; r.qualifier.version=JX_BAG_PATCH_VERSION; r.qualifier.discipline=JX_BAG_DISCIPLINE_RECORD; r.qualifier.expected_revision=7; r.qualifier.target_revision=8; strcpy(r.qualifier.bag_name,"Controls"); memcpy(r.qualifier.current_schema_digest,schema,32); memcpy(r.qualifier.current_content_digest,content,32); memcpy(r.qualifier.target_schema_digest,schema,32); memcpy(r.qualifier.target_json_digest,ctx.digest,32); r.qualifier.json_length=(uint32_t)(sizeof new_json-1u);
    assert(jx_maintainer_service_apply(&svc,&r,15,new_json,sizeof new_json-1u)==0);
    jx_live_bag *b=jx_live_bag_find(&ctx.registry,"Controls"); assert(b&&b->revision==8&&b->json_length==sizeof new_json-1u&&memcmp(b->json,new_json,b->json_length)==0&&memcmp(b->content_digest,ctx.digest,32)==0);
    jx_live_bag_registry_dispose(&ctx.registry); puts("jx-live-bag-maintainer: live canonical Bag replaced through maintainer service"); return 0;
}
