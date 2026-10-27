@echo off
echo Adding test files...
git add tests\Feature\GuestPagesTest.php
git add tests\Feature\AdminPagesTest.php
git add tests\Feature\SupplierPagesTest.php
git add tests\Feature\RetailerPagesTest.php
git add tests\Feature\UserProfilePagesTest.php
git add tests\Feature\PaymentPagesTest.php
git add tests\Feature\README.md

echo.
echo Files staged. Creating commit...
git commit -m "feat: add comprehensive feature tests for all routes (87 tests)" -m "- Add GuestPagesTest: 15 tests for public pages" -m "- Add AdminPagesTest: 27 tests for admin panel" -m "- Add SupplierPagesTest: 18 tests for supplier panel" -m "- Add RetailerPagesTest: 12 tests for retailer panel" -m "- Add UserProfilePagesTest: 12 tests for user profile and account management" -m "- Add PaymentPagesTest: 3 tests for payment gateway" -m "- Add comprehensive documentation in tests/Feature/README.md"

echo.
echo Commit created successfully!
echo.
git log --oneline -1
pause
