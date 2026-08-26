param(
    [string]$OutDir = "build/windows"
)

$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot "../..")).Path
$Out = Join-Path $Root $OutDir
$Exe = Join-Path $Out "jx-spec-contract.exe"
$Source = Join-Path $Root "tools/windows/jx-spec-contract.cs"

New-Item -ItemType Directory -Force -Path $Out | Out-Null

$csc = Get-Command csc.exe -ErrorAction SilentlyContinue
if (-not $csc) {
    throw "No C# compiler found. Install .NET SDK or Visual Studio Build Tools with csc.exe."
}

& csc.exe /nologo /optimize+ /warn:4 /out:$Exe $Source
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

Write-Output $Exe
