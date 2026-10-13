Set-Location "$PSScriptRoot\public"
Write-Host "SPS CRM local server" -ForegroundColor Cyan
Write-Host "Open: http://127.0.0.1:8000/login" -ForegroundColor Green
php -S 127.0.0.1:8000 index.php
