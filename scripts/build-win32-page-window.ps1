$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

function Invoke-Step {
    param(
        [string] $Name,
        [string] $Exe,
        [string[]] $Args
    )

    Write-Host $Name
    & $Exe @Args
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
}

function Find-CommandPath {
    param([string[]] $Names)

    foreach ($Name in $Names) {
        $Command = Get-Command $Name -ErrorAction SilentlyContinue
        if ($Command) {
            return $Command.Source
        }
    }

    return $null
}

if (-not (Test-Path 'build')) {
    New-Item -ItemType Directory -Path 'build' | Out-Null
}

$CCompiler = Find-CommandPath @('x86_64-w64-mingw32-gcc', 'gcc', 'cc')
if (-not $CCompiler) {
    Write-Error 'No C compiler found. Install MinGW-w64 or make gcc available in PATH.'
    exit 1
}

Write-Host "Using C compiler: $CCompiler"

Invoke-Step 'Building native Windows JX compiler...' $CCompiler @(
    '-O2',
    '-Wall',
    '-Wextra',
    '-o',
    'build/jx-native.exe',
    'src/native/jx.c'
)

Invoke-Step 'Emitting page C bundle...' '.\build\jx-native.exe' @(
    'emit-c',
    'examples/page.php',
    '--asset',
    'examples/style.css',
    '-o',
    'build/page.c'
)

Invoke-Step 'Building Windows page executable...' $CCompiler @(
    '-O2',
    '-Wall',
    '-Wextra',
    '-o',
    'build/page.exe',
    'build/page.c'
)

Invoke-Step 'Building Windows window runner...' $CCompiler @(
    '-O2',
    '-Wall',
    '-Wextra',
    '-o',
    'build/jx-window-win32.exe',
    'src/window/jx-window-win32.c',
    '-lgdi32',
    '-luser32'
)

Write-Host 'PASS: built build/jx-native.exe, build/page.exe, and build/jx-window-win32.exe'
Write-Host 'Run:'
Write-Host '.\build\jx-window-win32.exe .\build\page.exe world'
