@echo off
chcp 65001 >nul
SETLOCAL ENABLEDELAYEDEXPANSION
                   
:: تنظیم پوشه مقصد 
SET "DESTINATION_FOLDER=E:\sharifi\projects\b2b-marketplace-new\Hosh Masnoei"
  
:: متنی که باید از کل مسیر حذف شود
SET "TEXT_TO_REMOVE=E:\sharifi\projects\b2b-marketplace-new\"

echo ===================================================
echo در حال شروع عملیات پردازش و بررسی فایل‌ها...
echo ===================================================
echo.
 
:: در صورت عدم وجود پوشه مقصد، آن را می‌سازد
if not exist "%DESTINATION_FOLDER%" mkdir "%DESTINATION_FOLDER%"

:: =============================================
:: پوشه‌های بازگشتی (با زیرپوشه)
:: =============================================
CALL :PROCESS_FOLDER "E:\sharifi\projects\b2b-marketplace-new\app"
CALL :PROCESS_FOLDER "E:\sharifi\projects\b2b-marketplace-new\database"
CALL :PROCESS_FOLDER "E:\sharifi\projects\b2b-marketplace-new\resources"
CALL :PROCESS_FOLDER "E:\sharifi\projects\b2b-marketplace-new\routes"
CALL :PROCESS_FOLDER "E:\sharifi\projects\b2b-marketplace-new\my_file"
CALL :PROCESS_FOLDER "E:\sharifi\projects\b2b-marketplace-new\public\fonts"
CALL :PROCESS_FOLDER "E:\sharifi\projects\b2b-marketplace-new\kavenegar-examples-php-master"
CALL :PROCESS_FOLDER "E:\sharifi\projects\b2b-marketplace-new\config"


:: =============================================
:: پوشه‌های غیربازگشتی (فقط فایل‌های همان پوشه - بدون زیرپوشه)
:: =============================================
:: مسیرهای دلخواه خود را در این قسمت اضافه کنید:

CALL :PROCESS_FOLDER_FLAT "E:\sharifi\projects\b2b-marketplace-new"

echo.
echo ===================================================
echo پردازش با موفقیت به پایان رسید.
echo ===================================================
pause
goto :EOF


:: =============================================
:: زیربرنامه: پردازش بازگشتی (با زیرپوشه)
:: =============================================
:PROCESS_FOLDER
set "ROOT_FOLDER=%~1"

if not exist "%ROOT_FOLDER%\" (
    echo [خطا] پوشه یافت نشد: %ROOT_FOLDER%
    goto :EOF
)

echo.
echo پردازش پوشه (بازگشتی): %ROOT_FOLDER%
echo ---------------------------------------------------

FOR /R "%ROOT_FOLDER%" %%F IN (*) DO (
    :: دریافت مسیر کامل پوشه حاوی فایل
    set "DIR=%%~dpF"
    set "NAME=%%~nxF"
    
    :: ساخت نام نهایی
    set "REL=!DIR:%TEXT_TO_REMOVE%=!"
    set "REL=!REL:\=.!"
    set "NEWNAME=!REL!!NAME!"

    :: بررسی وجود فایل در مقصد
    if exist "%DESTINATION_FOLDER%\!NEWNAME!" (
        :: مقایسه باینری دو فایل
        fc /b "%%F" "%DESTINATION_FOLDER%\!NEWNAME!" >nul
        if !errorlevel! equ 0 (
            :: فایل‌ها یکسان هستند، کپی نمی‌شود
            echo [بدون تغییر - پرش] !NEWNAME!
        ) else (
            :: فایل‌ها تفاوت دارند، جایگزین می‌شود
            copy /Y "%%F" "%DESTINATION_FOLDER%\!NEWNAME!" >nul
            echo [به‌روزرسانی شد] !NEWNAME!
        )
    ) else (
        :: فایل در مقصد وجود ندارد، کپی می‌شود
        copy /Y "%%F" "%DESTINATION_FOLDER%\!NEWNAME!" >nul
        echo [کپی شد] !NEWNAME!
    )
)
goto :EOF


:: =============================================
:: زیربرنامه: پردازش غیربازگشتی (فقط فایل‌های همان پوشه - بدون زیرپوشه)
:: =============================================
:PROCESS_FOLDER_FLAT
set "ROOT_FOLDER=%~1"

if not exist "%ROOT_FOLDER%\" (
    echo [خطا] پوشه یافت نشد: %ROOT_FOLDER%
    goto :EOF
)

echo.
echo پردازش پوشه (غیربازگشتی - فقط همین پوشه): %ROOT_FOLDER%
echo ---------------------------------------------------

:: فقط فایل‌های مستقیم داخل این پوشه (بدون /R، یعنی بدون زیرپوشه)
FOR %%F IN ("%ROOT_FOLDER%\*") DO (
    :: دریافت مسیر کامل پوشه حاوی فایل
    set "DIR=%%~dpF"
    set "NAME=%%~nxF"
    
    :: ساخت نام نهایی
    set "REL=!DIR:%TEXT_TO_REMOVE%=!"
    set "REL=!REL:\=.!"
    set "NEWNAME=!REL!!NAME!"

    :: بررسی وجود فایل در مقصد
    if exist "%DESTINATION_FOLDER%\!NEWNAME!" (
        :: مقایسه باینری دو فایل
        fc /b "%%F" "%DESTINATION_FOLDER%\!NEWNAME!" >nul
        if !errorlevel! equ 0 (
            :: فایل‌ها یکسان هستند، کپی نمی‌شود
            echo [بدون تغییر - پرش] !NEWNAME!
        ) else (
            :: فایل‌ها تفاوت دارند، جایگزین می‌شود
            copy /Y "%%F" "%DESTINATION_FOLDER%\!NEWNAME!" >nul
            echo [به‌روزرسانی شد] !NEWNAME!
        )
    ) else (
        :: فایل در مقصد وجود ندارد، کپی می‌شود
        copy /Y "%%F" "%DESTINATION_FOLDER%\!NEWNAME!" >nul
        echo [کپی شد] !NEWNAME!
    )
)
goto :EOF
