# Build release zip for AI Content Generator
# Run from repo root. Creates ai-content-generator-{version}.zip with folder "ai-content-generator" at root (required for WordPress updates).
# Usage: .\build-release.ps1 [version]
# Example: .\build-release.ps1 1.0.0

param(
    [string]$Version = ""
)

if (-not $Version) {
    $header = Get-Content "ai-content-generator.php" -Raw
    if ($header -match "Version:\s*(\d+\.\d+(?:\.\d+)?)") {
        $Version = $Matches[1]
    } else {
        $Version = "1.0.0"
    }
}

$zipName = "ai-content-generator-$Version.zip"
$rootDir = "ai-content-generator"

if (Test-Path $zipName) { Remove-Item $zipName -Force }
if (Test-Path $rootDir) { Remove-Item $rootDir -Recurse -Force }

New-Item -ItemType Directory -Path $rootDir | Out-Null
$excludeNames = @($rootDir, ".git", ".github", "build-release.ps1")
Get-ChildItem -Path . -Force | Where-Object {
    $n = $_.Name
    if ($n -like "*.zip") { return $false }
    foreach ($e in $excludeNames) { if ($n -eq $e) { return $false } }
    $true
} | ForEach-Object { Copy-Item -Path $_.FullName -Destination (Join-Path $rootDir $_.Name) -Recurse -Force }
Compress-Archive -Path $rootDir -DestinationPath $zipName -Force
Remove-Item $rootDir -Recurse -Force

Write-Host "Created: $zipName"
Write-Host "Upload this file as a Release asset on GitHub."
$zipName
