# JX `.8B` Register-Window Bytecode

> **Status note (JXL update):** the register-window and prepared-block ideas in this document remain useful design material, but its older statement that `.8B` directly uses the global JX ABI-v4 high-bit HOT grammar is **superseded for JXL**. The authoritative JXL stream law is now documented in [`JXL-PREPARED-EXECUTION.md`](JXL-PREPARED-EXECUTION.md): `0xxxxxxx` is an executable JXL opcode and `1xxxxxxx` is attached extension/data and is never independently dispatched. The global JX/OSAura Hot-Call ABI v4 remains a separate ABI with its own `1xxxxxxx = one-byte HOT` / `0xxxxxxx xxxxxxxx = two-byte EXTENDED` rule. Admission binds the appropriate decoder once. Do not conflate the two grammars.

## Purpose

JX `.8B` is a compact executable bytecode format for programs that need a large logical register set while keeping prepared executable operations compact.

The original register-window design preserved JX ABI v4 directly in the stream. **Current JXL keeps the register-window idea but uses the separate JXL byte law described above.** References below to direct ABI-v4 hot bytes should therefore be read as historical design rationale unless they are explicitly about calls from JXL into a prelinked global ABI-v4 service target.

JXL does **not** widen a compact operation merely to carry a full register number. Instead, code blocks are prelinked to register windows. Each window contains eight full 8-bit register IDs.

This gives JX up to 256 directly named logical registers while retaining an eight-entry local working set.

## Core idea

```text
prepared executable block
        |
        +-- active register window
                 |
          local selector 0..7
                 |
        8-bit register ID 0..255
```

The compiler resolves the expensive mapping once. The repeat path uses an already prepared pointer/table entry.

## Register windows

A register window is exactly eight bytes:

```c
uint8_t register_id[8];
```

Example:

```text
window 0
local 0 -> R0
local 1 -> R1
local 2 -> R2
local 3 -> R3
local 4 -> R4
local 5 -> R5
local 6 -> R6
local 7 -> R7

window 1
local 0 -> R8
local 1 -> R9
...
local 7 -> R15
```

With 32 canonical windows:

```text
32 windows * 8 registers = 256 register IDs
```

The file may store fewer than 32 windows when the program does not need all 256 registers.

## Critical performance rule

A hot instruction must not perform a runtime search for its register.

Forbidden repeat path:

```text
opcode
 -> decode register window number
 -> search window table
 -> resolve register
 -> operation
```

Required prepared path:

```text
cold load/prelink
 -> resolve block window
 -> resolve local selector to register pointer/index
 -> store prepared binding

repeat
 -> compact executable opcode
 -> prepared executor
 -> already resolved register
```

The window is therefore primarily a **file/prelink concept**, not a tax paid on every operation.

## Code blocks

`.8B`/JXL code is divided into blocks. A block descriptor identifies:

```c
typedef struct {
    uint32_t code_offset;
    uint32_t code_bytes;
    uint8_t register_window;
    uint8_t flags;
    uint16_t reserved;
} jx8b_block;
```

A block has one default register window. Prepared operations inside the block may additionally prelink explicit full register IDs from metadata when necessary.

Changing blocks can therefore change the eight locally visible registers without widening every executable operation.

## Why blocks instead of a runtime PAGE opcode

A runtime register-page switch would consume execution bandwidth:

```text
PAGE 4
ADD ...
ADD ...
PAGE 7
...
```

That contradicts the JX goal of removing repeated resolution work.

JXL instead records block/window association in file metadata. During load/prelink, the host or OSAura runtime prepares each block with the correct register window before execution.

The common hot loop therefore contains no page-switch instruction.

## File identity

Useful standalone/debug extension:

```text
program.8B
```

JXL may also use `.jxl` as an explicit prepared-stream name. The authoritative executable identity should ultimately be the format bytes/manifest, not merely the filename extension.

`.8B`/JXL may be embedded as a section inside a normal `.64B` Book. `.64B` remains the broader compiled Book/container format; JXL is the compact register-windowed executable stream.

## Historical proposed file layout

```text
JX8B header
register-window table
block table
prepared-binding table
compact code stream
optional constants
optional debug/canonical map
```

Any concrete v1 JXL header/table layout must agree with `JXL-PREPARED-EXECUTION.md` and its executable/attachment byte law before being treated as release ABI.

## Prepared binding

The prepared table is where JXL turns many logical registers into a compact runtime path.

