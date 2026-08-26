# xi — XIP Book Server

Institutional **book**-shaped website sections with embedded PHP, bags/channels, and **no JS courier**.

```bash
cd pasl/xi
make test
make foreground          # http://127.0.0.1:8765/
make start / stop / status
make drop                # JSON into cover inbox
make docker-build && make docker-run
```

```bash
php xi.php localhost:8765 start config.json
php xi.php localhost:8765 stop
```

## Layout

- **Books** = site sections (`books/cover`, `books/account`)
- **Leaves** = pages (state-ready or normalized)
- **Binding** = spine, cursor, history, channels
- **XIP** = form + protocol + page segments
- **Drops** = JSON inbox → channel (next interaction, no refresh loop)
- **Tables** = isolated Y-axis channels (iframe-like for devs)
- **Windows** = Book surfaces hosted equivalently by browser, Win32, or X11
- **PASL leaves** = compile to PASM and execute in the browser host

Leaf metadata may name a PASL program and Book metadata may declare portable window bounds:

```json
{
  "window": { "id": "main", "width": 960, "height": 720 },
  "leaves": { "home": { "pasl": "programs/home.pasl" } }
}
```

Browser PASL results and native host events use the versioned JSON format in `../host/drop.schema.json`.

## XipEngine source

`XipEngine.php` assembles from `XipEngine.h1.php` + `XipEngine.h2.php` on first require (writes `XipEngine.assembled.php`). This keeps the class portable across tooling size limits while remaining pure PHP.

## Docs

Generate the books PDF locally:

```bash
pip install reportlab
python3 docs/build_pdf.py
```

See also the design agreement in the conversation / local `docs/XIP_Books_Guide.pdf`.
