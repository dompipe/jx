$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

$ManifestPath = 'data/php_function_manifest.csv'
if (-not (Test-Path $ManifestPath)) {
    Write-Error "Missing manifest: $ManifestPath"
    exit 1
}

$Rows = Import-Csv -Path $ManifestPath
$AllowedStatuses = @(
    'native_c',
    'native_asm',
    'language_construct',
    'runtime_wrapper',
    'bridge_php',
    'unsupported'
)

$Errors = New-Object System.Collections.Generic.List[string]
$Seen = @{}

foreach ($Row in $Rows) {
    $Name = ($Row.function + '').Trim()
    if (-not $Name) {
        $Errors.Add('row has empty function name')
        continue
    }

    if ($Seen.ContainsKey($Name)) {
        $Errors.Add("duplicate function row: $Name")
    } else {
        $Seen[$Name] = $true
    }

    $Status = ($Row.status + '').Trim()
    if ($AllowedStatuses -notcontains $Status) {
        $Errors.Add("$Name has invalid status: $Status")
    }

    if ($Status -eq 'native_c' -or $Status -eq 'native_asm' -or $Status -eq 'language_construct' -or $Status -eq 'runtime_wrapper') {
        if (-not (($Row.c_symbol + '').Trim())) {
            $Errors.Add("$Name is $Status but has no c_symbol")
        }
        if (-not (($Row.notes + '').Trim())) {
            $Errors.Add("$Name is $Status but has no notes")
        }
    }

    if ($Status -eq 'native_c' -or $Status -eq 'native_asm') {
        if (-not (($Row.jx_headers + '').Trim())) {
            $Errors.Add("$Name is $Status but has no jx_headers")
        }
        if (-not (($Row.jx_sources + '').Trim())) {
            $Errors.Add("$Name is $Status but has no jx_sources")
        }
    }
}

if ($Errors.Count -gt 0) {
    Write-Host 'PHP function manifest validation failed:'
    foreach ($ErrorText in $Errors) {
        Write-Host "  - $ErrorText"
    }
    exit 1
}

Write-Host "PHP function manifest OK: $($Rows.Count) rows"
