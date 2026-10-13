# SPS CRM — Phase 6

Phase 6 focuses on authentication reliability and controlled operations.

## Login reset
Open:
`http://localhost/newgpt/setup_password.php`

Setup key:
`SPS-LOCAL-SETUP-2026`

Create/reset the admin password (10+ characters). Then use:
`http://localhost/newgpt/login.php`

After the login is confirmed, delete `setup_password.php`.

The login path now uses `password_verify()`, session regeneration, login-attempt tracking and consistent logout. The old default password hash is no longer used.

## Install Phase 6
Copy/replace the ZIP into:
`C:\xampp\htdocs\newgpt\`

Start Apache + MySQL, log in, then open:
`upgrade_phase6.php`

Apply the additive migration.

## Added
- Login attempt tracking
- Password reset foundation
- Communication templates
- Approval queue
- Exception queue
- Workflow checkpoints foundation

External providers are not marked as working unless configured.


## LOGIN PATH FIX

The login recovery links are now relative to the CRM folder.

Correct URL when the folder is:

`C:\xampp\htdocs\newgpt\`

Login:
`http://localhost/newgpt/login.php`

Password setup:
`http://localhost/newgpt/setup_password.php`

There should be no `/newgpt/` path in the Phase 6 login files.


## CURRENT LOCAL PATH
Folder: `C:\xampp\htdocs\newgpt\`
Database: `gpt`
Login: `http://localhost/newgpt/login.php`
Password setup: `http://localhost/newgpt/setup_password.php`
Setup key: `SPS-LOCAL-SETUP-2026`


## YOUR XAMPP SETTINGS
Folder: `C:\xampp\htdocs\newgpt\`
Database: `gpt`
Login: `http://localhost/newgpt/login.php`
Password setup: `http://localhost/newgpt/setup_password.php`
Setup key: `SPS-LOCAL-SETUP-2026`
