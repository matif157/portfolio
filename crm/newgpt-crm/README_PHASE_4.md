# SPS CRM — Phase 4

Phase 4 adds finance recording, document requirements and a safer recovery path for local XAMPP login problems.

## IMPORTANT — LOGIN REPAIR

The previous default SQL password hash did not match the documented default password. Phase 4 includes a standalone:

`setup_password.php`

Use it at:

`http://localhost/newgpt/setup_password.php`

Setup key:

`SPS-LOCAL-SETUP-2026`

Enter a new password of at least 10 characters.

The utility directly creates/repairs the administrator account using PHP's `password_hash()`, so it does not depend on the broken old hash.

After successful login:
**DELETE setup_password.php** from the CRM folder.

This utility is intended only for local XAMPP recovery.

## Phase 4 install

1. Copy/replace the ZIP into `C:\xampp\htdocs\newgpt\`.
2. Start Apache and MySQL.
3. If login fails, first use `setup_password.php`.
4. Log in.
5. Open `upgrade_phase4.php`.
6. Click **Apply Phase 4 Database Upgrade**.

The migration is additive.

## Added

- Payments and invoice balance updates
- General document upload module
- Document requirements catalog
- Client document requirement foundation
- Custom-field foundation
- Secure-data foundation
- Internal service messaging foundation
- Better local administrator recovery

External email/SMS/WhatsApp/voice/OCR/AI are not falsely marked as connected.
