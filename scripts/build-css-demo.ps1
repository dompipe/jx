$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

if (-not (Test-Path 'build')) {
    New-Item -ItemType Directory -Path 'build' | Out-Null
}

$Compiler = $null
foreach ($Name in @('gcc', 'cc', 'x86_64-w64-mingw32-gcc')) {
    $Command = Get-Command $Name -ErrorAction SilentlyContinue
    if ($Command) {
        $Compiler = $Command.Source
        break
    }
}

if (-not $Compiler) {
    Write-Error 'No C compiler found. Install GCC or MinGW-w64 and put it in PATH.'
    exit 1
}

Write-Host "Using C compiler: $Compiler"

$ArgsList = @(
    '-O2',
    '-Wall',
    '-Wextra',
    '-o',
    'build/css-runtime-demo.exe',
    'examples/css_runtime_demo.c',
    'src/runtime/jx_css_runtime.c'
)

Write-Host "Building CSS runtime demo..."
Write-Host "  $Compiler $($ArgsList -join ' ')"
& $Compiler @ArgsList
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "Running CSS runtime demo..."
& .\build\css-runtime-demo.exe examples/style.css
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host 'PASS: CSS runtime demo built and ran.'
