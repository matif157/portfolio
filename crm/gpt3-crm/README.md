# SPS Professional Services CRM

A modular PHP/MySQL professional-services CRM built from the SPS requirements and the working XAMPP baseline.

## Requirements
- PHP 8.1+
- MySQL/MariaDB compatible with the supplied SQL
- Apache (XAMPP recommended)
- PDO MySQL, OpenSSL and Fileinfo PHP extensions

## XAMPP installation
1. Extract the project to `C:\xampp\htdocs\SPS_CRM_AI_Professional_Complete`.
2. Copy `.env.example` to `.env`; for a normal XAMPP subfolder installation leave `APP_URL=` blank.
3. Start Apache and MySQL.
4. Open phpMyAdmin and import `database/crm.sql`.
5. Visit `http://localhost/SPS_CRM_AI_Professional_Complete/`.
6. Login with `admin` / `ChangeMeImmediately!123`, then change the password.

If Apache rewrite is unavailable, the guaranteed login entry is `http://localhost/SPS_CRM_AI_Professional_Complete/index.php?route=%2Flogin`. The included `.htaccess` serves `/assets/*` from `public/assets/*` and routes clean application paths through the front controller.

## PHP built-in server
From the `public` directory: `php -S 127.0.0.1:8000 index.php`. Then use `http://127.0.0.1:8000/login`.

## Database
`database/crm.sql` is the complete clean-database initializer assembled from the integrated schema, permission seeds, service catalog, workflow seeds, automation, source policies, legacy staging and reporting views. Legacy CSV/XLSX exports are included under `database/legacy_data`; use `database/import_legacy.php` and then `database/normalize_legacy.php` only after reviewing the staging data.

## Core modules
Clients/businesses/contacts, leads, services and requirements, service workflow versions/steps, client service requests, projects and recurring projects, tasks/deadlines, quotations, invoices/payments, documents, communications, approvals, cases/exceptions, calendar/reminders, activity/audit, reports, AI operations, users/roles/permissions and client portal.

## Automation and AI
The workflow and orchestration layers can detect missing requirements, create follow-ups, store AI decision records, create human-approval gates and invoke external adapters. AI, voice, messaging, payment and authority integrations are disabled until valid provider credentials/configuration and any required consent/authorization are present.

## Verification
Run `php tests/syntax_scan.php`, `php tests/config_check.php`, and `php tests/route_check.php`. Use `php tools/verify-install.php` to inspect runtime extensions.

## Security before production
Change the seeded administrator password, set a strong `APP_KEY`, use HTTPS, keep `.env` private, keep sensitive uploads out of public directories, restrict database credentials, and review retention/consent requirements before enabling automated calls or regulatory submissions.
