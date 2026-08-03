@echo off
echo Running Laravel Tests...
echo.
php artisan test --stop-on-failure
echo.
echo Test execution completed.
pause
