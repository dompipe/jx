#ifndef JX_JXL_CONTAINER_ADMISSION_H
#define JX_JXL_CONTAINER_ADMISSION_H

#include "jxl_container_runtime.h"

#include <stddef.h>
#include <stdint.h>

#ifdef __cplusplus
extern "C" {
#endif

typedef struct JxJxlContainerBindingSpec {
    uint16_t id;
    uint8_t discipline;
    uint8_t opcode;
    uint8_t width;
    uint64_t bag_handle;
    uint64_t capacity;
    uint64_t mask;
    uint16_t native_id;
    uint16_t flags;
} JxJxlContainerBindingSpec;

/* Resolve one durable Bag handle into its already-admitted runtime memory
 * pointers. The callback fills base/head/tail/generation/flags/aux/aux2.
 * Admission supplies capacity/mask/native_fn from the checked JXCBIND1 record.
 * Return zero to reject the binding.
 */
typedef int (*JxJxlContainerBagResolver)(
    const JxJxlContainerBindingSpec *spec,
    JxJxlContainerBinding *runtime,
    void *context
);

enum JxJxlContainerAdmissionStatus {
    JX_JXL_ADMIT_OK = 0,
    JX_JXL_ADMIT_BAD_HEADER = -1,
    JX_JXL_ADMIT_BAD_VERSION = -2,
    JX_JXL_ADMIT_BAD_LENGTH = -3,
    JX_JXL_ADMIT_OUTPUT_SMALL = -4,
    JX_JXL_ADMIT_BAD_RECORD = -5,
    JX_JXL_ADMIT_BAD_NATIVE_ID = -6,
    JX_JXL_ADMIT_BAG_REJECTED = -7,
};

/* Convert canonical serialized operation bindings into the runtime table once.
 * No symbol names are consulted: native_id indexes the assembly target table.
 * The output array is indexed by binding id and can be passed directly to the
 * resident JXL container stream executor.
 */
int jx_jxl_container_admit(
    const uint8_t *serialized,
    size_t serialized_bytes,
    JxJxlContainerBinding *runtime_out,
    size_t runtime_capacity,
    JxJxlContainerBagResolver resolver,
    void *resolver_context,
    size_t *binding_count_out
);

#ifdef __cplusplus
}
#endif

#endif
