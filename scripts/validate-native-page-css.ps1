param(
    [string] $CssFile = 'examples/style.css'
)

$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

$CssPath = $CssFile
if (-not (Test-Path $CssPath)) {
    Write-Error "Missing CSS file: $CssPath"
    exit 1
}

$Css = Get-Content -Raw -Path $CssPath

$AllowedSelectors = @(
    'window',
    'body',
    'page',
    'card',
    'badge',
    'form',
    'iframe',
    'iframe-chrome',
    'modal',
    'modal-overlay',
    'modal-close',
    '#demo-page',
    '#main-card',
    '#badge',
    '#dynamic-form',
    '#native-iframe',
    '#native-iframe-chrome',
    '#help-modal',
    '#help-modal-overlay',
    '#help-modal-close',
    '.page',
    '.native-page',
    '.card',
    '.badge',
    '.panel',
    '.dynamic-form',
    '.iframe',
    '.native-frame',
    '.iframe-chrome',
    '.modal',
    '.help-modal',
    '.modal-overlay',
    '.modal-close'
)

$AllowedProperties = @(
    'width',
    'height',
    'panel-width',
    'gap',
    'min-content-width',
    'background',
    'color',
    'border',
    'border-radius',
    'padding',
    'margin',
    'font-family',
    'display',
    'font-weight',
    'letter-spacing'
)

$ColorProperties = @('background', 'color', 'border')
$SizeProperties = @('width', 'height', 'panel-width', 'gap', 'min-content-width', 'border-radius', 'padding', 'margin', 'letter-spacing')
$DocumentedNoOps = @('font-family', 'display', 'font-weight', 'letter-spacing', 'margin')

$Errors = New-Object System.Collections.Generic.List[string]
$Warnings = New-Object System.Collections.Generic.List[string]

function Add-ErrorText {
    param([string] $Text)
    [void]$Errors.Add($Text)
}

function Add-WarningText {
    param([string] $Text)
    [void]$Warnings.Add($Text)
}

$OpenCount = ([regex]::Matches($Css, '\{')).Count
$CloseCount = ([regex]::Matches($Css, '\}')).Count
if ($OpenCount -ne $CloseCount) {
    Add-ErrorText "brace count mismatch: found $OpenCount opening brace(s) and $CloseCount closing brace(s)"
}

$BlockPattern = '(?s)([^{}]+)\{([^{}]*)\}'
$Matches = [regex]::Matches($Css, $BlockPattern)
if ($Matches.Count -eq 0) {
    Add-ErrorText 'no CSS blocks were parsed'
}

foreach ($Match in $Matches) {
    $SelectorList = $Match.Groups[1].Value.Trim()
    $Body = $Match.Groups[2].Value.Trim()

    if (-not $SelectorList) {
        Add-ErrorText 'empty selector before CSS block'
        continue
    }

    $Selectors = $SelectorList.Split(',') | ForEach-Object { $_.Trim() } | Where-Object { $_ }
    foreach ($Selector in $Selectors) {
        if ($AllowedSelectors -notcontains $Selector) {
            Add-ErrorText "unsupported selector: $Selector"
        }
    }

    $Declarations = $Body.Split(';') | ForEach-Object { $_.Trim() } | Where-Object { $_ }
    foreach ($Declaration in $Declarations) {
        $Colon = $Declaration.IndexOf(':')
        if ($Colon -lt 1) {
            Add-ErrorText "$SelectorList has malformed declaration: $Declaration"
            continue
        }

        $Property = $Declaration.Substring(0, $Colon).Trim().ToLowerInvariant()
        $Value = $Declaration.Substring($Colon + 1).Trim()

        if ($AllowedProperties -notcontains $Property) {
            Add-ErrorText "$SelectorList has unsupported property: $Property"
            continue
        }

        if ($DocumentedNoOps -contains $Property) {
            Add-WarningText "$SelectorList $Property is documented but not fully lowered by the current Win32 renderer"
        }

        if ($ColorProperties -contains $Property) {
            if ($Property -eq 'border') {
                $ColorMatch = [regex]::Match($Value, '#[0-9a-fA-F]{6}\b')
                if (-not $ColorMatch.Success) {
                    Add-ErrorText "$SelectorList border needs a concrete #RRGGBB color: $Value"
                }
            } elseif ($Value -notmatch '^#[0-9a-fA-F]{6}$') {
                Add-ErrorText "$SelectorList $Property is not a supported #RRGGBB color: $Value"
            }
        }

        if ($SizeProperties -contains $Property) {
            if ($Property -eq 'border') {
                continue
            }
            if ($Value -match '^-') {
                Add-ErrorText "$SelectorList $Property cannot be negative: $Value"
            }
            if ($Value -notmatch '^(0|[0-9]+(px|rem))$') {
                if ($Property -eq 'letter-spacing' -and $Value -match '^[0-9]+(\.[0-9]+)?em$') {
                    Add-WarningText "$SelectorList letter-spacing uses em; current renderer documents but does not lower it: $Value"
                } else {
                    Add-ErrorText "$SelectorList $Property uses unsupported unit/value: $Value"
                }
            }
        }
    }
}

$TextWithoutBlocks = [regex]::Replace($Css, $BlockPattern, '')
$TextWithoutBlocks = ($TextWithoutBlocks -replace '/\*.*?\*/', '').Trim()
if ($TextWithoutBlocks) {
    Add-WarningText "unparsed CSS text remains outside blocks: $TextWithoutBlocks"
}

if ($Warnings.Count -gt 0) {
    Write-Host 'Native CSS validation warnings:'
    foreach ($Warning in $Warnings) {
        Write-Host "  - $Warning"
    }
}

if ($Errors.Count -gt 0) {
    Write-Host 'Native CSS validation failed:'
    foreach ($ErrorText in $Errors) {
        Write-Host "  - $ErrorText"
    }
    exit 1
}

Write-Host "Native CSS validation OK: $($Matches.Count) block(s) checked from $CssPath"
