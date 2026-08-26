param(
    [string]$OutDir = "build/windows"
)

$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot "../..")).Path
$Out = Join-Path $Root $OutDir
$Exe = Join-Path $Out "jx.exe"
$Source = Join-Path $Root "tools/windows/jx-windows.c"
$SourceCs = Join-Path $Root "tools/windows/jx-windows.cs"

New-Item -ItemType Directory -Force -Path $Out | Out-Null

$escapedRoot = $Root.Replace('\', '\\')
$define = "/DJX_ROOT_COMPILED=`"$escapedRoot`""

$cl = Get-Command cl.exe -ErrorAction SilentlyContinue
if ($cl) {
    & cl.exe /nologo /O2 /W4 $define /Fe:$Exe $Source
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    Write-Output $Exe
    exit 0
}

$gcc = Get-Command gcc.exe -ErrorAction SilentlyContinue
if ($gcc) {
    & gcc.exe -std=c11 -O2 -Wall -Wextra -pedantic "-DJX_ROOT_COMPILED=`"$Root`"" -o $Exe $Source
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    Write-Output $Exe
    exit 0
}

$clang = Get-Command clang.exe -ErrorAction SilentlyContinue
if ($clang) {
    & clang.exe -std=c11 -O2 -Wall -Wextra "-DJX_ROOT_COMPILED=`"$Root`"" -o $Exe $Source
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    Write-Output $Exe
    exit 0
}

$csc = Get-Command csc.exe -ErrorAction SilentlyContinue
if ($csc) {
    & csc.exe /nologo /optimize+ /out:$Exe $SourceCs
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    Write-Output $Exe
    exit 0
}

throw "No Windows compiler found. Install Visual Studio Build Tools, MinGW-w64, LLVM/Clang, or .NET SDK csc, then rerun tools/windows/build-windows.ps1."
