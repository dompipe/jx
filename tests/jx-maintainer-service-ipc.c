#define _POSIX_C_SOURCE 200809L
#include "../host/linux/jx-maintainer-service.h"
#include "../host/common/jx-live-bag.h"
#include "../host/common/jx-maintainer-ipc.h"
#include "../host/common/jx-remote-bag-wire.h"
#include <assert.h>
#include <errno.h>
#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/un.h>
#include <sys/wait.h>
#include <unistd.h>

typedef struct { jx_live_bag_registry registry; uint8_t digest[32]; } ctx_t;
typedef struct { uint8_t *json; size_t length; } candidate_t;
static void fill(uint8_t *p,uint8_t v){memset(p,v,32);}
static int resolve_cb(const char *name,jx_bag_patch_current *out,void *context){return jx_live_bag_current(&((ctx_t*)context)->registry,name,out);}
static int build_cb(const jx_bag_patch_current *current,const jx_bag_patch_qualifier *target,const uint8_t *json,size_t length,void **out,uint8_t digest[32],void *context){
    (void)current;(void)target; candidate_t *c=(candidate_t*)calloc(1,sizeof *c); if(!c)return -1; c->json=(uint8_t*)malloc(length); if(!c->json){free(c);return -1;} memcpy(c->json,json,length); c->length=length; memcpy(digest,((ctx_t*)context)->digest,32); *out=c; return 0;
}
static int commit_cb(void *candidate,const jx_bag_patch_qualifier *target,void *context){candidate_t *c=(candidate_t*)candidate; return jx_live_bag_replace(&((ctx_t*)context)->registry,target,c->json,c->length);}
static void discard_cb(void *candidate,void *context){(void)context; candidate_t *c=(candidate_t*)candidate; if(c){free(c->json);free(c);}}
static int write_all(int fd,const uint8_t *p,size_t n){while(n){ssize_t w=write(fd,p,n);if(w<0){if(errno==EINTR)continue;return -1;}p+=(size_t)w;n-=(size_t)w;}return 0;}
int main(void){
    char path[108]; snprintf(path,sizeof path,"/tmp/jx-maint-%ld.sock",(long)getpid());
    ctx_t ctx; memset(&ctx,0,sizeof ctx); jx_live_bag_registry_init(&ctx.registry); fill(ctx.digest,0x44);
    uint8_t schema[32],content[32],trust[32]; fill(schema,0x11); fill(content,0x22); fill(trust,0x55); const uint8_t old_json[]="{\"gap\":4}"; const uint8_t new_json[]="{\"gap\":8}";
    assert(jx_live_bag_add(&ctx.registry,"Controls",JX_BAG_DISCIPLINE_RECORD,7,schema,content,old_json,sizeof old_json-1u)==0);
    jx_remote_bag_source source={0}; source.version=JX_REMOTE_BAG_VERSION; source.transport=JX_REMOTE_BAG_TRANSPORT_SSH; source.capability_mask=JX_REMOTE_BAG_CAP_WRITE; source.enabled=1; source.maintainer=1; strcpy(source.source_id,"corp"); strcpy(source.installation_id,"prod"); strcpy(source.bag_name,"Controls"); memcpy(source.maintainer_trust_digest,trust,32);
    jx_maintainer_service core={0}; core.version=JX_MAINTAINER_SERVICE_VERSION; core.plane.version=JX_REMOTE_BAG_VERSION; core.plane.provisioned=1; strcpy(core.plane.installation_id,"prod"); memcpy(core.plane.maintainer_trust_digest,trust,32); core.sources=&source; core.source_count=1; core.resolve=resolve_cb; core.build=build_cb; core.commit=commit_cb; core.discard=discard_cb; core.context=&ctx;
    jx_linux_maintainer_service service; assert(jx_linux_maintainer_service_open(&service,path,&core)==0);
    jx_remote_bag_request r={0}; r.version=JX_REMOTE_BAG_VERSION; r.transport=JX_REMOTE_BAG_TRANSPORT_SSH; r.requested_capabilities=JX_REMOTE_BAG_CAP_WRITE; strcpy(r.source_id,"corp"); strcpy(r.installation_id,"prod"); r.sequence=1; r.issued_at=10; r.expires_at=20; r.qualifier.version=JX_BAG_PATCH_VERSION; r.qualifier.discipline=JX_BAG_DISCIPLINE_RECORD; r.qualifier.expected_revision=7; r.qualifier.target_revision=8; strcpy(r.qualifier.bag_name,"Controls"); memcpy(r.qualifier.current_schema_digest,schema,32); memcpy(r.qualifier.current_content_digest,content,32); memcpy(r.qualifier.target_schema_digest,schema,32); memcpy(r.qualifier.target_json_digest,ctx.digest,32); r.qualifier.json_length=(uint32_t)(sizeof new_json-1u);
    uint8_t request_wire[JX_REMOTE_BAG_REQUEST_WIRE_BYTES]; assert(jx_remote_bag_request_write(request_wire,&r)==0);
    jx_maintainer_ipc_header ih={JX_MAINTAINER_IPC_VERSION,JX_MAINTAINER_IPC_OP_BAG,0,JX_REMOTE_BAG_REQUEST_WIRE_BYTES,(uint32_t)(sizeof new_json-1u)}; uint8_t hdr[JX_MAINTAINER_IPC_HEADER_BYTES]; assert(jx_maintainer_ipc_write(hdr,&ih)==0);
    pid_t child=fork(); assert(child>=0); if(child==0){int fd=socket(AF_UNIX,SOCK_STREAM,0); struct sockaddr_un a; memset(&a,0,sizeof a); a.sun_family=AF_UNIX; strcpy(a.sun_path,path); if(connect(fd,(struct sockaddr*)&a,sizeof a)!=0)_exit(2); if(write_all(fd,hdr,sizeof hdr)||write_all(fd,request_wire,sizeof request_wire)||write_all(fd,new_json,sizeof new_json-1u))_exit(3); shutdown(fd,SHUT_WR); char resp[32]={0}; ssize_t n=read(fd,resp,sizeof resp-1); close(fd); _exit(n>0&&strncmp(resp,"OK bag",6)==0?0:4);} 
    int processed=0; for(int i=0;i<100&&!processed;++i){int rc=jx_linux_maintainer_service_process_one(&service,15); if(rc==1)processed=1; else if(rc<0)assert(0); else usleep(1000);} assert(processed);
    int status=0; waitpid(child,&status,0); assert(WIFEXITED(status)&&WEXITSTATUS(status)==0); jx_live_bag *b=jx_live_bag_find(&ctx.registry,"Controls"); assert(b&&b->revision==8&&memcmp(b->json,new_json,sizeof new_json-1u)==0);
    jx_linux_maintainer_service_close(&service); jx_live_bag_registry_dispose(&ctx.registry); puts("jx-maintainer-service-ipc: JXM1 -> private service -> live Bag ok"); return 0;
}
