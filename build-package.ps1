# Build script for cs-extension-manager
# Produces a single installable package at the repo root:
#   pkg_csextensionmanager_v{version}_{YYYYMMDD}_{HHMM}.zip
# which wraps the admin component and its three plugins (installer,
# quickicon, task). Install this one zip; Joomla unpacks it and installs
# every child extension.
#
# Zips are built with 7-Zip because PowerShell's Compress-Archive
# omits directory entries, which breaks Joomla's installer.

param(
    [string]$Version = ""
)

$ErrorActionPreference = "Stop"

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$sevenZip  = "C:\Program Files\7-Zip\7z.exe"

if (-not (Test-Path $sevenZip)) {
    throw "7-Zip not found at $sevenZip. Install 7-Zip or update the path in build-package.ps1."
}

$packagesPath = Join-Path $scriptDir "packages"

# UTF-8 BOM check across every PHP / XML / INI / JSON in the source tree.
# A BOM in front of a `<?php` opener emits raw bytes before the PHP
# parser starts, which breaks declare(strict_types=1). Fail here
# rather than at install time.
$bomFiles = @()
Get-ChildItem -Path $packagesPath -Recurse -Include "*.php", "*.xml", "*.ini", "*.json" -File | ForEach-Object {
    $head = [byte[]](Get-Content -LiteralPath $_.FullName -Encoding Byte -ReadCount 0 -TotalCount 3)
    if ($head.Count -ge 3 -and $head[0] -eq 0xEF -and $head[1] -eq 0xBB -and $head[2] -eq 0xBF) {
        $bomFiles += $_.FullName
    }
}
if ($bomFiles.Count -gt 0) {
    Write-Host "ABORT: UTF-8 BOM detected in the following file(s):" -ForegroundColor Red
    $bomFiles | ForEach-Object { Write-Host "  $_" -ForegroundColor Red }
    throw "Strip the BOM and re-run."
}

$pkgDir      = Join-Path $scriptDir "packages\pkg_csextensionmanager"
$pkgManifest = Join-Path $pkgDir "pkg_csextensionmanager.xml"

if (-not (Test-Path $pkgManifest)) {
    throw "Package manifest not found at $pkgManifest"
}

if ([string]::IsNullOrEmpty($Version)) {
    $manifestContents = Get-Content $pkgManifest -Raw
    if ($manifestContents -match '<version>([^<]+)</version>') {
        $Version = $matches[1]
    } else {
        throw "Could not read <version> from $pkgManifest"
    }
}

$childExtensions = @(
    @{
        Name      = "com_csextensionmanager"
        SourceDir = Join-Path $scriptDir "packages\com_csextensionmanager"
        Manifest  = "csextensionmanager.xml"
        Contents  = @("csextensionmanager.xml", "admin", "media")
    },
    @{
        Name      = "plg_installer_csextensionmanager"
        SourceDir = Join-Path $scriptDir "packages\plg_installer_csextensionmanager"
        Manifest  = "csextensionmanager.xml"
        Contents  = @("csextensionmanager.xml", "services", "src", "language")
    },
    @{
        Name      = "plg_quickicon_csextensionmanager"
        SourceDir = Join-Path $scriptDir "packages\plg_quickicon_csextensionmanager"
        Manifest  = "csextensionmanager.xml"
        Contents  = @("csextensionmanager.xml", "services", "src", "language")
    },
    @{
        Name      = "plg_task_csextensionmanager"
        SourceDir = Join-Path $scriptDir "packages\plg_task_csextensionmanager"
        Manifest  = "csextensionmanager.xml"
        Contents  = @("csextensionmanager.xml", "services", "src", "language", "forms")
    }
)

# Every manifest must carry the same version, or Joomla's update tracking
# drifts between the package and its children.
foreach ($ext in $childExtensions) {
    $m = Join-Path $ext.SourceDir $ext.Manifest
    if ((Get-Content $m -Raw) -match '<version>([^<]+)</version>') {
        if ($matches[1] -ne $Version) {
            throw "Version mismatch: $m says $($matches[1]), package says $Version"
        }
    }
}

# Empty folders in a package blow up Joomla's installer later (Brain gotcha #22).
$emptyDirs = Get-ChildItem -Path $packagesPath -Recurse -Directory | Where-Object {
    @(Get-ChildItem -LiteralPath $_.FullName -Recurse -File).Count -eq 0
}
if ($emptyDirs) {
    $emptyDirs | ForEach-Object { Write-Host "Empty dir: $($_.FullName)" -ForegroundColor Red }
    throw "Remove the empty folder(s) or add a file to them."
}

