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

$CCompiler = Find-CommandPath @('x86_64-w64-mingw32-gcc', 'gcc', 'cc')
if (-not $CCompiler) {
    Write-Error 'No C compiler found. Install MSYS2 MinGW-w64 GCC and add C:\msys64\ucrt64\bin to PATH.'
    exit 1
}

Write-Host "Using C compiler: $CCompiler"

Invoke-Step 'Building CSS runtime demo executable...' $CCompiler @(
    '-O2',
    '-Wall',
    '-Wextra',
    '-o',
    'build/css-runtime-demo.exe',
    'examples/css_runtime_demo.c',
    'src/runtime/jx_css_runtime.c'
)

Invoke-Step 'Building Win32 text window runner...' $CCompiler @(
    '-O2',
    '-Wall',
    '-Wextra',
    '-o',
    'build/jx-window-win32.exe',
    'src/window/jx-window-win32.c',
    '-lgdi32',
    '-luser32'
)

Write-Host 'Opening CSS runtime demo in a Windows window...'
& .\build\jx-window-win32.exe .\build\css-runtime-demo.exe examples/style.css
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}