A useful binding shape remains conceptually:

```c
typedef struct {
    uint8_t opcode;
    uint8_t block;
    uint8_t local0;
    uint8_t local1;
    uint8_t register0;
    uint8_t register1;
    uint8_t native_operation;
    uint8_t flags;
} jx8b_prepared;
```

`register0` and `register1` are the already-resolved full 8-bit register IDs. `local0/local1` can be retained for verification/debugging and omitted from a future stripped release representation if profiling proves that worthwhile.

A loader verifies:

```text
window[block.window][local0] == register0
window[block.window][local1] == register1
```

and then prelinks directly to the register storage.

## Runtime register file

The canonical v1 logical register file direction is:

```c
uint64_t reg[256];
```

The **register ID is 8-bit**; the register value does not have to be 8-bit.

```text
8-bit register ID -> 256 addressable registers
register contents -> native JX value width
```

A typed register file can attach type metadata without changing the compact ID width.

## JXL executable bytes

The current authoritative JXL law is:

```text
0xxxxxxx = executable JXL opcode
1xxxxxxx = attached extension/data byte; never opcode
```

A high-bit byte is valid only where the preceding prepared operation/block metadata declares attached data. An unattached high-bit byte is malformed.

The global Hot-Call ABI v4 remains separate:

```text
1bbbbsss                  = one-byte global HOT bank/shadow call
0fffffff ssssssss         = two-byte global EXTENDED family/slot call
```

JXL may invoke a prelinked global service target, but its own stream is decoded as JXL first.

## Example

Suppose a canonical method needs registers:

```text
R40 R41 R52 R53 R80 R81 R200 R201
```

The compiler emits a window:

```text
W7 = [40, 41, 52, 53, 80, 81, 200, 201]
```

The block descriptor selects `W7`.

During admission:

```text
local 0 -> reg[40]
local 1 -> reg[41]
local 2 -> reg[52]
local 3 -> reg[53]
local 4 -> reg[80]
local 5 -> reg[81]
local 6 -> reg[200]
local 7 -> reg[201]
```

The prepared JXL operations can then use local selectors/prepared bindings without searching the 256-register file on every operation.

## Canonical source rule

Programmers never manually manage windows in ordinary JX source.

Canonical JX remains readable. The compiler performs:

```text
canonical variables/register needs
 -> liveness analysis
 -> register allocation 0..255
 -> group hot working sets into windows of eight
 -> form code blocks
 -> prelink prepared bindings
 -> emit JXL
```

If a working set exceeds eight simultaneously hot values, the compiler may:

1. split the region into multiple prepared blocks,
2. bind some operands explicitly through prepared metadata,
3. spill cold values to Bags/containers,
4. use an extended prepared form when truly dynamic selection is required.

The compiler should choose whichever minimizes repeated work while preserving semantics.

## Relationship to Bags

JXL does not replace Bags.

```text
registers -> immediate hot working state
Bags      -> durable/structured semantic state
```

The intended model remains:

**Bags remember. Registers react. Prepared code executes.**

A Bag field that becomes hot can be cached/promoted into one of the 256 logical registers and placed into an eight-register window for a prepared block.

## Relationship to `.64B`

```text
.64B
  compiled Book/container
  generations
  Bags
  schemas
  reactions
  manifests
  one or more executable sections

JXL / .8B
  compact prepared executable/register-window section
  up to 256 logical register IDs
  eight-register local windows
```

A `.64B` Book may carry multiple JXL programs or generations.

## Admission verification

A JXL loader must reject, as applicable:

- invalid magic/version,
- too many windows for the declared format version,
- window/block/table ranges outside the file,
- blocks outside the code section,
- local selectors above 7,
- register IDs outside 0..255,
- unattached high-bit bytes,
- malformed or truncated declared attachments,
- prepared register IDs that do not agree with the selected window,
- invalid branch targets,
- overlapping or contradictory block descriptors unless explicitly permitted by a future version.

## Host parity

The exact same validated JXL section must have the same semantics under:

```text
native OSAura
Windows WSJX64 host
jx native host
```

The backend mechanism can differ; register/window/prepared semantics cannot.

## Future optimization

After profiling, a loaded JXL block can be transformed internally into a direct executor array containing already-resolved operation and register references.

The central reason for this format remains:

> **Increase logical register reach and preserve compiler decisions without making ordinary canonical JX carry the complexity.**
