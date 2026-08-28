#define _POSIX_C_SOURCE 200809L
#include "../common/jx-maintainer-ssh.h"
#include "../common/jx-maintainer-ipc.h"
#include "../common/jx-remote-bag-wire.h"
#include <errno.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/un.h>
#include <unistd.h>

static int read_exact(int fd, uint8_t *out, size_t n){size_t at=0;while(at<n){ssize_t r=read(fd,out+at,n-at);if(r<0){if(errno==EINTR)continue;return -1;}if(r==0)return -1;at+=(size_t)r;}return 0;}
static int write_exact(int fd,const uint8_t *p,size_t n){size_t at=0;while(at<n){ssize_t r=write(fd,p+at,n-at);if(r<0){if(errno==EINTR)continue;return -1;}if(r==0)return -1;at+=(size_t)r;}return 0;}
static int read_line(char *out,size_t cap){size_t n=0;while(n+1<cap){char c;ssize_t r=read(STDIN_FILENO,&c,1);if(r<0){if(errno==EINTR)continue;return -1;}if(r==0)break;out[n++]=c;if(c=='\n')break;}out[n]='\0';return n? (int)n:-1;}

int main(void){
    const char *socket_path=getenv("JX_MAINT_SOCKET");
    const char *source_id=getenv("JX_MAINT_SOURCE");
    const char *installation_id=getenv("JX_MAINT_INSTALLATION");
    if(!socket_path||!*socket_path||!source_id||!*source_id||!installation_id||!*installation_id){fputs("ERR maintainer-not-provisioned\n",stdout);return 2;}

    char line[JX_MAINTAINER_SSH_LINE_MAX+1u];
    int line_len=read_line(line,sizeof line);
    jx_maintainer_ssh_command cmd;
    if(line_len<0||jx_maintainer_ssh_parse_line(line,(size_t)line_len,&cmd)!=0||cmd.kind!=JX_MAINTAINER_SSH_BAG){fputs("ERR command\n",stdout);return 3;}

    uint8_t request_wire[JX_REMOTE_BAG_REQUEST_WIRE_BYTES];
    uint8_t *json=malloc(cmd.json_length);
    if(!json){fputs("ERR memory\n",stdout);return 4;}
    if(read_exact(STDIN_FILENO,request_wire,sizeof request_wire)!=0||read_exact(STDIN_FILENO,json,cmd.json_length)!=0){free(json);fputs("ERR truncated\n",stdout);return 5;}

    jx_remote_bag_request request;
    if(jx_remote_bag_request_read(request_wire,sizeof request_wire,&request)!=0||
       strcmp(request.source_id,source_id)!=0||strcmp(request.installation_id,installation_id)!=0){free(json);fputs("ERR identity\n",stdout);return 6;}

    int fd=socket(AF_UNIX,SOCK_STREAM,0);
    if(fd<0){free(json);fputs("ERR socket\n",stdout);return 7;}
    struct sockaddr_un addr;memset(&addr,0,sizeof addr);addr.sun_family=AF_UNIX;
    size_t pn=strlen(socket_path);if(pn>=sizeof addr.sun_path){close(fd);free(json);fputs("ERR socket-path\n",stdout);return 7;}
    memcpy(addr.sun_path,socket_path,pn+1u);
    if(connect(fd,(const struct sockaddr *)&addr,sizeof addr)!=0){close(fd);free(json);fputs("ERR connect\n",stdout);return 8;}

    jx_maintainer_ipc_header h={JX_MAINTAINER_IPC_VERSION,JX_MAINTAINER_IPC_OP_BAG,0u,JX_REMOTE_BAG_REQUEST_WIRE_BYTES,cmd.json_length};
    uint8_t header[JX_MAINTAINER_IPC_HEADER_BYTES];
    if(jx_maintainer_ipc_write(header,&h)!=0||write_exact(fd,header,sizeof header)!=0||
       write_exact(fd,request_wire,sizeof request_wire)!=0||write_exact(fd,json,cmd.json_length)!=0){close(fd);free(json);fputs("ERR forward\n",stdout);return 9;}
    free(json);shutdown(fd,SHUT_WR);

    char response[512];ssize_t n;
    while((n=read(fd,response,sizeof response))>0) if(write_exact(STDOUT_FILENO,(const uint8_t *)response,(size_t)n)!=0){close(fd);return 10;}
    close(fd);return 0;
}
