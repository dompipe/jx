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

The ABI defines four two-bit verdicts:

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

Whole-file hashes remain equally readable:

```jx
SecuritySignature {
    id: 12016
    name: "Example.SHA256"
    type: hash
    verdict: malware
    hash: sha256
    size: 12345
    digest: "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef"
}
```

Canonical source is never replaced by native tables. At load/prelink time JX may prepare faster match structures from those signatures.

## Current native matcher: version 2

Version 2 supports:

- exact byte signatures at a fixed offset;
- exact byte signatures anywhere in an object;
- masked byte signatures with byte-level wildcards;
- whole-file MD5 signatures;
- whole-file SHA-1 signatures;
- whole-file SHA-256 signatures;
- exact or wildcard file-size constraints for imported hash rules;
- deterministic highest-severity result selection;
- zero-copy scanning of caller-owned buffers;
- compact SECURITY result-slot encoding;
- ordered SECURITY-domain collection.

Digest computation is lazy. During one object scan, MD5, SHA-1, or SHA-256 is calculated only if a configured rule needs it, and each required algorithm is calculated at most once for that object regardless of how many rules use the same digest family.

The byte matcher currently performs straightforward matching. Future prepared generations can replace the search implementation with hash tables, direct offset groups, multi-pattern automatons, SIMD/native scanning, or other optimized structures without changing canonical signature syntax.

## ClamAV and phpMussel whole-file hash import

JX currently provides **whole-file hash format compatibility**, not complete engine compatibility.

The importer accepts the common form:

```text
HASH:FILESIZE:NAME
```

and the optional numeric fourth field used by ClamAV hash databases:

```text
HASH:FILESIZE:NAME:73
```

`FILESIZE` may be a decimal byte count or `*` for any size. The hash algorithm is determined without an extra tag:

| Hex characters | Algorithm |
|---:|---|
| 32 | MD5 |
| 40 | SHA-1 |
| 64 | SHA-256 |

When the hash importer is used on a phpMussel Hash signature file, a leading framing/header line beginning with `phpMussel` is skipped before signature records are parsed.

Imported records are normalized into ordinary `jx_security_signature` objects with deterministic JX IDs. JX does not execute third-party scanner source or keep external rule syntax in the hot scan path.

Malformed records fail closed at import time: invalid hexadecimal digests, unsupported digest lengths, invalid or overflowing sizes, overlong names, unexpected extra fields, and invalid numeric compatibility fields are rejected and counted in the import report rather than activated as signatures. Capacity exhaustion and JX signature-ID overflow are hard importer errors.

### Compatibility boundary

The current importer does **not** claim complete phpMussel or ClamAV compatibility. It does not yet import:

- phpMussel Standard, Regex, Normalised, HTML, PE, Complex Extended, or URL rules;
- ClamAV NDB, LDB, YARA, bytecode, PE-section, or other structural signature databases;
- archive recursion or embedded-object rules.

Those formats require their own explicit adapters and tests. Unsupported rule types must never be silently interpreted as simpler hash rules.

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

## Compatibility and growth path

Completed foundations:

1. canonical JX byte/masked signatures;
2. native MD5, SHA-1, and SHA-256 whole-file matching;
3. ClamAV/phpMussel whole-file hash import into canonical JX records.

Next adapters should be added independently and tested by format:

1. body/hex signature import;
2. archive-entry inspection with bounded recursion and decompression limits;
3. MIME/file-type rules;
4. URL/download admission;
5. executable launch admission;
6. quarantine Bag and signed scan provenance;
7. signature-generation hot swap through the existing JX generation model.

A compatibility importer converts external formats into canonical JX signatures. Prepared/native generations are then produced from that canonical representation.

## Security constraints

- Treat scanned data and signature files as untrusted input.
- Bound pattern lengths, archive recursion, expanded bytes, and object counts.
- Do not execute scanned content.
- Do not treat a numeric bus/result reference as authorization.
- Preserve canonical provenance for every blocking result.
- A signature-generation swap is preparation until explicitly committed by the runtime authority.
