#include "jx-security-hash.h"
#include <string.h>

static uint32_t rol32(uint32_t x, unsigned n) { return (x << n) | (x >> (32u - n)); }
static uint32_t ror32(uint32_t x, unsigned n) { return (x >> n) | (x << (32u - n)); }
static uint32_t load_le32(const uint8_t *p) {
    return (uint32_t)p[0] | ((uint32_t)p[1] << 8) | ((uint32_t)p[2] << 16) | ((uint32_t)p[3] << 24);
}
static uint32_t load_be32(const uint8_t *p) {
    return ((uint32_t)p[0] << 24) | ((uint32_t)p[1] << 16) | ((uint32_t)p[2] << 8) | (uint32_t)p[3];
}
static void store_le32(uint8_t *p, uint32_t v) {
    p[0]=(uint8_t)v; p[1]=(uint8_t)(v>>8); p[2]=(uint8_t)(v>>16); p[3]=(uint8_t)(v>>24);
}
static void store_be32(uint8_t *p, uint32_t v) {
    p[0]=(uint8_t)(v>>24); p[1]=(uint8_t)(v>>16); p[2]=(uint8_t)(v>>8); p[3]=(uint8_t)v;
}

static void md5_block(uint32_t s[4], const uint8_t block[64]) {
    static const uint32_t k[64] = {
        0xd76aa478u,0xe8c7b756u,0x242070dbu,0xc1bdceeeu,0xf57c0fafu,0x4787c62au,0xa8304613u,0xfd469501u,
        0x698098d8u,0x8b44f7afu,0xffff5bb1u,0x895cd7beu,0x6b901122u,0xfd987193u,0xa679438eu,0x49b40821u,
        0xf61e2562u,0xc040b340u,0x265e5a51u,0xe9b6c7aau,0xd62f105du,0x02441453u,0xd8a1e681u,0xe7d3fbc8u,
        0x21e1cde6u,0xc33707d6u,0xf4d50d87u,0x455a14edu,0xa9e3e905u,0xfcefa3f8u,0x676f02d9u,0x8d2a4c8au,
        0xfffa3942u,0x8771f681u,0x6d9d6122u,0xfde5380cu,0xa4beea44u,0x4bdecfa9u,0xf6bb4b60u,0xbebfbc70u,
        0x289b7ec6u,0xeaa127fau,0xd4ef3085u,0x04881d05u,0xd9d4d039u,0xe6db99e5u,0x1fa27cf8u,0xc4ac5665u,
        0xf4292244u,0x432aff97u,0xab9423a7u,0xfc93a039u,0x655b59c3u,0x8f0ccc92u,0xffeff47du,0x85845dd1u,
        0x6fa87e4fu,0xfe2ce6e0u,0xa3014314u,0x4e0811a1u,0xf7537e82u,0xbd3af235u,0x2ad7d2bbu,0xeb86d391u
    };
    static const uint8_t r[64] = {
        7,12,17,22,7,12,17,22,7,12,17,22,7,12,17,22,
        5,9,14,20,5,9,14,20,5,9,14,20,5,9,14,20,
        4,11,16,23,4,11,16,23,4,11,16,23,4,11,16,23,
        6,10,15,21,6,10,15,21,6,10,15,21,6,10,15,21
    };
    uint32_t m[16], a=s[0], b=s[1], c=s[2], d=s[3];
    unsigned i;
    for (i=0;i<16;i++) m[i]=load_le32(block+i*4u);
    for (i=0;i<64;i++) {
        uint32_t f,g,tmp;
        if (i<16) { f=(b&c)|((~b)&d); g=i; }
        else if (i<32) { f=(d&b)|((~d)&c); g=(5u*i+1u)%16u; }
        else if (i<48) { f=b^c^d; g=(3u*i+5u)%16u; }
        else { f=c^(b|(~d)); g=(7u*i)%16u; }
        tmp=d; d=c; c=b; b=b+rol32(a+f+k[i]+m[g],r[i]); a=tmp;
    }
    s[0]+=a; s[1]+=b; s[2]+=c; s[3]+=d;
}

int jx_security_md5(const uint8_t *data,size_t length,uint8_t out[JX_SECURITY_MD5_BYTES]) {
    uint32_t s[4]={0x67452301u,0xefcdab89u,0x98badcfeu,0x10325476u};
    uint8_t block[128]; size_t full=length/64u,rem=length%64u,i,total; uint64_t bits=(uint64_t)length*8u;
    if((!data&&length)||!out)return -1;
    for(i=0;i<full;i++)md5_block(s,data+i*64u);
    memset(block,0,sizeof block); if(rem)memcpy(block,data+full*64u,rem); block[rem]=0x80u;
    total=(rem<56u)?64u:128u;
    for(i=0;i<8;i++)block[total-8u+i]=(uint8_t)(bits>>(8u*i));
    md5_block(s,block); if(total==128u)md5_block(s,block+64u);
    for(i=0;i<4;i++)store_le32(out+i*4u,s[i]);
    return 0;
}

