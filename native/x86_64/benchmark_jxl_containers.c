#define _POSIX_C_SOURCE 200809L
#include "jxl_container_runtime.h"

#include <inttypes.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>

/*
 * Native JXL container benchmark.
 *
 * The timed path is:
 *   6-byte prepared JXL instruction
 *     -> jx_jxl_container_execute
 *     -> admitted operation-specific binding
 *     -> pure assembly container primitive
 *     -> Bag memory
 *
 * Allocation, zeroing, binding construction and instruction construction stay
 * outside the timed region. Each logical operation is therefore a real JXL
 * dispatch, not a direct C call to the container primitive.
 *
 * Map/Set law in this harness matches canonical native JXL:
 *   Map = ordered keys[] + values[] (2D array)
 *   Set = ordered unique keys[] (1D array)
 */

enum {
    OP_PUSH = 0x40,
    OP_POP = 0x41,
    OP_PUSHF = 0x42,
    OP_PUSHB = 0x43,
    OP_POPF = 0x44,
    OP_POPB = 0x45,
    OP_EMPLACE = 0x46,
    OP_GET = 0x47,
    OP_PUT = 0x48,
    OP_HAS = 0x49,
    OP_REMOVE = 0x4a,
    SEL_UNUSED = 0x7f,
};

typedef struct Stats {
    double median_ms;
    double min_ms;
    double p95_ms;
    double mops_s;
    double ns_op;
    uint64_t checksum;
} Stats;

typedef struct RunResult {
    double ms;
    uint64_t checksum;
    int ok;
} RunResult;

static double now_ms(void)
{
    struct timespec ts;
    if (clock_gettime(CLOCK_MONOTONIC, &ts) != 0) {
        perror("clock_gettime");
        exit(2);
    }
    return (double)ts.tv_sec * 1000.0 + (double)ts.tv_nsec / 1000000.0;
}

static int cmp_double(const void *a, const void *b)
{
    const double da = *(const double *)a;
    const double db = *(const double *)b;
    return (da > db) - (da < db);
}

static uint64_t next_pow2(uint64_t x)
{
    uint64_t p = 1;
    while (p < x) {
        if (p > (UINT64_MAX >> 1)) return 0;
        p <<= 1;
    }
    return p;
}

static void make_inst(uint8_t out[6], uint8_t opcode, uint16_t binding_id,
                      uint8_t src0, uint8_t src1, uint8_t dst)
{
    out[0] = opcode;
    out[1] = (uint8_t)(0x80u | (binding_id & 0x7fu));
    out[2] = (uint8_t)(0x80u | ((binding_id >> 7) & 0x7fu));
    out[3] = (uint8_t)(0x80u | (src0 & 0x7fu));
    out[4] = (uint8_t)(0x80u | (src1 & 0x7fu));
    out[5] = (uint8_t)(0x80u | (dst & 0x7fu));
}

static int exec_inst(const uint8_t inst[6], JxJxlContainerBinding *bindings,
                     uint64_t binding_count, uint64_t window8[8])
{
    const uint8_t *next = jx_jxl_container_execute(inst, bindings, window8, binding_count);
    return next == inst + JX_JXL_CONTAINER_INSTRUCTION_BYTES;
}

static void init_binding(JxJxlContainerBinding *b, void *fn, uint64_t *base,
                         uint64_t *head, uint64_t *tail, uint64_t capacity,
                         uint64_t mask, uint64_t *generation, uint64_t *flags,
                         void *aux)
{
    memset(b, 0, sizeof(*b));
    b->native_fn = fn;
    b->base = base;
    b->head = head;
    b->tail = tail;
    b->capacity = capacity;
    b->mask = mask;
    b->generation = generation;
    b->flags = flags;
    b->aux = aux;
}

static RunResult run_record(uint64_t total_ops)
{
    const uint64_t n = total_ops / 2;
    uint64_t slots[3] = {0,0,0}, gen = 0, flags = 0, w[8] = {0};
    JxJxlContainerBinding b[2];
    uint8_t put[6], get[6];
    init_binding(&b[0], (void *)jx_record_put_u64, slots, NULL, NULL, 3, 0, &gen, &flags, NULL);
    init_binding(&b[1], (void *)jx_record_get_u64, slots, NULL, NULL, 3, 0, &gen, &flags, NULL);
    make_inst(put, OP_PUT, 0, 0, 1, SEL_UNUSED);
    make_inst(get, OP_GET, 1, 0, SEL_UNUSED, 2);

    uint64_t x = 0;
    const double t0 = now_ms();
    for (uint64_t i=0;i<n;i++) {
        w[0]=0; w[1]=i;
        if (!exec_inst(put,b,2,w) || !exec_inst(get,b,2,w)) return (RunResult){0,0,0};
        x ^= w[2];
    }
    return (RunResult){now_ms()-t0,x,1};
}

