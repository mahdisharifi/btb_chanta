# اسکریپت PowerShell برای اصلاح سینتکس SQL dump

param(
    [Parameter(Mandatory=$true)]
    [string]$InputFile,
    
    [Parameter(Mandatory=$false)]
    [string]$OutputFile = ""
)

# اگر نام فایل خروجی مشخص نشده، فایل اصلی را backup می‌گیریم
if ($OutputFile -eq "") {
    $OutputFile = $InputFile
    $BackupFile = $InputFile + ".backup"
    Copy-Item -Path $InputFile -Destination $BackupFile
    Write-Host "✓ Backup created: $BackupFile" -ForegroundColor Green
}

# خواندن محتوای فایل
$content = Get-Content -Path $InputFile -Raw -Encoding UTF8

# الگوهای نیاز به اصلاح
$patterns = @(
    # ADD KEY `index_name` `column` -> ADD KEY `index_name` (`column`)
    @{
        Pattern = 'ADD KEY\s+(`[^`]+`)\s+(`[^`]+`)\)'
        Replacement = 'ADD KEY $1 ($2))'
    },
    # ADD PRIMARY KEY `column` -> ADD PRIMARY KEY (`column`)
    @{
        Pattern = 'ADD PRIMARY KEY\s+(`[^`]+`)\)'
        Replacement = 'ADD PRIMARY KEY ($1))'
    },
    # ADD UNIQUE KEY `index_name` `column` -> ADD UNIQUE KEY `index_name` (`column`)
    @{
        Pattern = 'ADD UNIQUE KEY\s+(`[^`]+`)\s+(`[^`]+`)\)'
        Replacement = 'ADD UNIQUE KEY $1 ($2))'
    }
)

$changesCount = 0

# اعمال هر الگو
foreach ($pattern in $patterns) {
    $matches = [regex]::Matches($content, $pattern.Pattern)
    if ($matches.Count -gt 0) {
        Write-Host "Found $($matches.Count) matches for pattern: $($pattern.Pattern)" -ForegroundColor Yellow
        $content = [regex]::Replace($content, $pattern.Pattern, $pattern.Replacement)
        $changesCount += $matches.Count
    }
}

# ذخیره فایل اصلاح شده
Set-Content -Path $OutputFile -Value $content -Encoding UTF8

if ($changesCount -gt 0) {
    Write-Host "`n✓ Fixed $changesCount syntax errors" -ForegroundColor Green
    Write-Host "✓ Output saved to: $OutputFile" -ForegroundColor Green
} else {
    Write-Host "`n⚠ No syntax errors found" -ForegroundColor Yellow
}

Write-Host "`nYou can now import the fixed SQL file to your server." -ForegroundColor Cyan
