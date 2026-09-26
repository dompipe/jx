#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

chmod +x ./jx
rm -rf build
mkdir -p build

compile_output="$(./jx compile examples/hello.php -o build/hello)"
if [[ "$compile_output" != "compiled examples/hello.php -> build/hello" ]]; then
  echo "unexpected compile output: $compile_output" >&2
  exit 1
fi

run_output="$(./build/hello world)"
if [[ "$run_output" != "Hello from JX, world" ]]; then
  echo "unexpected executable output: $run_output" >&2
  exit 1
fi

jx_run_output="$(./jx run examples/hello.php -- world)"
if [[ "$jx_run_output" != "Hello from JX, world" ]]; then
  echo "unexpected jx run output: $jx_run_output" >&2
  exit 1
fi

cat > build/exit.php <<'PHP'
<?php
fwrite(STDERR, "err-line\n");
exit(7);
PHP

./jx compile build/exit.php -o build/exit >/dev/null
set +e
./build/exit 2> build/exit.err
exit_code=$?
set -e
if [[ "$exit_code" -ne 7 ]]; then
  echo "expected exit code 7, got $exit_code" >&2
  exit 1
fi
if [[ "$(cat build/exit.err)" != "err-line" ]]; then
  echo "stderr was not preserved" >&2
  exit 1
fi

echo "PASS: JX compiles PHP into executable artifacts and preserves args, stdout, stderr, and exit code"
