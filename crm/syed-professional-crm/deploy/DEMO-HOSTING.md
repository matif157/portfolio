# Live Demo Deployment Guide — Syed Professional Services CRM

> A **real, working** CRM for an immigration / professional-services consultancy:
> case intake with required-document checklists, workflow stage tracking, tasks
> (auto + manual), projects, invoicing with partial payments, approvals, internal
> comments and audit log. Runs on sanitized demo data (100% fictional records).

## Demo credentials

| Role    | Email              | Password   | Access                 |
|---------|--------------------|------------|------------------------|
| Admin   | `admin@demo.local` | `demo12345`| Full CRM               |
| Staff   | `staff2@demo.local` … `staff4@demo.local` | `demo12345` | Case/task work |

All user accounts share password `demo12345`.

## What's in the demo data

- **49 clients** with nationalities (UK, US, DE, UAE, AU, CA, PK), 55 contacts
- **6 services** (Immigration, Debt Collection, Legal Notice, Document Attestation,
  Visa Sponsorship, Permanent Residency) with **19 required-document checklists**
- **44 active cases** across every stage (intake → documents → review → completed),
  priorities, due dates, assigned staff
- **21 projects**, **119 tasks** (55% completed, some auto-created), **39 invoices**
  (draft/sent/partial/paid/overdue) with **19 received payments**
- **49 comments**, **29 notifications**, **25 workflow runs**, **19 approvals**,
  **59 audit-log entries**

## Free hosting target (InfinityFree / ProFreeHost)

### 1. Account
Sign up at `https://infinityfree.net` (free, two clicks) → domain like
`sps-crm-demo.infinityfreeapp.com`.

### 2. Database
Control Panel → *MySQL Databases* → **Import** `deploy/syed-professional-crm-demo.sql`
via phpMyAdmin into the new database. (The dump contains the full schema + data —
no web installer needed.)

### 3. Upload code
Upload the whole project into the host's web folder (`htdocs/`), minus
`.git`, `deploy/`, `tests/`, `tools/`, `setup-project.ps1`. Note the app serves
from `public/`.

### 4. Root `.htaccess` → `public/`
Put this at `htdocs/.htaccess` so requests land in `public/`:

```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

RewriteEngine On
RewriteRule ^$ public/ [L]
RewriteRule (.*) public/$1 [L]

<FilesMatch "^\.">
    Require all denied
</FilesMatch>
```

### 5. `.env`
Copy/create `.env` at project root (loaded by `config/config.php`):

```env
APP_NAME="Syed Professional Services CRM (Demo)"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sps-crm-demo.infinityfreeapp.com
APP_BASE_PATH=""
APP_TIMEZONE=Asia/Karachi

COMPANY_NAME="Syed Professional Services"
COMPANY_EMAIL=admin@demo.local
COMPANY_PHONE=
COMPANY_ADDRESS=

SESSION_NAME=sps_crm
SESSION_SECURE=true
SESSION_SAME_SITE=Lax

DB_HOST=sqlXXX.infinityfree.com
DB_PORT=3306
DB_DATABASE=if0_456_demo
DB_USERNAME=if0_456_user
DB_PASSWORD=yourpassword
DB_CHARSET=utf8mb4

MAIL_DRIVER=log
MAIL_FROM=noreply@demo.local
```

### 6. Mark installed
The app considers itself installed when `storage/installed.lock` exists.
Upload an empty `storage/installed.lock` so the installer page is skipped.

### 7. Verify
- Open the site → admin dashboard (case stats, tasks, invoices, charts).
- Login `admin@demo.local` / `demo12345`.
- Create a case for a client, add a document, issue an invoice, approve a task —
  the CRM behaves like production but all data is demo.
- Check `/configure` shows the DB connection is live.

## Notes
- Rebuild the demo data locally: `bash deploy/rebuild-demo.sh`
  (mirrors the local MariaDB described in the script; uses `migrations/001_initial.sql`
  + `deploy/gen_sps.py` with a fixed random seed and a universal demo password).