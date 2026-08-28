#include "../host/common/jx-maintainer-runtime.h"
#include <assert.h>
#include <stdio.h>
#include <string.h>

static void fill(uint8_t out[32], uint8_t value) { memset(out, value, 32u); }
static int digest_cb(const uint8_t *bytes,size_t length,uint8_t out[32],void *context){
    (void)context; uint8_t v=0u; for(size_t i=0;i<length;++i)v=(uint8_t)(v*33u+bytes[i]); fill(out,v?v:1u); return 0;
}
static int listener_cb(const jx_bag_listener_event *event,void *context){
    int *phases=(int*)context; if(event->phase==JX_BAG_LISTENER_PREPARE)phases[0]++; else if(event->phase==JX_BAG_LISTENER_COMMIT)phases[1]++; else if(event->phase==JX_BAG_LISTENER_ROLLBACK)phases[2]++; return 0;
}
int main(void){
    uint8_t trust[32],schema[32],content[32],target_digest[32]; fill(trust,0x51); fill(schema,0x11); fill(content,0x22);
    const uint8_t old_json[]="{\"gap\":4}"; const uint8_t new_json[]="{\"gap\":8}"; assert(digest_cb(new_json,sizeof new_json-1u,target_digest,NULL)==0);
    jx_maintainer_runtime rt; assert(jx_maintainer_runtime_init(&rt,"install-a",trust,digest_cb,NULL)==0);
    assert(jx_maintainer_runtime_add_bag(&rt,"Controls",JX_BAG_DISCIPLINE_RECORD,7,schema,content,old_json,sizeof old_json-1u)==0);
    int phases[3]={0,0,0}; assert(jx_maintainer_runtime_add_listener(&rt,"Controls",JX_BAG_CHANGE_ALL,listener_cb,phases)==0);
    jx_remote_bag_source source={0}; source.version=JX_REMOTE_BAG_VERSION; source.transport=JX_REMOTE_BAG_TRANSPORT_SSH; source.capability_mask=JX_REMOTE_BAG_CAP_WRITE; source.enabled=1u; source.maintainer=1u; strcpy(source.source_id,"maint"); strcpy(source.installation_id,"install-a"); strcpy(source.bag_name,"Controls"); memcpy(source.maintainer_trust_digest,trust,32u); assert(jx_maintainer_runtime_add_source(&rt,&source)==0);
    jx_remote_bag_source wrong=source; strcpy(wrong.installation_id,"install-b"); assert(jx_maintainer_runtime_add_source(&rt,&wrong)!=0);
    jx_remote_bag_request req={0}; req.version=JX_REMOTE_BAG_VERSION; req.transport=JX_REMOTE_BAG_TRANSPORT_SSH; req.requested_capabilities=JX_REMOTE_BAG_CAP_WRITE; strcpy(req.source_id,"maint"); strcpy(req.installation_id,"install-a"); req.sequence=1u; req.issued_at=10u; req.expires_at=20u; req.qualifier.version=JX_BAG_PATCH_VERSION; req.qualifier.discipline=JX_BAG_DISCIPLINE_RECORD; req.qualifier.expected_revision=7u; req.qualifier.target_revision=8u; strcpy(req.qualifier.bag_name,"Controls"); memcpy(req.qualifier.current_schema_digest,schema,32u); memcpy(req.qualifier.current_content_digest,content,32u); memcpy(req.qualifier.target_schema_digest,schema,32u); memcpy(req.qualifier.target_json_digest,target_digest,32u); req.qualifier.json_length=(uint32_t)(sizeof new_json-1u);
    assert(jx_maintainer_service_apply(&rt.service,&req,15u,new_json,sizeof new_json-1u)==0);
    jx_live_bag *bag=jx_live_bag_find(&rt.bags,"Controls"); assert(bag&&bag->revision==8u&&bag->json_length==sizeof new_json-1u&&memcmp(bag->json,new_json,bag->json_length)==0); assert(phases[0]==1&&phases[1]==1&&phases[2]==0); assert(rt.sources[0].last_sequence==1u);
    jx_maintainer_runtime_dispose(&rt); puts("jx-maintainer-runtime: provisioned source -> real live Bag commit through common composition"); return 0;
}
