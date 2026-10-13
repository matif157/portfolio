# SPS CRM Build Manifest

This package is a fresh re-packaging of the latest SPS CRM working build for the current autonomous development cycle.

Validation performed before packaging:
- PHP files scanned: 169
- PHP syntax errors: 0
- Master SQL present: `crm.sql`
- XAMPP launcher present: `start-xampp.bat`
- Local PHP launcher present: `start-local.bat`
- Root entry point present: `index.php`
- Login entry point present: `login.php`

Primary runtime target:
- Windows + XAMPP
- PHP 8.1+
- MySQL/MariaDB

The application is intended to be run from the project directory under `htdocs`, using the included XAMPP launcher or the PHP built-in server launcher.