static void sha1_block(uint32_t s[5], const uint8_t block[64]) {
    uint32_t w[80],a,b,c,d,e; unsigned i;
    for(i=0;i<16;i++)w[i]=load_be32(block+i*4u);
    for(i=16;i<80;i++)w[i]=rol32(w[i-3]^w[i-8]^w[i-14]^w[i-16],1);
    a=s[0];b=s[1];c=s[2];d=s[3];e=s[4];
    for(i=0;i<80;i++) {
        uint32_t f,k,t;
        if(i<20){f=(b&c)|((~b)&d);k=0x5a827999u;}
        else if(i<40){f=b^c^d;k=0x6ed9eba1u;}
        else if(i<60){f=(b&c)|(b&d)|(c&d);k=0x8f1bbcdcu;}
        else{f=b^c^d;k=0xca62c1d6u;}
        t=rol32(a,5)+f+e+k+w[i];e=d;d=c;c=rol32(b,30);b=a;a=t;
    }
    s[0]+=a;s[1]+=b;s[2]+=c;s[3]+=d;s[4]+=e;
}

int jx_security_sha1(const uint8_t *data,size_t length,uint8_t out[JX_SECURITY_SHA1_BYTES]) {
    uint32_t s[5]={0x67452301u,0xefcdab89u,0x98badcfeu,0x10325476u,0xc3d2e1f0u};
    uint8_t block[128]; size_t full=length/64u,rem=length%64u,i,total; uint64_t bits=(uint64_t)length*8u;
    if((!data&&length)||!out)return -1;
    for(i=0;i<full;i++)sha1_block(s,data+i*64u);
    memset(block,0,sizeof block); if(rem)memcpy(block,data+full*64u,rem); block[rem]=0x80u;
    total=(rem<56u)?64u:128u;
    for(i=0;i<8;i++)block[total-1u-i]=(uint8_t)(bits>>(8u*i));
    sha1_block(s,block); if(total==128u)sha1_block(s,block+64u);
    for(i=0;i<5;i++)store_be32(out+i*4u,s[i]);
    return 0;
}

static void sha256_block(uint32_t s[8], const uint8_t block[64]) {
    static const uint32_t k[64]={
        0x428a2f98u,0x71374491u,0xb5c0fbcfu,0xe9b5dba5u,0x3956c25bu,0x59f111f1u,0x923f82a4u,0xab1c5ed5u,
        0xd807aa98u,0x12835b01u,0x243185beu,0x550c7dc3u,0x72be5d74u,0x80deb1feu,0x9bdc06a7u,0xc19bf174u,
        0xe49b69c1u,0xefbe4786u,0x0fc19dc6u,0x240ca1ccu,0x2de92c6fu,0x4a7484aau,0x5cb0a9dcu,0x76f988dau,
        0x983e5152u,0xa831c66du,0xb00327c8u,0xbf597fc7u,0xc6e00bf3u,0xd5a79147u,0x06ca6351u,0x14292967u,
        0x27b70a85u,0x2e1b2138u,0x4d2c6dfcu,0x53380d13u,0x650a7354u,0x766a0abbu,0x81c2c92eu,0x92722c85u,
        0xa2bfe8a1u,0xa81a664bu,0xc24b8b70u,0xc76c51a3u,0xd192e819u,0xd6990624u,0xf40e3585u,0x106aa070u,
        0x19a4c116u,0x1e376c08u,0x2748774cu,0x34b0bcb5u,0x391c0cb3u,0x4ed8aa4au,0x5b9cca4fu,0x682e6ff3u,
        0x748f82eeu,0x78a5636fu,0x84c87814u,0x8cc70208u,0x90befffau,0xa4506cebu,0xbef9a3f7u,0xc67178f2u};
    uint32_t w[64],a,b,c,d,e,f,g,h; unsigned i;
    for(i=0;i<16;i++)w[i]=load_be32(block+i*4u);
    for(i=16;i<64;i++){
        uint32_t s0=ror32(w[i-15],7)^ror32(w[i-15],18)^(w[i-15]>>3);
        uint32_t s1=ror32(w[i-2],17)^ror32(w[i-2],19)^(w[i-2]>>10);
        w[i]=w[i-16]+s0+w[i-7]+s1;
    }
    a=s[0];b=s[1];c=s[2];d=s[3];e=s[4];f=s[5];g=s[6];h=s[7];
    for(i=0;i<64;i++){
        uint32_t S1=ror32(e,6)^ror32(e,11)^ror32(e,25),ch=(e&f)^((~e)&g);
        uint32_t t1=h+S1+ch+k[i]+w[i],S0=ror32(a,2)^ror32(a,13)^ror32(a,22),maj=(a&b)^(a&c)^(b&c);
        uint32_t t2=S0+maj;h=g;g=f;f=e;e=d+t1;d=c;c=b;b=a;a=t1+t2;
    }
    s[0]+=a;s[1]+=b;s[2]+=c;s[3]+=d;s[4]+=e;s[5]+=f;s[6]+=g;s[7]+=h;
}

int jx_security_sha256(const uint8_t *data,size_t length,uint8_t out[JX_SECURITY_SHA256_BYTES]) {
    uint32_t s[8]={0x6a09e667u,0xbb67ae85u,0x3c6ef372u,0xa54ff53au,0x510e527fu,0x9b05688cu,0x1f83d9abu,0x5be0cd19u};
    uint8_t block[128]; size_t full=length/64u,rem=length%64u,i,total; uint64_t bits=(uint64_t)length*8u;
    if((!data&&length)||!out)return -1;
    for(i=0;i<full;i++)sha256_block(s,data+i*64u);
    memset(block,0,sizeof block); if(rem)memcpy(block,data+full*64u,rem); block[rem]=0x80u;
    total=(rem<56u)?64u:128u;
    for(i=0;i<8;i++)block[total-1u-i]=(uint8_t)(bits>>(8u*i));
    sha256_block(s,block); if(total==128u)sha256_block(s,block+64u);
    for(i=0;i<8;i++)store_be32(out+i*4u,s[i]);
    return 0;
}
