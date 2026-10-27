#!/usr/bin/env pwsh
# =============================================================================
# BOM Remover Script for B2B Marketplace Project
# =============================================================================
# ط§غŒظ† ط§ط³ع©ط±غŒظ¾طھ طھظ…ط§ظ… ظپط§غŒظ„â€Œظ‡ط§غŒ PHP ظˆ Blade ط±ط§ ط¨ط±ط§غŒ ظˆط¬ظˆط¯ BOM ط¨ط±ط±ط³غŒ ظ…غŒâ€Œع©ظ†ط¯
# ظˆ ط¯ط± طµظˆط±طھ غŒط§ظپطھظ†طŒ BOM ط±ط§ ط¨ظ‡ طµظˆط±طھ ط®ظˆط¯ع©ط§ط± ط­ط°ظپ ظ…غŒâ€Œع©ظ†ط¯

Write-Host "=================================================" -ForegroundColor Cyan
Write-Host "  BOM Remover - B2B Marketplace Project" -ForegroundColor Cyan
Write-Host "=================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "âڑ ï¸ڈ  ط§غŒظ† ط¹ظ…ظ„غŒط§طھ ظپط§غŒظ„â€Œظ‡ط§غŒ ط´ظ…ط§ ط±ط§ طھط؛غŒغŒط± ط®ظˆط§ظ‡ط¯ ط¯ط§ط¯!" -ForegroundColor Yellow
Write-Host ""

$confirmation = Read-Host "ط¢غŒط§ ظ…ط·ظ…ط¦ظ† ظ‡ط³طھغŒط¯طں (y/n)"
if ($confirmation -ne 'y') {
    Write-Host "ط¹ظ…ظ„غŒط§طھ ظ„ط؛ظˆ ط´ط¯." -ForegroundColor Yellow
    exit
}

Write-Host ""
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
$fixedCount = 0
$fixedFiles = @()

# ط¨ط±ط±ط³غŒ ظˆ ط±ظپط¹ BOM ط¯ط± ظپط§غŒظ„â€Œظ‡ط§غŒ Blade
Write-Host "ط¨ط±ط±ط³غŒ ظپط§غŒظ„â€Œظ‡ط§غŒ Blade..." -ForegroundColor Yellow
Get-ChildItem -Path "resources/views" -Filter "*.blade.php" -Recurse | ForEach-Object {
    $bytes = Get-Content $_.FullName -Encoding Byte -TotalCount 3 -ErrorAction SilentlyContinue
    if ($bytes.Count -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
        Write-Host "  ًں”§ ط¯ط± ط­ط§ظ„ ط±ظپط¹ BOM: $($_.FullName)" -ForegroundColor Cyan
        $content = Get-Content $_.FullName -Raw
        [System.IO.File]::WriteAllText($_.FullName, $content, $utf8NoBom)
        $fixedCount++
        $fixedFiles += $_.FullName
        Write-Host "  âœ… BOM ط­ط°ظپ ط´ط¯" -ForegroundColor Green
    }
}

# ط¨ط±ط±ط³غŒ ظˆ ط±ظپط¹ BOM ط¯ط± ظپط§غŒظ„â€Œظ‡ط§غŒ PHP
Write-Host ""
Write-Host "ط¨ط±ط±ط³غŒ ظپط§غŒظ„â€Œظ‡ط§غŒ PHP..." -ForegroundColor Yellow
Get-ChildItem -Path "app" -Filter "*.php" -Recurse | ForEach-Object {
    $bytes = Get-Content $_.FullName -Encoding Byte -TotalCount 3 -ErrorAction SilentlyContinue
    if ($bytes.Count -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
        Write-Host "  ًں”§ ط¯ط± ط­ط§ظ„ ط±ظپط¹ BOM: $($_.FullName)" -ForegroundColor Cyan
        $content = Get-Content $_.FullName -Raw
        [System.IO.File]::WriteAllText($_.FullName, $content, $utf8NoBom)
        $fixedCount++
        $fixedFiles += $_.FullName
        Write-Host "  âœ… BOM ط­ط°ظپ ط´ط¯" -ForegroundColor Green
    }
}

Write-Host ""
Write-Host "=================================================" -ForegroundColor Cyan

if ($fixedCount -gt 0) {
    Write-Host ""
    Write-Host "طھط¹ط¯ط§ط¯ $fixedCount ظپط§غŒظ„ ط§طµظ„ط§ط­ ط´ط¯!" -ForegroundColor Green
    Write-Host ""
    Write-Host "ط¯ط± ط­ط§ظ„ ظ¾ط§ع©â€Œط³ط§ط²غŒ ع©ط´ Blade..." -ForegroundColor Yellow
    php artisan view:clear
    Write-Host ""
    Write-Host "ع©ط´ ظ¾ط§ع© ط´ط¯. ظ„ط·ظپط§ظ‹ طھط؛غŒغŒط±ط§طھ ط±ط§ ط¯ط± ظ…ط±ظˆط±ع¯ط± ط¨ط±ط±ط³غŒ ع©ظ†غŒط¯." -ForegroundColor Green
    Write-Host ""
} else {
    Write-Host ""
    Write-Host "ظپط§غŒظ„غŒ ط¨ط±ط§غŒ ط§طµظ„ط§ط­ غŒط§ظپطھ ظ†ط´ط¯." -ForegroundColor Green
    Write-Host ""
}

Write-Host "=================================================" -ForegroundColor Cyan
