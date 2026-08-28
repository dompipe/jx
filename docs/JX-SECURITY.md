# JX Security

JX Security is the native malware-inspection service for JX. It is inspired by the role of tools such as phpMussel, but it is implemented independently around JX Bags, compact applied codes, and the multiplexed runtime bus.

## Architectural law

> Canonical signatures are readable. Prepared signatures do the work. The bus moves ownership and result references, not file payloads.

A file, download, archive entry, or executable remains in caller-owned or mapped storage. The scanner reads that storage in place and writes a compact result into a prepared result slot. The SECURITY logical bus carries only the 1- or 2-byte result-slot code.

```text
object storage / Bag / mapped file
          |
          v
   native scan engine
          |
          v
 prepared result slot
          |
          v
 SECURITY DATA bit + 1-2 byte code
          |
          v
 admission / quarantine / report
```

## Multiplexed domain

`JX_IDLE_DOMAIN_SECURITY` is independent from CORE and WINDOW. SECURITY is armed only when inspection work exists. A window cannot hold CORE open, and a scan batch does not need to exist on idle epochs.

The domain uses the same fixed-position bitstring discipline as the other buses. Program position, not reply timing, defines collection order.

## Verdicts

The first ABI defines four two-bit verdicts:

| Bits | Verdict | Meaning |
|---|---|---|
| `00` | CLEAN | no configured signature matched |
| `01` | SUSPICIOUS | policy/review match |
| `10` | MALWARE | blocking malware match |
| `11` | ERROR | scanner/signature failure |

## Compact result references

Direct result slots use one byte:

```text
vvssssss
```

- `vv`: verdict
- `ssssss`: result slot 0..63

Extended result slots use two bytes:

```text
vvssssssssssssss
```

- `vv`: verdict
- remaining 14 bits: result slot 0..16383

The code does not contain the file, signature text, path, or report. It identifies the prepared result which already exists in JX-owned state.

## Canonical signature Bag

The intended source representation is readable JX data, for example:

```jx
bag MalwareSignatures {
    type: vector
    of: SecuritySignature
}

SecuritySignature {
    id: 12014
    name: "Example.Byte.Pattern"
    type: bytes
    verdict: malware
    offset: any
    bytes: "DE AD BE EF"
}
```

A masked pattern remains explicit:

```jx
SecuritySignature {
    id: 12015
    name: "Example.Masked.Pattern"
    type: bytes-masked
    verdict: suspicious
    offset: any
    bytes: "41 42 00 44"
    mask:  "FF FF 00 FF"
}
```

Canonical source is never replaced by native tables. At load/prelink time JX may prepare faster match structures from those signatures.

## Current native matcher

Version 1 supports:

- exact byte signatures at a fixed offset;
- exact byte signatures anywhere in an object;
- masked byte signatures with byte-level wildcards;
- deterministic highest-severity result selection;
- zero-copy scanning of caller-owned buffers;
- compact SECURITY result-slot encoding;
- ordered SECURITY-domain collection.

The scanner currently performs straightforward matching. Future prepared generations can replace the search implementation with hash tables, direct offset groups, multi-pattern automatons, SIMD/native scanning, or other optimized structures without changing canonical signature syntax.

## Admission path

A later execution boundary can use:

```text
jx.exe / executable candidate
        |
        v
SECURITY scan
        |
        +-- CLEAN ------> authority checks -> execute
        +-- SUSPICIOUS -> policy/review
        +-- MALWARE ----> deny/quarantine
        `-- ERROR ------> fail closed where policy requires
```

The scanner result does not itself grant execution permission. Security scanning and authorization remain separate checks.

## Planned compatibility layers

The implementation should grow by adapters, not by copying third-party engines:

1. canonical JX signature files;
2. hash signatures (SHA-256 first);
3. ClamAV-compatible signature import where licensing/format requirements permit;
4. archive-entry inspection with bounded recursion and decompression limits;
5. MIME/file-type rules;
6. URL/download admission;
7. executable launch admission;
8. quarantine Bag and signed scan provenance;
9. signature-generation hot swap through the existing JX generation model.

A compatibility importer converts external formats into canonical JX signatures. Prepared/native generations are then produced from that canonical representation.

## Security constraints

- Treat scanned data and signature files as untrusted input.
- Bound pattern lengths, archive recursion, expanded bytes, and object counts.
- Do not execute scanned content.
- Do not treat a numeric bus/result reference as authorization.
- Preserve canonical provenance for every blocking result.
- A signature-generation swap is preparation until explicitly committed by the runtime authority.
