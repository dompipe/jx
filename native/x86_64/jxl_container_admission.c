#include "jxl_container_admission.h"

#define JXCB_HEADER_BYTES 12u
#define JXCB_RECORD_BYTES 26u

static uint16_t u16le(const uint8_t *p)
{
    return (uint16_t)p[0] | ((uint16_t)p[1] << 8);
}

static uint32_t u32le(const uint8_t *p)
{
    return (uint32_t)p[0]
        | ((uint32_t)p[1] << 8)
        | ((uint32_t)p[2] << 16)
        | ((uint32_t)p[3] << 24);
}

static uint64_t u64le(const uint8_t *p)
{
    return (uint64_t)u32le(p) | ((uint64_t)u32le(p + 4) << 32);
}

static int header_matches(const uint8_t *p)
{
    static const uint8_t magic[8] = {'J','X','C','B','I','N','D','1'};
    for (size_t i = 0; i < sizeof(magic); i++) if (p[i] != magic[i]) return 0;
    return 1;
}

static int needs_power_of_two(uint8_t discipline)
{
    return discipline == 4 || discipline == 5 || discipline == 6 || discipline == 7;
}

static int is_power_of_two(uint64_t value)
{
    return value != 0 && (value & (value - 1)) == 0;
}

int jx_jxl_container_admit(
    const uint8_t *serialized,
    size_t serialized_bytes,
    JxJxlContainerBinding *runtime_out,
    size_t runtime_capacity,
    JxJxlContainerBagResolver resolver,
    void *resolver_context,
    size_t *binding_count_out
)
{
    if (binding_count_out != NULL) *binding_count_out = 0;
    if (serialized == NULL || serialized_bytes < JXCB_HEADER_BYTES || !header_matches(serialized)) {
        return JX_JXL_ADMIT_BAD_HEADER;
    }

    const uint16_t version = u16le(serialized + 8);
    const uint16_t count = u16le(serialized + 10);
    if (version != 1) return JX_JXL_ADMIT_BAD_VERSION;

    const size_t expected = JXCB_HEADER_BYTES + (size_t)count * JXCB_RECORD_BYTES;
    if (serialized_bytes != expected) return JX_JXL_ADMIT_BAD_LENGTH;
    if (runtime_out == NULL || runtime_capacity < count) return JX_JXL_ADMIT_OUTPUT_SMALL;
    if (resolver == NULL) return JX_JXL_ADMIT_BAG_REJECTED;

    const uint64_t native_count = jx_jxl_container_native_count;
    for (uint16_t row = 0; row < count; row++) {
        const uint8_t *p = serialized + JXCB_HEADER_BYTES + (size_t)row * JXCB_RECORD_BYTES;
        JxJxlContainerBindingSpec spec;
        spec.id = u16le(p + 0);
        spec.discipline = p[2];
        spec.opcode = p[3];
        spec.width = p[4];
        spec.bag_handle = u64le(p + 6);
        spec.capacity = u32le(p + 14);
        spec.mask = u32le(p + 18);
        spec.native_id = u16le(p + 22);
        spec.flags = u16le(p + 24);

        /* The canonical writer emits a dense id-indexed table. Keeping that law
         * at admission removes a relocation/search step from executable JXL.
         */
        if (spec.id != row || spec.discipline < 1 || spec.discipline > 7 || spec.width != 8) {
            return JX_JXL_ADMIT_BAD_RECORD;
        }
        if (spec.opcode < 0x40 || spec.opcode > 0x50) return JX_JXL_ADMIT_BAD_RECORD;
        if (needs_power_of_two(spec.discipline) && spec.capacity != 0) {
            if (!is_power_of_two(spec.capacity) || spec.mask != spec.capacity - 1) return JX_JXL_ADMIT_BAD_RECORD;
        }
        if (spec.native_id == 0 || spec.native_id > native_count || jx_jxl_container_native_table[spec.native_id] == NULL) {
            return JX_JXL_ADMIT_BAD_NATIVE_ID;
        }

        JxJxlContainerBinding *runtime = &runtime_out[spec.id];
        runtime->native_fn = NULL;
        runtime->base = NULL;
        runtime->head = NULL;
        runtime->tail = NULL;
        runtime->capacity = spec.capacity;
        runtime->mask = spec.mask;
        runtime->generation = NULL;
        runtime->flags = NULL;
        runtime->aux = NULL;
        runtime->aux2 = NULL;

        if (!resolver(&spec, runtime, resolver_context)) return JX_JXL_ADMIT_BAG_REJECTED;
        runtime->native_fn = jx_jxl_container_native_table[spec.native_id];
        runtime->capacity = spec.capacity;
        runtime->mask = spec.mask;
    }

    if (binding_count_out != NULL) *binding_count_out = count;
    return JX_JXL_ADMIT_OK;
}
