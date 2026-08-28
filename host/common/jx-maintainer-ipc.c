#include "jx-maintainer-ipc.h"
#include <string.h>

static void put16(uint8_t *p, uint16_t v){p[0]=(uint8_t)(v>>8);p[1]=(uint8_t)v;}
static void put32(uint8_t *p, uint32_t v){p[0]=(uint8_t)(v>>24);p[1]=(uint8_t)(v>>16);p[2]=(uint8_t)(v>>8);p[3]=(uint8_t)v;}
static uint16_t get16(const uint8_t *p){return (uint16_t)(((uint16_t)p[0]<<8)|p[1]);}
static uint32_t get32(const uint8_t *p){return ((uint32_t)p[0]<<24)|((uint32_t)p[1]<<16)|((uint32_t)p[2]<<8)|p[3];}

int jx_maintainer_ipc_write(uint8_t out[JX_MAINTAINER_IPC_HEADER_BYTES],
                            const jx_maintainer_ipc_header *h){
    if(!out||!h||h->version!=JX_MAINTAINER_IPC_VERSION||h->operation!=JX_MAINTAINER_IPC_OP_BAG) return -1;
    memset(out,0,JX_MAINTAINER_IPC_HEADER_BYTES);
    out[0]='J';out[1]='X';out[2]='M';out[3]='1';out[4]=h->version;out[5]=h->operation;
    put16(out+6,h->flags);put32(out+8,h->request_length);put32(out+12,h->json_length);return 0;
}

int jx_maintainer_ipc_read(const uint8_t *in,size_t length,jx_maintainer_ipc_header *h){
    if(!in||!h||length!=JX_MAINTAINER_IPC_HEADER_BYTES) return -1;
    if(in[0]!='J'||in[1]!='X'||in[2]!='M'||in[3]!='1') return -2;
    memset(h,0,sizeof *h);h->version=in[4];h->operation=in[5];h->flags=get16(in+6);
    h->request_length=get32(in+8);h->json_length=get32(in+12);
    if(h->version!=JX_MAINTAINER_IPC_VERSION||h->operation!=JX_MAINTAINER_IPC_OP_BAG) return -3;
    return 0;
}
