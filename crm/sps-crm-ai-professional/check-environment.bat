@echo off
setlocal
title SPS CRM - Environment Check

set "PROJECT_ROOT=%~dp0"
set "PHP_EXE="

if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"
if not defined PHP_EXE if exist "C:\xampp_new\php\php.exe" set "PHP_EXE=C:\xampp_new\php\php.exe"

echo.
echo ==========================================
echo   SPS CRM - Environment Check
echo ==========================================
echo.
echo Project:
echo %PROJECT_ROOT%
echo.

if exist "%PROJECT_ROOT%public\index.php" (
    echo [OK] public\index.php exists.
) else (
    echo [FAIL] public\index.php is missing.
)

if exist "%PROJECT_ROOT%app\Support\Logger.php" (
    echo [OK] Logger.php exists.
) else (
    echo [FAIL] Logger.php is missing.
)

if defined PHP_EXE (
    echo [OK] PHP found:
    echo %PHP_EXE%
    "%PHP_EXE%" -v
    echo.
    "%PHP_EXE%" -r "if (PHP_VERSION_ID < 80200) { fwrite(STDERR, 'FAIL: PHP 8.2+ required. Current: '.PHP_VERSION.PHP_EOL); exit(1); } echo 'OK: PHP 8.2+ requirement satisfied.'.PHP_EOL;"
) else (
    echo [FAIL] XAMPP PHP not found.
)

echo.
echo [INFO] MySQL must be running in XAMPP before logging in.
echo [INFO] Import database\schema.sql if the sps_crm database does not exist.
echo.
pause