static RunResult run_vector(uint64_t total_ops, int stack_mode)
{
    const uint64_t n = total_ops / 2;
    const uint64_t cap = n ? n : 1;
    uint64_t *storage = calloc((size_t)cap, sizeof(uint64_t));
    if (!storage) return (RunResult){0,0,0};
    uint64_t count=0,gen=0,flags=0,w[8]={0};
    JxJxlContainerBinding b[2]; uint8_t a[6],z[6];
    init_binding(&b[0], (void *)jx_vector_push_u64, storage, NULL, &count, cap, 0, &gen, &flags, NULL);
    init_binding(&b[1], stack_mode ? (void *)jx_vector_pop_u64 : (void *)jx_vector_get_u64,
                 storage, NULL, &count, cap, 0, &gen, &flags, NULL);
    make_inst(a,OP_PUSH,0,0,SEL_UNUSED,SEL_UNUSED);
    make_inst(z,stack_mode?OP_POP:OP_GET,1,stack_mode?SEL_UNUSED:0,SEL_UNUSED,2);

    uint64_t x=0; int ok=1; const double t0=now_ms();
    for(uint64_t i=0;i<n;i++){w[0]=i;if(!exec_inst(a,b,2,w)){ok=0;break;}}
    if(ok){
        for(uint64_t i=0;i<n;i++){
            if(!stack_mode)w[0]=i;
            if(!exec_inst(z,b,2,w)){ok=0;break;}
            x^=w[2];
        }
    }
    const double elapsed=now_ms()-t0; free(storage);
    return (RunResult){elapsed,x,ok};
}

static RunResult run_ring(uint64_t total_ops, int deque_mode)
{
    const uint64_t n=total_ops/2;
    uint64_t cap=next_pow2(n?n:1); if(!cap)return (RunResult){0,0,0};
    uint64_t *storage=calloc((size_t)cap,sizeof(uint64_t)); if(!storage)return (RunResult){0,0,0};
    uint64_t head=0,tail=0,gen=0,flags=0,w[8]={0};
    JxJxlContainerBinding b[2]; uint8_t push[6],pop[6];
    void *push_fn=deque_mode?(void *)jx_deque_push_back_u64:(void *)jx_queue_push_u64;
    void *pop_fn=deque_mode?(void *)jx_deque_pop_front_u64:(void *)jx_queue_pop_u64;
    init_binding(&b[0],push_fn,storage,&head,&tail,cap,cap-1,&gen,&flags,NULL);
    init_binding(&b[1],pop_fn,storage,&head,&tail,cap,cap-1,&gen,&flags,NULL);
    make_inst(push,deque_mode?OP_PUSHB:OP_PUSH,0,0,SEL_UNUSED,SEL_UNUSED);
    make_inst(pop,deque_mode?OP_POPF:OP_POP,1,SEL_UNUSED,SEL_UNUSED,2);

    uint64_t x=0;int ok=1;const double t0=now_ms();
    for(uint64_t i=0;i<n;i++){w[0]=i;if(!exec_inst(push,b,2,w)){ok=0;break;}}
    if(ok){for(uint64_t i=0;i<n;i++){if(!exec_inst(pop,b,2,w)){ok=0;break;}x^=w[2];}}
    const double elapsed=now_ms()-t0;free(storage);
    return (RunResult){elapsed,x,ok};
}

