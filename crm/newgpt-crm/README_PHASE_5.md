# SPS CRM — Phase 5

Phase 5 adds practical management, sales, reporting, administration and configuration features while keeping the same flat XAMPP structure.

## Install

1. Copy/replace the ZIP contents into:
   `C:\xampp\htdocs\newgpt\`
2. Start Apache and MySQL.
3. Ensure Phase 4 is already installed.
4. Log in.
5. Open `upgrade_phase5.php`.
6. Click **Apply Phase 5 Database Upgrade**.

The migration is additive.

## Added

- Quotations and quotation items
- Payments history support is continued
- Reports with date filtering
- User creation/activation/deactivation
- Role-aware user administration
- Client customizer
- CRM settings
- Project comments foundation
- Task comments foundation
- Recurring project series foundation
- User preferences foundation
- Dashboard navigation upgrades
- Phase 5 health-check status

## Security / reliability

- Passwords use `password_hash()`
- Existing CSRF protection remains
- Permission checks protect administrative screens
- No external service is falsely marked as working

The project remains XAMPP-oriented: PHP + MySQL/MariaDB + browser, with no Node/npm/Docker requirement.
