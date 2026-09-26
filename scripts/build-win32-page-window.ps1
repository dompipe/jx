$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

if (-not (Test-Path 'build')) {
    New-Item -ItemType Directory -Path 'build' | Out-Null
}

Write-Host 'Building native JX compiler...'
& sh scripts/build-native-jx.sh
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host 'Emitting page C bundle...'
& .\jx emit-c examples/page.php --asset examples/style.css -o build/page.c
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host 'Building Windows page executable...'
& x86_64-w64-mingw32-gcc -O2 -Wall -Wextra -o build/page.exe build/page.c
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host 'Building Windows window runner...'
& x86_64-w64-mingw32-gcc -O2 -Wall -Wextra -o build/jx-window-win32.exe src/window/jx-window-win32.c -lgdi32 -luser32
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host 'PASS: built build/page.exe and build/jx-window-win32.exe'
Write-Host 'Run:'
Write-Host '.\build\jx-window-win32.exe .\build\page.exe world'
