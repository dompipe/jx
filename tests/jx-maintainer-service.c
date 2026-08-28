#include "../host/common/jx-maintainer-service.h"
#include <assert.h>
#include <stdlib.h>
#include <stdio.h>
#include <string.h>

typedef struct { uint64_t revision; int commits; int veto; } state_t;
static void fill(uint8_t *p, uint8_t v) { memset(p,v,32); }
static int resolve_bag(const char *name,jx_bag_patch_current *out,void *ctx){
    state_t *s=(state_t*)ctx; if(strcmp(name,"Controls")!=0)return -1;
    out->bag_name="Controls"; out->discipline=JX_BAG_DISCIPLINE_RECORD; out->revision=s->revision;
    fill(out->schema_digest,0x11); fill(out->content_digest,0x22); return 0;
}
static int build_candidate(const jx_bag_patch_current *c,const jx_bag_patch_qualifier *q,const uint8_t *json,size_t n,void **out,uint8_t digest[32],void *ctx){
    (void)c;(void)q;(void)ctx; uint8_t *p=(uint8_t*)malloc(n); if(!p)return -1; memcpy(p,json,n); *out=p; fill(digest,0x44); return 0;
}
static int commit_candidate(void *candidate,const jx_bag_patch_qualifier *q,void *ctx){ (void)candidate; state_t *s=(state_t*)ctx; s->revision=q->target_revision; s->commits++; return 0; }
static void discard_candidate(void *candidate,void *ctx){ (void)ctx; free(candidate); }
static int listener(const jx_bag_listener_event *e,void *ctx){ state_t *s=(state_t*)ctx; if(e->phase==JX_BAG_LISTENER_PREPARE && s->veto)return -1; return 0; }
int main(void){
    state_t state={7u,0,0};
    jx_remote_bag_source source={0}; source.version=JX_REMOTE_BAG_VERSION; source.transport=JX_REMOTE_BAG_TRANSPORT_SSH; source.capability_mask=JX_REMOTE_BAG_CAP_WRITE; source.enabled=1; source.maintainer=1; strcpy(source.source_id,"corp"); strcpy(source.installation_id,"prod"); strcpy(source.bag_name,"Controls"); fill(source.maintainer_trust_digest,0x55);
    jx_bag_listener_registry listeners; jx_bag_listener_registry_init(&listeners); assert(jx_bag_listener_add(&listeners,"Controls",JX_BAG_CHANGE_ALL,listener,&state)==0);
    jx_maintainer_service svc={0}; svc.version=JX_MAINTAINER_SERVICE_VERSION; svc.plane.version=JX_REMOTE_BAG_VERSION; svc.plane.provisioned=1; strcpy(svc.plane.installation_id,"prod"); fill(svc.plane.maintainer_trust_digest,0x55); svc.sources=&source; svc.source_count=1; svc.listeners=&listeners; svc.resolve=resolve_bag; svc.build=build_candidate; svc.commit=commit_candidate; svc.discard=discard_candidate; svc.context=&state;
    jx_remote_bag_request r={0}; r.version=JX_REMOTE_BAG_VERSION; r.transport=JX_REMOTE_BAG_TRANSPORT_SSH; r.requested_capabilities=JX_REMOTE_BAG_CAP_WRITE; strcpy(r.source_id,"corp"); strcpy(r.installation_id,"prod"); r.sequence=1; r.issued_at=1000; r.expires_at=1100; r.qualifier.version=JX_BAG_PATCH_VERSION; r.qualifier.discipline=JX_BAG_DISCIPLINE_RECORD; r.qualifier.expected_revision=7; r.qualifier.target_revision=8; strcpy(r.qualifier.bag_name,"Controls"); fill(r.qualifier.current_schema_digest,0x11); fill(r.qualifier.current_content_digest,0x22); fill(r.qualifier.target_schema_digest,0x11); fill(r.qualifier.target_json_digest,0x44); const uint8_t json[]="{\"gap\":8}"; r.qualifier.json_length=(uint32_t)(sizeof json-1u);
    assert(jx_maintainer_service_apply(&svc,&r,1050,json,sizeof json-1u)==0); assert(state.revision==8 && state.commits==1 && source.last_sequence==1);
    assert(jx_maintainer_service_apply(&svc,&r,1050,json,sizeof json-1u)==JX_MAINTAINER_SERVICE_ERR_PREPARE);
    state.veto=1; r.sequence=2; r.qualifier.expected_revision=8; r.qualifier.target_revision=9; assert(jx_maintainer_service_apply(&svc,&r,1050,json,sizeof json-1u)==JX_MAINTAINER_SERVICE_ERR_PREPARE); assert(state.revision==8 && source.last_sequence==1);
    puts("jx-maintainer-service: resolve build prepare commit replay veto ok"); return 0;
}
