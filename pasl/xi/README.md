# xi — XIP Book Server

Institutional **book**-shaped website sections with embedded PHP, bags/channels, and **no JS courier**.

```bash
make foreground          # http://127.0.0.1:8765/
make start / stop / status
make test
make drop                # JSON into cover inbox
make docker-build && make docker-run
```

```bash
php xi.php localhost:8765 start config.json
php xi.php localhost:8765 stop
```

- **Books** = site sections (`books/cover`, `books/account`)
- **Leaves** = pages (state-ready or normalized)
- **Binding** = spine, cursor, history, channels
- **XIP** = form + protocol + page segments
- **Drops** = JSON inbox → channel (next interaction, no refresh loop)
- **Tables** = isolated Y-axis channels (iframe-like for devs)

See **docs/XIP_Books_Guide.pdf** for the full design.
