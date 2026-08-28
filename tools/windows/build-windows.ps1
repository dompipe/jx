param(
    [string]$OutDir = "build/windows"
)

$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot "../..")).Path
$Out = Join-Path $Root $OutDir
$Exe = Join-Path $Out "jx.exe"
$NativeExe = Join-Path $Out "jx-native-window.exe"
$Source = Join-Path $Root "tools/windows/jx-windows.c"
$HotSource = Join-Path $Root "host/common/jx-asm-call.c"
$SourceCs = Join-Path $Root "tools/windows/jx-windows.cs"
$NativeSourceCs = Join-Path $Root "tools/windows/jx-native-window.cs"

New-Item -ItemType Directory -Force -Path $Out | Out-Null

$escapedRoot = $Root.Replace('\', '\\')
$define = "/DJX_ROOT_COMPILED=`"$escapedRoot`""

$cl = Get-Command cl.exe -ErrorAction SilentlyContinue
if ($cl) {
    & cl.exe /nologo /O2 /W4 $define /Fe:$Exe $Source $HotSource
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    Write-Output $Exe
    exit 0
}

$gcc = Get-Command gcc.exe -ErrorAction SilentlyContinue
if ($gcc) {
    & gcc.exe -std=c11 -O2 -Wall -Wextra -pedantic "-DJX_ROOT_COMPILED=`"$Root`"" -o $Exe $Source $HotSource
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    Write-Output $Exe
    exit 0
}

$clang = Get-Command clang.exe -ErrorAction SilentlyContinue
if ($clang) {
    & clang.exe -std=c11 -O2 -Wall -Wextra "-DJX_ROOT_COMPILED=`"$Root`"" -o $Exe $Source $HotSource
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    Write-Output $Exe
    exit 0
}

$csc = Get-Command csc.exe -ErrorAction SilentlyContinue
if ($csc) {
    & csc.exe /nologo /optimize+ /out:$Exe $SourceCs
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    & csc.exe /nologo /target:winexe /optimize+ /r:System.Windows.Forms.dll /r:System.Drawing.dll /out:$NativeExe $NativeSourceCs
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    Write-Output $Exe
    Write-Output $NativeExe
    exit 0
}

throw "No Windows compiler found. Install Visual Studio Build Tools, MinGW-w64, LLVM/Clang, or .NET SDK csc, then rerun tools/windows/build-windows.ps1."
