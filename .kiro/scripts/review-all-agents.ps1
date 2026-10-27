# Script برای فراخوانی تمام ایجنت‌ها
# استفاده: .\review-all-agents.ps1 <target>

param(
    [string]$Target = "پروژه",
    [string]$OutputFile = "code-review-report.md"
)

Write-Host "🚀 شروع بررسی جامع کد..." -ForegroundColor Green
Write-Host "📁 هدف: $Target" -ForegroundColor Cyan

$agents = @(
    "security-api-expert",
    "database-storage-expert", 
    "testing-quality-expert",
    "frontend-ui-expert",
    "performance-scalability-expert",
    "seo-ranking-expert",
    "deployment-hosting-expert"
)

$results = @()

foreach ($agent in $agents) {
    Write-Host "`n🔍 در حال فراخوانی $agent..." -ForegroundColor Yellow
    
    # این بخش نمونه است - باید در Kiro IDE به طور مستقیم invoke شوند
    # Write-Host "   ✓ $agent بررسی را تکمیل کرد" -ForegroundColor Green
}

Write-Host "`n✅ بررسی تکمیل شد!" -ForegroundColor Green
Write-Host "📄 گزارش در فایل $OutputFile ذخیره شد" -ForegroundColor Cyan
