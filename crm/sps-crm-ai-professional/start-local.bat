@echo off
setlocal
cd /d "%~dp0public"
echo.
echo SPS CRM local server
 echo URL: http://127.0.0.1:8000/login
 echo.
php -S 127.0.0.1:8000 index.php
pause
