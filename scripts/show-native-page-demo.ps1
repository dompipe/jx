$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

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

function Invoke-Step {
    param(
        [string] $Name,
        [string] $Exe,
        [string[]] $CommandArgs
    )

    Write-Host $Name
    Write-Host "  $Exe $($CommandArgs -join ' ')"
    & $Exe @CommandArgs
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
}

if (-not (Test-Path 'build')) {
    New-Item -ItemType Directory -Path 'build' | Out-Null
}

$CCompiler = Find-CommandPath @('gcc', 'x86_64-w64-mingw32-gcc', 'cc')
if (-not $CCompiler) {
    Write-Error 'No C compiler found. Install MSYS2 GCC and make sure C:\msys64\ucrt64\bin is on PATH.'
    exit 1
}

Write-Host "Using C compiler: $CCompiler"

Invoke-Step -Name 'Building native page window demo...' -Exe $CCompiler -CommandArgs @(
    '-O2',
    '-Wall',
    '-Wextra',
    '-o',
    'build/jx-page-win32.exe',
    'src/window/jx-page-win32.c',
    'src/runtime/jx_css_runtime.c',
    '-lgdi32',
    '-luser32'
)

Write-Host 'Opening native page window...'
& '.\build\jx-page-win32.exe' 'examples/style.css'
