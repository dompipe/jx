$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

function Invoke-Step {
    param(
        [Parameter(Mandatory=$true)][string] $Name,
        [Parameter(Mandatory=$true)][string] $Exe,
        [Parameter(Mandatory=$true)][string[]] $CommandArgs
    )

    Write-Host $Name
    Write-Host "  $Exe $($CommandArgs -join ' ')"
    & $Exe @CommandArgs
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

Invoke-Step -Name 'Building native Windows JX compiler...' -Exe $CCompiler -CommandArgs @(
    '-O2',
    '-Wall',
    '-Wextra',
    '-o',
    'build/jx-native.exe',
    'src/native/jx.c'
)

Invoke-Step -Name 'Emitting page C bundle...' -Exe '.\build\jx-native.exe' -CommandArgs @(
    'emit-c',
    'examples/page.php',
    '--asset',
    'examples/style.css',
    '-o',
    'build/page.c'
)

Invoke-Step -Name 'Building Windows page executable...' -Exe $CCompiler -CommandArgs @(
    '-O2',
    '-Wall',
    '-Wextra',
    '-o',
    'build/page.exe',
    'build/page.c'
)

Invoke-Step -Name 'Building Windows window runner...' -Exe $CCompiler -CommandArgs @(
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
