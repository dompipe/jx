# JX SSH Live Patch

JX uses SSH as a secure carrier for native live updates, not as the authorization mechanism and not as a general remote shell.

Core rule:

> SSH authenticates and protects the channel. The JX patch signature authorizes the patch. The base generation/hash proves applicability. A safe-point generation swap activates it.

## Narrow protocol

The SSH-side protocol is intentionally non-shell-shaped and accepts only:

```text
JX-PATCH/1 STATUS
JX-PATCH/1 ROLLBACK
JX-PATCH/1 PUSH <manifest-bytes> <signature-bytes> <patch-bytes>
```

After `PUSH`, the manifest, signature, and patch body follow as bounded raw byte sequences with the declared lengths.

There is no quoting, command substitution, shell expansion, pathname expansion, pipe, redirection, or arbitrary command verb. The parser rejects shell metacharacters and extra arguments.

The three operations have separate authorization bits:

```text
status
push
rollback
```

A key/account intended only to inspect patch state therefore does not need push or rollback authority.

## OpenSSH deployment

A production host should dedicate a key or account to the JX patch service and constrain it with OpenSSH controls. The intended shape is a `ForceCommand` or dedicated subsystem that runs only the JX patch receiver. Disable unrelated SSH facilities for that identity, including PTY allocation and forwarding features unless a deployment explicitly requires them.

Conceptually:

```text
SSH authenticated connection
        ↓
forced JX patch receiver
        ↓
strict JX-PATCH/1 parser
        ↓
operation capability check
        ↓
manifest/signature/body framing
        ↓
jx_patch_validate()
        ↓
stage prepared generation
        ↓
JX11 quiescent event boundary
        ↓
atomic generation swap
```

The receiver must never reinterpret a JX patch request as a shell command.

## Independent patch verification

A successful SSH login is not sufficient to activate a patch. `jx_patch_validate()` independently requires:

- supported patch protocol/version
- a non-null signature verifier and signature
- exact base generation
- exact base SHA-256 digest
- strictly newer target generation
- fresh timestamp and bounded expiry
- monotonic nonce/replay protection
- permitted patch capability mask
- bounded patch length
- a nonzero, changed target digest
- successful patch signature verification

The patch body should then be independently materialized/validated against the declared target digest before staging.

## No secrets in `.64B`

SSH private keys, API secrets, TLS private keys, and patch-signing private keys do not belong in `.64B` Books. Use the OS keychain, `ssh-agent`, hardware-backed credentials, or an operator trust store. `.64B` may carry public identities, capability declarations, and signed/checksummed metadata.

## Safe-point activation

JX11 does not overwrite instructions that may currently be executing. It maintains prepared generations:

```text
active
pending
previous
```

A valid incoming patch prepares `pending`. The active generation remains untouched while staging. At the JX11 event-batch/quiescent boundary, the manager swaps `pending` into `active` and retains the old generation as `previous` for rollback.

This gives the native desktop the desired no-reboot/no-restart behavior without treating arbitrary writable code memory as the update API.

## Security law

> Hot addresses answer **where**. Capabilities answer **whether**. SSH answers **how it arrived**. The patch signature answers **who authorized this exact change**.

These are deliberately separate checks.
