# OSAura operating-system handoff

OSAura is the standalone operating-system repository for the JX runtime.

- JX repository: `dompipe/jx`
- OSAura repository: `dompipe/OSAura`

JX remains the canonical language, compiler, runtime semantics, Bags, `.64B` format, applied bytecodes, security semantics, task/channel abstractions, and host-neutral execution machinery. OSAura owns the standalone kernel, UEFI/USB boot path, interrupt/scheduler implementation, memory management, device drivers, terminal, storage/network services, and eventually the JX11 graphical environment.

## Portability contract

Host-neutral JX behavior used by OSAura must remain separable from Windows/Linux-specific adapters. In particular, the following are part of the portable JX/OSAura contract:

- canonical Bag/container semantics;
- `.64B` executable/package format;
- applied bytecode ABI, including the current BUS system entries;
- task, channel, hot-generation, rollback, and execution-branch semantics;
- multiplexed logical bus behavior and logical bus clock policy;
- SECURITY domain semantics;
- JX Security signature representation, MD5/SHA-1/SHA-256 matching, and supported hash-signature import rules;
- compact 1-2 byte prepared/result references;
- native/prepared acceleration that preserves canonical source and checkpoint behavior.

OS-specific wake mechanisms are adapters, not semantic ABI. Linux futexes and Win32 named-event/shared-memory implementations are reference hosts; OSAura will provide its own interrupt/event/scheduler implementation while preserving the same externally visible JX semantics.

## Architectural split

```text
JX source / .64B
        |
        v
JX language + runtime ABI
        |
        +-----------------------+
        |                       |
        v                       v
Windows/Linux hosts          OSAura
host adapters                kernel-native adapters
        |                       |
        v                       v
Windows/Linux kernel         OSAura kernel
```

JX11 belongs above OSAura's kernel/runtime boundary. The terminal-first OSAura system should remain usable when JX11 is absent or stopped.

## Migration rule

Do not solve OSAura requirements by weakening canonical JX semantics. If OSAura needs a faster implementation, add or improve a prepared/native lowering while keeping canonical JX readable and authoritative.

When a JX change affects any portable ABI listed above, assess whether the corresponding OSAura snapshot/adapter must be updated.
