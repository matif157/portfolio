@echo off
cd /d C:\xampp\htdocs\gpt3
if not exist public\index.php (
  echo ERROR: C:\xampp\htdocs\gpt3\public\index.php was not found.
  pause
  exit /b 1
)
echo Start Apache and MySQL in XAMPP.
echo Open: http://localhost/gpt3/
echo Login: http://localhost/gpt3/index.php?route=%%2Flogin
pause
