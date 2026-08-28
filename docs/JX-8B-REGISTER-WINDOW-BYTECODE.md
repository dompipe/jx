# JX `.8B` Register-Window Bytecode

## Purpose

JX `.8B` is a compact executable bytecode format for programs that need a large logical register set while keeping hot executable instructions exactly one byte wide.

The design preserves JX ABI v4:

```text
1xxxxxxx                  -> HOT / exactly 1 byte
0xxxxxxx xxxxxxxx         -> EXTENDED / exactly 2 bytes
```

`.8B` does **not** widen the hot byte to carry a register number. Instead, code blocks are prelinked to register windows. Each window contains eight full 8-bit register IDs.

This gives JX up to 256 directly named registers while retaining the existing three-bit local selector and one-byte hot bytecode.

## Core idea

```text
one-byte hot instruction
        |
        +-- bank:4
        +-- shadow:3
                 |
          prepared binding
                 |
          active register window
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
 -> one-byte opcode
 -> prepared executor
 -> already resolved register
```

The window is therefore primarily a **file/prelink concept**, not a tax paid on every operation.

## Code blocks

`.8B` code is divided into blocks. A block descriptor identifies:

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

Changing blocks can therefore change the eight locally visible registers without widening any hot instruction.

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

`.8B` instead records block/window association in file metadata. During load/prelink, the host or OSAura runtime prepares each block with the correct register window before execution.

The common hot loop therefore contains no page-switch instruction.

## File identity

Proposed standalone extension:

```text
program.8B
```

Magic:

```text
JX8B0001
```

`.8B` may also be embedded as a section inside a normal `.64B` Book. `.64B` remains the broader compiled Book/container format; `.8B` is the compact register-windowed executable stream.

## Proposed file layout

```text
JX8B header
register-window table
block table
prepared-binding table
one-byte/two-byte code stream
optional constants
optional debug/canonical map
```

Suggested fixed header:

```c
typedef struct {
    uint8_t magic[8];          // "JX8B0001"
    uint16_t version;          // 1
    uint16_t flags;
    uint16_t window_count;     // 1..32
    uint16_t block_count;
    uint32_t prepared_count;
    uint32_t code_bytes;
    uint32_t window_offset;
    uint32_t block_offset;
    uint32_t prepared_offset;
    uint32_t code_offset;
} jx8b_header;
```

All multi-byte file fields use little endian in v1.

## Prepared binding

The prepared table is where `.8B` turns many logical registers into a one-byte runtime path.

Suggested v1 binding:

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

`register0` and `register1` are the already-resolved full 8-bit register IDs. `local0/local1` are retained for verification/debugging and can be omitted from a future stripped release representation if profiling proves that worthwhile.

A loader verifies:

```text
window[block.window][local0] == register0
window[block.window][local1] == register1
```

and then prelinks directly to the register storage.

## Runtime register file

The canonical v1 logical register file is:

```c
uint64_t reg[256];
```

The **register ID is 8-bit**; the register value does not have to be 8-bit.

This distinction is important:

```text
8-bit register ID -> 256 addressable registers
register contents -> native JX value width
```

A future typed register file can attach type metadata without changing the bytecode width.

## One-byte bytecodes

The existing ABI v4 hot byte remains authoritative:

```text
bit 7      = 1
bits 6..3  = hot bank 0..15
bits 2..0  = shadow 0..7
```

The shadow still identifies one of eight prelinked operations/slots in that bank. `.8B` extends what those prepared operations can point at; it does not reinterpret the ABI byte as a raw register operand.

This preserves compatibility with OSAura's `0x80..0xFF` one-byte hot decoder.

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

Hot code remains a stream such as:

```text
83 91 A6 C0 87
```

Every byte is still one instruction. The prepared bindings already know which full register(s) each operation touches.

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
 -> emit `.8B`
```

If a working set exceeds eight simultaneously hot values, the compiler may:

1. split the region into multiple prepared blocks,
2. bind some operands explicitly through prepared metadata,
3. spill cold values to Bags/containers,
4. use an extended operation when a truly dynamic register selection is required.

The compiler should choose whichever minimizes repeated work.

## Relationship to Bags

`.8B` does not replace Bags.

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

.8B
  compact executable/register-window section
  256 logical register IDs
  one-byte hot stream
```

A `.64B` Book may carry multiple `.8B` programs or generations.

## Admission verification

An `.8B` loader must reject:

- invalid magic/version,
- more than 32 windows in v1,
- window/block/table ranges outside the file,
- blocks outside the code section,
- local selectors above 7,
- register IDs outside 0..255,
- high-bit hot opcodes whose prepared binding is missing,
- malformed two-byte extended instructions,
- prepared register IDs that do not agree with the selected window,
- overlapping or contradictory block descriptors unless explicitly permitted by a future version.

## Host parity

The exact same `.8B` file must be accepted by:

```text
native OSAura
Windows command-line OSAura emulator
jx.exe native host
```

The backend mechanism can differ; register/window semantics cannot.

## Future optimization

After profiling, a loaded `.8B` block can be transformed internally into a direct executor array:

```text
byte opcode
 -> executor[opcode & 0x7f]
 -> prelinked register pointer(s)
 -> native operation
```

No register-window lookup remains in the repeat path.

That is the central reason for this file type: **increase register reach without making the bytecode wider.**
