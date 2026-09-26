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

    $EscapedName = [regex]::Escape($Name)
    $SinglePattern = "(?s)$EscapedName\s*\(\s*'([^']*)'\s*\)"
    $SingleMatch = [regex]::Match($Source, $SinglePattern)
    if ($SingleMatch.Success) {
        return $SingleMatch.Groups[1].Value
    }

    $DoublePattern = '(?s)' + $EscapedName + '\s*\(\s*"([^"]*)"\s*\)'
    $DoubleMatch = [regex]::Match($Source, $DoublePattern)
    if ($DoubleMatch.Success) {
        return $DoubleMatch.Groups[1].Value
    }

    return $Fallback
}

function Convert-ToCString {
    param([string] $Text)

    if ($null -eq $Text) {
        $Text = ''
    }

    $Builder = New-Object System.Text.StringBuilder
    [void]$Builder.Append([char]34)

    foreach ($Char in $Text.ToCharArray()) {
        $Code = [int][char]$Char
        if ($Code -eq 10) {
            [void]$Builder.Append('\n')
        } elseif ($Code -eq 13) {
            [void]$Builder.Append('\r')
        } elseif ($Code -eq 9) {
            [void]$Builder.Append('\t')
        } elseif ($Code -eq 34) {
            [void]$Builder.Append('\"')
        } elseif ($Code -eq 92) {
            [void]$Builder.Append('\\')
        } elseif ($Code -lt 32 -or $Code -gt 126) {
            [void]$Builder.Append(('\x{0:x2}' -f $Code))
        } else {
            [void]$Builder.Append($Char)
        }
    }

    [void]$Builder.Append([char]34)
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

if (Test-Path 'scripts/validate-native-page-css.ps1') {
    Write-Host 'Validating native CSS before build...'
    & powershell -ExecutionPolicy Bypass -File '.\scripts\validate-native-page-css.ps1'
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
}

$PhpSource = Get-Content -Raw -Path $PhpPage
$CssSource = Get-Content -Raw -Path $CssFile

$Title = Get-JxLiteralCall -Source $PhpSource -Name 'jx_page_title' -Fallback 'JX PHP Native Page'
$Badge = Get-JxLiteralCall -Source $PhpSource -Name 'jx_page_badge' -Fallback 'BUILT FROM PHP'
$Body = Get-JxLiteralCall -Source $PhpSource -Name 'jx_page_body' -Fallback 'Generated from PHP declarations.'
$ModalTitle = Get-JxLiteralCall -Source $PhpSource -Name 'jx_modal_title' -Fallback 'JX Native Modal'
$ModalBody = Get-JxLiteralCall -Source $PhpSource -Name 'jx_modal_body' -Fallback 'This modal was declared in PHP and compiled into the native executable.'
$IframeTitle = Get-JxLiteralCall -Source $PhpSource -Name 'jx_iframe_title' -Fallback 'Native Iframe'
$IframeHtml = Get-JxLiteralCall -Source $PhpSource -Name 'jx_iframe_html' -Fallback '<p>Iframe HTML declared in PHP.</p>'

$PageSourcePath = $PhpPage -replace '\\', '/'
$PageSourceC = Convert-ToCString $PageSourcePath
$TitleC = Convert-ToCString $Title
$BadgeC = Convert-ToCString $Badge
$BodyC = Convert-ToCString $Body
$ModalTitleC = Convert-ToCString $ModalTitle
$ModalBodyC = Convert-ToCString $ModalBody
$IframeTitleC = Convert-ToCString $IframeTitle
$IframeHtmlC = Convert-ToCString $IframeHtml
$CssC = Convert-ToCString $CssSource

$Header = @"
#ifndef JX_PHP_PAGE_DATA_H
#define JX_PHP_PAGE_DATA_H

#define JX_PAGE_SOURCE $PageSourceC
#define JX_PAGE_TITLE $TitleC
#define JX_PAGE_BADGE $BadgeC
#define JX_PAGE_BODY $BodyC
#define JX_PAGE_MODAL_TITLE $ModalTitleC
#define JX_PAGE_MODAL_BODY $ModalBodyC
#define JX_PAGE_IFRAME_TITLE $IframeTitleC
#define JX_PAGE_IFRAME_HTML $IframeHtmlC
#define JX_PAGE_CSS $CssC

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
Write-Host '  http://127.0.0.1:8765/modal?title=Native+Modal&body=Opened+from+URL'
Write-Host '  http://127.0.0.1:8765/iframe?title=Frame&html=%3Ch2%3EHello%3C%2Fh2%3E%3Cp%3EUpdated%20iframe%20HTML%3C%2Fp%3E'
