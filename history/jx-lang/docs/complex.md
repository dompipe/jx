# Complex Numbers

Complex numbers are first-class in jx.

## Literals and Construction

```jx
c1 = 3 + 4i
c2 = complex(3, 4)
c3 = 2.5 - 1.25i
```

## Operations

- Arithmetic: `+`, `-`, `*`, `/`
- Conjugate: `c.conj` or `conj(c)`
- Polar: `c.mag`, `c.arg`, `from_polar(r, theta)`
- Components: `c.re`, `c.im`

## Native Representation

Preferred layout (platform-dependent, chosen by smart table):

- Two consecutive floats / doubles (re, im) in registers or stack slots when possible.
- Or a small struct `{ re: f64, im: f64 }` with known ABI.

The smart table entries for complex arithmetic carry both a pure native_template (SIMD or scalar) and a Resistant scalar fallback.

## Const and Delivery

```jx
const origin = 0 + 0i
val = transform.matrix.scale.delivery()   // may be complex
```

Complex values obey the same `const` and memory rules as any other data.
