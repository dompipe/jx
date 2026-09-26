param(
    [string] $ManifestDir = '',
    [string] $Manifest = '',
    [string] $PhpPage = '',
    [string] $CssFile = '',
    [string] $OutputExe = '',
    [string] $GeneratedHeader = '',
    [string] $PageSource = '',
    [string] $CssValidator = '',
    [string] $Compiler = '',
    [string] $ApiPort = ''
)

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

function Get-ConfigValue {
    param(
        [object] $Config,
        [string] $Name,
        [string] $CliValue,
        [string] $Fallback
    )

    if ($CliValue) {
        return $CliValue
    }

    if ($null -ne $Config -and $Config.PSObject.Properties[$Name]) {
        $Value = $Config.PSObject.Properties[$Name].Value
        if ($null -ne $Value -and "$Value") {
            return "$Value"
        }
    }

    return $Fallback
}

function Get-ConfigArray {
    param(
        [object] $Config,
        [string] $Name,
        [string[]] $Fallback
    )

    if ($null -ne $Config -and $Config.PSObject.Properties[$Name]) {
        $Value = $Config.PSObject.Properties[$Name].Value
        if ($null -ne $Value) {
            return @($Value)
        }
    }

    return $Fallback
}

function Normalize-LinkLibrary {
    param([string] $Name)

    if (-not $Name) {
        return $Name
    }

    if ($Name.StartsWith('-')) {
        return $Name
    }

    return "-l$Name"
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

$Config = $null
$EffectiveManifest = $Manifest
if (-not $EffectiveManifest -and $ManifestDir) {
    $Candidates = @(
        (Join-Path $ManifestDir 'jx-native-page.json'),
        (Join-Path $ManifestDir 'native-page.manifest.json'),
        (Join-Path $ManifestDir 'manifest.json')
    )
    foreach ($Candidate in $Candidates) {
        if (Test-Path $Candidate) {
            $EffectiveManifest = $Candidate
            break
        }
    }

    if (-not $EffectiveManifest) {
        Write-Error "No native page manifest found in $ManifestDir. Expected jx-native-page.json, native-page.manifest.json, or manifest.json."
        exit 1
    }
}

if ($EffectiveManifest) {
    if (-not (Test-Path $EffectiveManifest)) {
        Write-Error "Missing manifest file: $EffectiveManifest"
        exit 1
    }
    $Config = Get-Content -Raw -Path $EffectiveManifest | ConvertFrom-Json
}

$PhpPage = Get-ConfigValue -Config $Config -Name 'phpPage' -CliValue $PhpPage -Fallback 'examples/native_page.php'
$CssFile = Get-ConfigValue -Config $Config -Name 'cssFile' -CliValue $CssFile -Fallback 'examples/style.css'
$OutputExe = Get-ConfigValue -Config $Config -Name 'outputExe' -CliValue $OutputExe -Fallback 'dist/jx-php-native-page.exe'
$GeneratedHeader = Get-ConfigValue -Config $Config -Name 'generatedHeader' -CliValue $GeneratedHeader -Fallback 'build/jx_php_page_data.h'
$PageSource = Get-ConfigValue -Config $Config -Name 'pageSource' -CliValue $PageSource -Fallback 'src/window/jx-page-win32-from-php.c'
$CssValidator = Get-ConfigValue -Config $Config -Name 'cssValidator' -CliValue $CssValidator -Fallback 'scripts/validate-native-page-css.ps1'
$Compiler = Get-ConfigValue -Config $Config -Name 'compiler' -CliValue $Compiler -Fallback ''
$ApiPort = Get-ConfigValue -Config $Config -Name 'apiPort' -CliValue $ApiPort -Fallback '8765'

[int] $ApiPortNumber = 0
if (-not [int]::TryParse($ApiPort, [ref] $ApiPortNumber) -or $ApiPortNumber -lt 1024 -or $ApiPortNumber -gt 65535) {
    Write-Error "apiPort must be an integer from 1024 to 65535: $ApiPort"
    exit 1
}

$CFlags = Get-ConfigArray -Config $Config -Name 'cFlags' -Fallback @('-O2', '-Wall', '-Wextra', '-mwindows')
$RuntimeSources = Get-ConfigArray -Config $Config -Name 'runtimeSources' -Fallback @('src/runtime/jx_css_runtime.c')
$LinkLibraries = Get-ConfigArray -Config $Config -Name 'linkLibraries' -Fallback @('ws2_32', 'gdi32', 'user32')
$LinkLibraries = @($LinkLibraries | ForEach-Object { Normalize-LinkLibrary "$_" })

foreach ($RequiredFile in @($PhpPage, $CssFile, $PageSource)) {
    if (-not (Test-Path $RequiredFile)) {
        Write-Error "Missing build input: $RequiredFile"
        exit 1
    }
}

foreach ($RuntimeSource in $RuntimeSources) {
    if (-not (Test-Path $RuntimeSource)) {
        Write-Error "Missing runtime source: $RuntimeSource"
        exit 1
    }
}

$HeaderDir = Split-Path -Parent $GeneratedHeader
$OutputDir = Split-Path -Parent $OutputExe
if ($HeaderDir -and -not (Test-Path $HeaderDir)) {
    New-Item -ItemType Directory -Path $HeaderDir | Out-Null
}
if ($OutputDir -and -not (Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir | Out-Null
}

if ($CssValidator -and (Test-Path $CssValidator)) {
    Write-Host 'Validating native CSS before build...'
    & powershell -ExecutionPolicy Bypass -File $CssValidator -CssFile $CssFile
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
#define JX_API_PORT $ApiPortNumber

#endif
"@

Set-Content -Path $GeneratedHeader -Value $Header -Encoding ASCII

$CCompiler = $Compiler
if (-not $CCompiler) {
    $CCompiler = Find-CommandPath @('gcc', 'x86_64-w64-mingw32-gcc', 'cc')
}
if (-not $CCompiler) {
    Write-Error 'No C compiler found. Install MSYS2 GCC and make sure C:\msys64\ucrt64\bin is on PATH.'
    exit 1
}

Write-Host "Using C compiler: $CCompiler"
if ($EffectiveManifest) {
    Write-Host "Manifest: $EffectiveManifest"
}
Write-Host "PHP page: $PhpPage"
Write-Host "CSS asset: $CssFile"
Write-Host "Generated header: $GeneratedHeader"
Write-Host "Output EXE: $OutputExe"
Write-Host "Default API port: $ApiPortNumber"

$CommandArgs = @()
$CommandArgs += $CFlags
$CommandArgs += @('-I', $HeaderDir)
$CommandArgs += @('-o', $OutputExe)
$CommandArgs += @($PageSource)
$CommandArgs += $RuntimeSources
$CommandArgs += $LinkLibraries

Invoke-Step -Name 'Building PHP native page standalone EXE...' -Exe $CCompiler -CommandArgs $CommandArgs

Write-Host ''
Write-Host 'Standalone EXE created from PHP page:'
Write-Host "  $OutputExe"
Write-Host ''
Write-Host 'Run it directly:'
Write-Host "  .\$OutputExe"
Write-Host ''
Write-Host 'Run another copy on another local API port:'
Write-Host "  .\$OutputExe --port $($ApiPortNumber + 1)"
Write-Host ''
Write-Host 'Then test URL update:'
Write-Host "  http://127.0.0.1:$ApiPortNumber/update?title=Hello&badge=LIVE&body=Updated+from+URL"
Write-Host "  http://127.0.0.1:$ApiPortNumber/modal?title=Native+Modal&body=Opened+from+URL"
Write-Host "  http://127.0.0.1:$ApiPortNumber/iframe?title=Frame&html=%3Ch2%3EHello%3C%2Fh2%3E%3Cp%3EUpdated%20iframe%20HTML%3C%2Fp%3E"
