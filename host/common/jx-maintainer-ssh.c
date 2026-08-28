#define _POSIX_C_SOURCE 200809L
#include "jx-maintainer-ssh.h"
#include <ctype.h>
#include <stdlib.h>
#include <string.h>

static int forbidden(char c) {
    switch (c) {
        case ';': case '|': case '&': case '`': case '$': case '>': case '<':
        case '\\': case '\'': case '"': case '(': case ')': case '{': case '}':
            return 1;
        default: return 0;
    }
}

static int parse_u32(const char *s, uint32_t *out) {
    if (!s || !*s || !out) return -1;
    unsigned long long v=0;
    for (const char *p=s; *p; ++p) {
        if (!isdigit((unsigned char)*p)) return -1;
        v=v*10u+(unsigned)(*p-'0');
        if (v>0xffffffffull) return -1;
    }
    *out=(uint32_t)v;
    return 0;
}

int jx_maintainer_ssh_parse_line(const char *line, size_t length,
                                 jx_maintainer_ssh_command *out) {
    if (!line || !out || length==0u || length>JX_MAINTAINER_SSH_LINE_MAX) return -1;
    memset(out,0,sizeof *out);
    char buf[JX_MAINTAINER_SSH_LINE_MAX+1u];
    memcpy(buf,line,length); buf[length]='\0';
    while (length && (buf[length-1]=='\n' || buf[length-1]=='\r')) buf[--length]='\0';
    for (size_t i=0;i<length;++i) if (forbidden(buf[i])) return -2;

    char *save=NULL;
    char *a=strtok_r(buf," ", &save);
    char *b=strtok_r(NULL," ", &save);
    char *c=strtok_r(NULL," ", &save);
    char *d=strtok_r(NULL," ", &save);
    char *e=strtok_r(NULL," ", &save);
    if (!a || !b || !c || !d || e) return -3;
    if (strcmp(a,"JX-MAINT/1")!=0 || strcmp(b,"BAG")!=0) return -4;
    if (parse_u32(c,&out->request_length)!=0 || parse_u32(d,&out->json_length)!=0) return -5;
    if (out->request_length != JX_REMOTE_BAG_REQUEST_WIRE_BYTES ||
        out->json_length==0u || out->json_length>JX_BAG_PATCH_JSON_MAX) return -6;
    out->kind=JX_MAINTAINER_SSH_BAG;
    return 0;
}
