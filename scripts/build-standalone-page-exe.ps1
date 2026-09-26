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

if (-not (Test-Path 'dist')) {
    New-Item -ItemType Directory -Path 'dist' | Out-Null
}

$CCompiler = Find-CommandPath @('gcc', 'x86_64-w64-mingw32-gcc', 'cc')
if (-not $CCompiler) {
    Write-Error 'No C compiler found. Install MSYS2 GCC and make sure C:\msys64\ucrt64\bin is on PATH.'
    exit 1
}

Write-Host "Using C compiler: $CCompiler"

Invoke-Step -Name 'Building standalone native dynamic page EXE...' -Exe $CCompiler -CommandArgs @(
    '-O2',
    '-Wall',
    '-Wextra',
    '-mwindows',
    '-o',
    'dist/jx-native-page-demo.exe',
    'src/window/jx-page-win32.c',
    'src/runtime/jx_css_runtime.c',
    '-lgdi32',
    '-luser32',
    '-lws2_32'
)

Write-Host ''
Write-Host 'Standalone EXE created:'
Write-Host '  dist\jx-native-page-demo.exe'
Write-Host ''
Write-Host 'Run it directly:'
Write-Host '  .\dist\jx-native-page-demo.exe'
Write-Host 'or double-click it in File Explorer.'
Write-Host ''
Write-Host 'Local update URL while the EXE is running:'
Write-Host '  http://127.0.0.1:8765/update?title=Hello&badge=LIVE&body=Updated+from+URL'