static RunResult run_sorted(uint64_t total_ops, int set_mode)
{
    const uint64_t n=total_ops/2;
    const uint64_t cap=n?n:1;
    uint64_t *keys=calloc((size_t)cap,sizeof(uint64_t));
    uint64_t *values=set_mode?NULL:calloc((size_t)cap,sizeof(uint64_t));
    if(!keys || (!set_mode && !values)){free(keys);free(values);return (RunResult){0,0,0};}

    uint64_t cursor=0,count=0,gen=0,flags=0,w[8]={0};
    JxJxlContainerBinding b[2];uint8_t put[6],get[6];
    void *put_fn=set_mode?(void *)jx_set_add_u64:(void *)jx_map_put_u64;
    void *get_fn=set_mode?(void *)jx_set_has_u64:(void *)jx_map_get_u64;
    init_binding(&b[0],put_fn,keys,&cursor,&count,cap,0,&gen,&flags,values);
    init_binding(&b[1],get_fn,keys,&cursor,&count,cap,0,&gen,&flags,values);
    make_inst(put,set_mode?OP_EMPLACE:OP_PUT,0,0,1,set_mode?2:SEL_UNUSED);
    make_inst(get,set_mode?OP_HAS:OP_GET,1,0,SEL_UNUSED,2);

    uint64_t x=0;int ok=1;const double t0=now_ms();
    for(uint64_t i=0;i<n;i++){
        w[0]=i;w[1]=i;
        if(!exec_inst(put,b,2,w)){ok=0;break;}
    }
    if(ok){
        for(uint64_t i=0;i<n;i++){
            w[0]=i;
            if(!exec_inst(get,b,2,w)){ok=0;break;}
            x^=w[2];
        }
    }
    const double elapsed=now_ms()-t0;
    free(values);free(keys);
    return (RunResult){elapsed,x,ok};
}

/* Verify the array law before timing it: ordered insertion, Map overwrite,
 * Set uniqueness, lookup and removal. This intentionally uses the JXL executor
 * instead of directly calling the assembly primitive.
 */
static int verify_sorted_map_set(void)
{
    uint64_t map_keys[8]={0},map_values[8]={0},map_cursor=0,map_count=0,gen=0,flags=0,w[8]={0};
    JxJxlContainerBinding mb[4];
    uint8_t mput[6],mget[6],mhas[6],mremove[6];
    init_binding(&mb[0],(void *)jx_map_put_u64,map_keys,&map_cursor,&map_count,8,0,&gen,&flags,map_values);
    init_binding(&mb[1],(void *)jx_map_get_u64,map_keys,&map_cursor,&map_count,8,0,&gen,&flags,map_values);
    init_binding(&mb[2],(void *)jx_map_has_u64,map_keys,&map_cursor,&map_count,8,0,&gen,&flags,map_values);
    init_binding(&mb[3],(void *)jx_map_remove_u64,map_keys,&map_cursor,&map_count,8,0,&gen,&flags,map_values);
    make_inst(mput,OP_PUT,0,0,1,SEL_UNUSED);
    make_inst(mget,OP_GET,1,0,SEL_UNUSED,2);
    make_inst(mhas,OP_HAS,2,0,SEL_UNUSED,2);
    make_inst(mremove,OP_REMOVE,3,0,SEL_UNUSED,2);

    w[0]=4;w[1]=40;if(!exec_inst(mput,mb,4,w))return 0;
    w[0]=2;w[1]=20;if(!exec_inst(mput,mb,4,w))return 0;
    w[0]=4;w[1]=99;if(!exec_inst(mput,mb,4,w))return 0;
    if(map_count!=2 || map_keys[0]!=2 || map_keys[1]!=4 || map_values[0]!=20 || map_values[1]!=99)return 0;
    w[0]=4;if(!exec_inst(mget,mb,4,w) || w[2]!=99)return 0;
    w[0]=3;if(!exec_inst(mhas,mb,4,w) || w[2]!=0)return 0;
    w[0]=2;if(!exec_inst(mremove,mb,4,w) || w[2]!=1)return 0;
    if(map_count!=1 || map_keys[0]!=4 || map_values[0]!=99)return 0;

    uint64_t set_keys[8]={0},set_cursor=0,set_count=0,sw[8]={0};
    JxJxlContainerBinding sb[3];
    uint8_t sadd[6],shas[6],sremove[6];
    init_binding(&sb[0],(void *)jx_set_add_u64,set_keys,&set_cursor,&set_count,8,0,&gen,&flags,NULL);
    init_binding(&sb[1],(void *)jx_set_has_u64,set_keys,&set_cursor,&set_count,8,0,&gen,&flags,NULL);
    init_binding(&sb[2],(void *)jx_set_remove_u64,set_keys,&set_cursor,&set_count,8,0,&gen,&flags,NULL);
    make_inst(sadd,OP_EMPLACE,0,0,1,2);
    make_inst(shas,OP_HAS,1,0,SEL_UNUSED,2);
    make_inst(sremove,OP_REMOVE,2,0,SEL_UNUSED,2);

    sw[0]=5;sw[1]=5;if(!exec_inst(sadd,sb,3,sw))return 0;
    sw[0]=1;sw[1]=1;if(!exec_inst(sadd,sb,3,sw))return 0;
    sw[0]=5;sw[1]=5;if(!exec_inst(sadd,sb,3,sw))return 0;
    sw[0]=3;sw[1]=3;if(!exec_inst(sadd,sb,3,sw))return 0;
    if(set_count!=3 || set_keys[0]!=1 || set_keys[1]!=3 || set_keys[2]!=5)return 0;
    sw[0]=3;if(!exec_inst(shas,sb,3,sw) || sw[2]!=1)return 0;
    sw[0]=1;if(!exec_inst(sremove,sb,3,sw) || sw[2]!=1)return 0;
    if(set_count!=2 || set_keys[0]!=3 || set_keys[1]!=5)return 0;
    return 1;
}

