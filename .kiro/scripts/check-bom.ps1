#!/usr/bin/env pwsh
# =============================================================================
# BOM Checker Script for B2B Marketplace Project
# =============================================================================
# ط§غŒظ† ط§ط³ع©ط±غŒظ¾طھ طھظ…ط§ظ… ظپط§غŒظ„â€Œظ‡ط§غŒ PHP ظˆ Blade ط±ط§ ط¨ط±ط§غŒ ظˆط¬ظˆط¯ BOM ط¨ط±ط±ط³غŒ ظ…غŒâ€Œع©ظ†ط¯
# ظˆ ط¯ط± طµظˆط±طھ غŒط§ظپطھظ†طŒ ظ„غŒط³طھ ظپط§غŒظ„â€Œظ‡ط§غŒ ط¯ط§ط±ط§غŒ BOM ط±ط§ ظ†ظ…ط§غŒط´ ظ…غŒâ€Œط¯ظ‡ط¯

Write-Host "=================================================" -ForegroundColor Cyan
Write-Host "  BOM Checker - B2B Marketplace Project" -ForegroundColor Cyan
Write-Host "=================================================" -ForegroundColor Cyan
Write-Host ""

$foundBom = $false
$bomFiles = @()

# ط¨ط±ط±ط³غŒ ظپط§غŒظ„â€Œظ‡ط§غŒ Blade
Write-Host "ط¨ط±ط±ط³غŒ ظپط§غŒظ„â€Œظ‡ط§غŒ Blade..." -ForegroundColor Yellow
Get-ChildItem -Path "resources/views" -Filter "*.blade.php" -Recurse | ForEach-Object {
    $bytes = Get-Content $_.FullName -Encoding Byte -TotalCount 3 -ErrorAction SilentlyContinue
    if ($bytes.Count -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
        $foundBom = $true
        $bomFiles += $_.FullName
        Write-Host "  â‌Œ BOM غŒط§ظپطھ ط´ط¯: $($_.FullName)" -ForegroundColor Red
    }
}

# ط¨ط±ط±ط³غŒ ظپط§غŒظ„â€Œظ‡ط§غŒ PHP
Write-Host ""
Write-Host "ط¨ط±ط±ط³غŒ ظپط§غŒظ„â€Œظ‡ط§غŒ PHP..." -ForegroundColor Yellow
Get-ChildItem -Path "app" -Filter "*.php" -Recurse | ForEach-Object {
    $bytes = Get-Content $_.FullName -Encoding Byte -TotalCount 3 -ErrorAction SilentlyContinue
    if ($bytes.Count -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
        $foundBom = $true
        $bomFiles += $_.FullName
        Write-Host "  â‌Œ BOM غŒط§ظپطھ ط´ط¯: $($_.FullName)" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "=================================================" -ForegroundColor Cyan

if ($foundBom) {
    Write-Host ""
    Write-Host "طھط¹ط¯ط§ط¯ $($bomFiles.Count) ظپط§غŒظ„ ط¯ط§ط±ط§غŒ BOM غŒط§ظپطھ ط´ط¯!" -ForegroundColor Red
    Write-Host ""
    Write-Host "ط¨ط±ط§غŒ ط­ط°ظپ ط®ظˆط¯ع©ط§ط± BOM ط§ط² ظ‡ظ…ظ‡ ظپط§غŒظ„â€Œظ‡ط§:" -ForegroundColor Yellow
    Write-Host "  .\.kiro\scripts\remove-bom.ps1" -ForegroundColor White
    Write-Host ""
} else {
    Write-Host ""
    Write-Host "ط¹ط§ظ„غŒ! ظ‡غŒع† ظپط§غŒظ„غŒ ط¨ط§ BOM غŒط§ظپطھ ظ†ط´ط¯." -ForegroundColor Green
    Write-Host ""
}

Write-Host "=================================================" -ForegroundColor Cyan
