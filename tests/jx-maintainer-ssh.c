#include "../host/common/jx-maintainer-ssh.h"
#include "../host/common/jx-remote-bag.h"
#include <assert.h>
#include <stdio.h>
#include <string.h>

static void fill(uint8_t *d, size_t n, uint8_t v) { memset(d,v,n); }

int main(void) {
    jx_remote_bag_request in={0}, out={0};
    in.version=JX_REMOTE_BAG_VERSION;
    in.transport=JX_REMOTE_BAG_TRANSPORT_SSH;
    in.requested_capabilities=JX_REMOTE_BAG_CAP_WRITE;
    strcpy(in.source_id,"corp-maintainer");
    strcpy(in.installation_id,"prod-east-1");
    in.sequence=0x0102030405060708ull;
    in.issued_at=1000u; in.expires_at=1100u;
    in.qualifier.version=JX_BAG_PATCH_VERSION;
    in.qualifier.discipline=JX_BAG_DISCIPLINE_RECORD;
    in.qualifier.expected_revision=9u; in.qualifier.target_revision=10u;
    strcpy(in.qualifier.bag_name,"Controls");
    fill(in.qualifier.current_schema_digest,32,0x11);
    fill(in.qualifier.current_content_digest,32,0x22);
    fill(in.qualifier.target_schema_digest,32,0x11);
    fill(in.qualifier.target_json_digest,32,0x44);
    in.qualifier.json_length=17u;

    uint8_t wire[JX_REMOTE_BAG_REQUEST_WIRE_BYTES];
    assert(jx_remote_bag_request_write(wire,&in)==0);
    assert(wire[132]==0x01 && wire[139]==0x08);
    assert(jx_remote_bag_request_read(wire,sizeof wire,&out)==0);
    assert(out.sequence==in.sequence);
    assert(strcmp(out.source_id,"corp-maintainer")==0);
    assert(strcmp(out.installation_id,"prod-east-1")==0);
    assert(strcmp(out.qualifier.bag_name,"Controls")==0);
    assert(out.qualifier.json_length==17u);
    assert(jx_remote_bag_request_read(wire,sizeof wire-1u,&out)!=0);

    jx_maintainer_ssh_command cmd;
    const char good[]="JX-MAINT/1 BAG 372 17\n";
    assert(jx_maintainer_ssh_parse_line(good,sizeof good-1u,&cmd)==0);
    assert(cmd.kind==JX_MAINTAINER_SSH_BAG && cmd.request_length==372u && cmd.json_length==17u);
    const char shell[]="JX-MAINT/1 BAG 372 17;id\n";
    assert(jx_maintainer_ssh_parse_line(shell,sizeof shell-1u,&cmd)!=0);
    const char wrong[]="JX-MAINT/1 BAG 371 17\n";
    assert(jx_maintainer_ssh_parse_line(wrong,sizeof wrong-1u,&cmd)!=0);

    jx_maintainer_plane plane={0};
    plane.version=JX_REMOTE_BAG_VERSION; plane.provisioned=1u;
    strcpy(plane.installation_id,"prod-east-1");
    fill(plane.maintainer_trust_digest,32,0x55);
    jx_remote_bag_source source={0};
    source.version=JX_REMOTE_BAG_VERSION; source.transport=JX_REMOTE_BAG_TRANSPORT_SSH;
    source.capability_mask=JX_REMOTE_BAG_CAP_WRITE; source.enabled=1u; source.maintainer=1u;
    strcpy(source.source_id,"corp-maintainer"); strcpy(source.installation_id,"prod-east-1"); strcpy(source.bag_name,"Controls");
    fill(source.maintainer_trust_digest,32,0x55);
    assert(jx_remote_bag_authorize(&plane,&source,&out,1050u)==JX_REMOTE_BAG_OK);

    puts("jx-maintainer-ssh: canonical wire + restricted SSH framing ok");
    return 0;
}
