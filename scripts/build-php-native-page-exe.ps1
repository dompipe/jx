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

function Get-JxLiteralCall {
    param(
        [string] $Source,
        [string] $Name,
        [string] $Fallback
    )

    $Pattern = "(?s)" + [regex]::Escape($Name) + "\s*\(\s*(['\"])(.*?)\1\s*\)"
    $Match = [regex]::Match($Source, $Pattern)
    if ($Match.Success) {
        return $Match.Groups[2].Value
    }
    return $Fallback
}

function Convert-ToCString {
    param([string] $Text)

    $Builder = New-Object System.Text.StringBuilder
    [void]$Builder.Append('"')
    foreach ($Char in $Text.ToCharArray()) {
        switch ($Char) {
            "`n" { [void]$Builder.Append('\n') }
            "`r" { [void]$Builder.Append('\r') }
            "`t" { [void]$Builder.Append('\t') }
            '"'  { [void]$Builder.Append('\"') }
            '\'  { [void]$Builder.Append('\\') }
            default { [void]$Builder.Append($Char) }
        }
    }
    [void]$Builder.Append('"')
    return $Builder.ToString()
}

if (-not (Test-Path 'build')) {
    New-Item -ItemType Directory -Path 'build' | Out-Null
}

if (-not (Test-Path 'dist')) {
    New-Item -ItemType Directory -Path 'dist' | Out-Null
}

$PhpPage = 'examples/native_page.php'
$CssFile = 'examples/style.css'

$PhpSource = Get-Content -Raw -Path $PhpPage
$CssSource = Get-Content -Raw -Path $CssFile

$Title = Get-JxLiteralCall -Source $PhpSource -Name 'jx_page_title' -Fallback 'JX PHP Native Page'
$Badge = Get-JxLiteralCall -Source $PhpSource -Name 'jx_page_badge' -Fallback 'BUILT FROM PHP'
$Body = Get-JxLiteralCall -Source $PhpSource -Name 'jx_page_body' -Fallback 'Generated from PHP declarations.'

$Header = @"
#ifndef JX_PHP_PAGE_DATA_H
#define JX_PHP_PAGE_DATA_H

#define JX_PAGE_SOURCE "$(($PhpPage -replace '\\', '/'))"
#define JX_PAGE_TITLE $(Convert-ToCString $Title)
#define JX_PAGE_BADGE $(Convert-ToCString $Badge)
#define JX_PAGE_BODY $(Convert-ToCString $Body)
#define JX_PAGE_CSS $(Convert-ToCString $CssSource)

#endif
"@

Set-Content -Path 'build/jx_php_page_data.h' -Value $Header -Encoding ASCII

$CCompiler = Find-CommandPath @('gcc', 'x86_64-w64-mingw32-gcc', 'cc')
if (-not $CCompiler) {
    Write-Error 'No C compiler found. Install MSYS2 GCC and make sure C:\msys64\ucrt64\bin is on PATH.'
    exit 1
}

Write-Host "Using C compiler: $CCompiler"
Write-Host "PHP page: $PhpPage"
Write-Host "CSS asset: $CssFile"
Write-Host "Generated header: build\jx_php_page_data.h"

Invoke-Step -Name 'Building PHP native page standalone EXE...' -Exe $CCompiler -CommandArgs @(
    '-O2',
    '-Wall',
    '-Wextra',
    '-mwindows',
    '-I',
    'build',
    '-o',
    'dist/jx-php-native-page.exe',
    'src/window/jx-page-win32-from-php.c',
    'src/runtime/jx_css_runtime.c',
    '-lws2_32',
    '-lgdi32',
    '-luser32'
)

Write-Host ''
Write-Host 'Standalone EXE created from PHP page:'
Write-Host '  dist\jx-php-native-page.exe'
Write-Host ''
Write-Host 'Run it directly:'
Write-Host '  .\dist\jx-php-native-page.exe'
Write-Host ''
Write-Host 'Then test URL update:'
Write-Host '  http://127.0.0.1:8765/update?title=Hello&badge=LIVE&body=Updated+from+URL'