typedef RunResult (*Runner)(uint64_t);
static RunResult vector_runner(uint64_t ops){return run_vector(ops,0);}
static RunResult stack_runner(uint64_t ops){return run_vector(ops,1);}
static RunResult queue_runner(uint64_t ops){return run_ring(ops,0);}
static RunResult deque_runner(uint64_t ops){return run_ring(ops,1);}
static RunResult map_runner(uint64_t ops){return run_sorted(ops,0);}
static RunResult set_runner(uint64_t ops){return run_sorted(ops,1);}

static Stats bench(Runner fn,uint64_t ops,int reps,int warmups)
{
    for(int i=0;i<warmups;i++){
        RunResult r=fn(ops);if(!r.ok){fprintf(stderr,"native JXL warmup failed\n");exit(3);}
    }
    double *times=calloc((size_t)reps,sizeof(double));if(!times)exit(4);
    uint64_t checksum=0;int have=0;
    for(int i=0;i<reps;i++){
        RunResult r=fn(ops);if(!r.ok){fprintf(stderr,"native JXL benchmark operation failed\n");exit(5);}
        times[i]=r.ms;
        if(!have){checksum=r.checksum;have=1;}
        else if(checksum!=r.checksum){fprintf(stderr,"native JXL checksum changed between repetitions\n");exit(6);}
    }
    qsort(times,(size_t)reps,sizeof(double),cmp_double);
    const double median=(reps&1)?times[reps/2]:(times[reps/2-1]+times[reps/2])/2.0;
    int p95i=(int)((reps*95+99)/100)-1;if(p95i<0)p95i=0;if(p95i>=reps)p95i=reps-1;
    Stats s={median,times[0],times[p95i],0,0,checksum};
    const double seconds=median/1000.0;
    s.mops_s=seconds>0?((double)ops/seconds)/1e6:0;
    s.ns_op=ops?median*1e6/(double)ops:0;
    free(times);return s;
}

static void print_metric(const char *name,Stats s,int comma)
{
    printf("\"%s\":{\"median_ms\":%.9f,\"min_ms\":%.9f,\"p95_ms\":%.9f,\"mops_s\":%.9f,\"ns_op\":%.9f,\"checksum\":%" PRIu64 "}%s",
           name,s.median_ms,s.min_ms,s.p95_ms,s.mops_s,s.ns_op,s.checksum,comma?",":"");
}

int main(int argc,char **argv)
{
    uint64_t ops=argc>1?strtoull(argv[1],NULL,10):100000;
    int reps=argc>2?atoi(argv[2]):9;
    int warmups=argc>3?atoi(argv[3]):2;
    if(ops<2 || (ops&1u) || reps<1 || warmups<0){
        fprintf(stderr,"usage: %s EVEN_TOTAL_OPS [REPS] [WARMUPS]\n",argv[0]);return 2;
    }
    if(!verify_sorted_map_set()){
        fprintf(stderr,"native JXL ordered Map/Set verification failed\n");
        return 7;
    }

    Stats record=bench(run_record,ops,reps,warmups);
    Stats vector=bench(vector_runner,ops,reps,warmups);
    Stats stack=bench(stack_runner,ops,reps,warmups);
    Stats queue=bench(queue_runner,ops,reps,warmups);
    Stats deque=bench(deque_runner,ops,reps,warmups);
    Stats map=bench(map_runner,ops,reps,warmups);
    Stats set=bench(set_runner,ops,reps,warmups);

    printf("{\"suite\":\"jxl-native-containers/1\",\"path\":\"prepared-6-byte-executor\",\"map_set_layout\":\"ordered-arrays\",\"ops\":%" PRIu64 ",\"reps\":%d,\"warmups\":%d,\"native\":{",ops,reps,warmups);
    print_metric("record",record,1);print_metric("vector",vector,1);print_metric("stack",stack,1);
    print_metric("queue",queue,1);print_metric("deque",deque,1);print_metric("map",map,1);print_metric("set",set,0);
    printf("},\"vm\":{}}\n");
    return 0;
}
