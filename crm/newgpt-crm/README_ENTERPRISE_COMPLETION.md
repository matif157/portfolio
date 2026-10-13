# SPS CRM — Enterprise Completion Build

Target environment:
- Folder: `C:\xampp\htdocs\newgpt\`
- Database: `gpt`
- URL: `http://localhost/newgpt/`

## First install

1. Replace the existing CRM files with this ZIP's contents.
2. Start Apache and MySQL in XAMPP.
3. Make sure the `gpt` database is available.
4. Log in using the current administrator credentials.
5. Open `upgrade_enterprise.php` and apply the additive enterprise schema.
6. Open `health_check.php`.

## Login recovery

`http://localhost/newgpt/setup_password.php`

Setup key:
`SPS-LOCAL-SETUP-2026`

After the password is repaired, delete `setup_password.php`.

## What this build adds

- Master enterprise sidebar tree
- Live database dashboard / command center
- Whitelisted enterprise CRUD module router for major CRM, sales, service, operations, finance, administration, security, automation and AI records
- Contacts, groups, tags, sources, leads, opportunities, pipeline, products and service taxonomy
- Workflow template/version/SLA records
- Appointments
- Finance templates, taxes and journals
- Security events, sessions and devices data structures
- Automation jobs, triggers and escalations
- AI prompts/activity/recommendations records
- Teams and departments
- Local CRM AI/search assistant
- Database-backed layout/settings control plane
- Secure-data encryption-at-rest foundation using a local key stored outside public request paths

## Integrity

This release is additive. It does not intentionally drop prior CRM data. Existing phase migrations remain in place for backwards compatibility.