# The seed catalog and the asset registry must be valid JSON.
foreach ($json in @(
    "packages\com_csextensionmanager\admin\catalog\seed.json",
    "packages\com_csextensionmanager\media\joomla.asset.json"
)) {
    try {
        Get-Content (Join-Path $scriptDir $json) -Raw | ConvertFrom-Json | Out-Null
    } catch {
        throw "Invalid JSON in ${json}: $($_.Exception.Message)"
    }
}

$timestamp  = Get-Date -Format "yyyyMMdd_HHmm"
$pkgZipName = "pkg_csextensionmanager_v${Version}_${timestamp}.zip"
$pkgZipPath = Join-Path $scriptDir $pkgZipName

# Clean old builds for this version
Get-ChildItem -Path $scriptDir -Filter "pkg_csextensionmanager_v${Version}_*.zip" -ErrorAction SilentlyContinue | Remove-Item -Force

$pkgStage = Join-Path $scriptDir "build"
if (Test-Path $pkgStage) { Remove-Item $pkgStage -Recurse -Force }
New-Item -ItemType Directory -Path $pkgStage | Out-Null
New-Item -ItemType Directory -Path (Join-Path $pkgStage "packages") | Out-Null

Write-Host "Building pkg_csextensionmanager v$Version ..." -ForegroundColor Cyan

# 1. Build each child extension into the staging packages/ folder with
#    a stable, non-timestamped filename.
foreach ($ext in $childExtensions) {
    Write-Host "  Child: $($ext.Name)" -ForegroundColor DarkCyan

    $childStage = Join-Path $scriptDir "build-child"
    if (Test-Path $childStage) { Remove-Item $childStage -Recurse -Force }
    New-Item -ItemType Directory -Path $childStage | Out-Null

    foreach ($item in $ext.Contents) {
        $source = Join-Path $ext.SourceDir $item
        if (-not (Test-Path $source)) {
            throw "$($ext.Name): $item is listed but missing."
        }
        $dest = Join-Path $childStage $item
        if ((Get-Item $source).PSIsContainer) {
            Copy-Item $source $dest -Recurse
        } else {
            Copy-Item $source $dest
        }
    }

    $childZipPath = Join-Path $pkgStage "packages\$($ext.Name).zip"

    Push-Location $childStage
    try {
        & $sevenZip a -tzip $childZipPath * | Out-Null
        if ($LASTEXITCODE -ne 0) { throw "7-Zip failed on $($ext.Name) (exit $LASTEXITCODE)" }
    } finally {
        Pop-Location
    }

    Remove-Item $childStage -Recurse -Force
}

# 2. Copy package manifest + script + language into staging root.
Copy-Item $pkgManifest (Join-Path $pkgStage "pkg_csextensionmanager.xml")
Copy-Item (Join-Path $pkgDir "script.php") (Join-Path $pkgStage "script.php")
Copy-Item (Join-Path $pkgDir "language") (Join-Path $pkgStage "language") -Recurse

# Every release zip carries the full changelog in both formats
# (VERSION-BUMP-CHECKLIST). Refuse to build without them.
foreach ($log in @("CHANGELOG.md", "CHANGELOG.html")) {
    $logPath = Join-Path $scriptDir $log
    if (-not (Test-Path $logPath)) { throw "$log is missing from the repo root." }
    if ((Get-Content $logPath -Raw) -notmatch [regex]::Escape($Version)) {
        throw "$log has no entry for $Version."
    }
    Copy-Item $logPath (Join-Path $pkgStage $log)
}

# 3. Zip the whole package.
Push-Location $pkgStage
try {
    & $sevenZip a -tzip $pkgZipPath * | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "7-Zip failed on package (exit $LASTEXITCODE)" }
} finally {
    Pop-Location
}

Remove-Item $pkgStage -Recurse -Force

$sizeKb = [Math]::Round((Get-Item $pkgZipPath).Length / 1KB, 1)
Write-Host "Created $pkgZipName ($sizeKb KB)" -ForegroundColor Green

# 4. Report sha256 to check against the one Release Manager computes on upload.
#    Updates are served by cs-Release-Manager on cybersalt.com, not an updates.xml.
$sha256 = (Get-FileHash $pkgZipPath -Algorithm SHA256).Hash.ToLower()
Write-Host "SHA256: $sha256" -ForegroundColor Yellow
Write-Host ""
Write-Host "To release: copy to pkg_csextensionmanager_v$Version.zip, attach it to the GitHub release," -ForegroundColor Gray
Write-Host "then upload it to cs-Release-Manager (element pkg_csextensionmanager)." -ForegroundColor Gray
