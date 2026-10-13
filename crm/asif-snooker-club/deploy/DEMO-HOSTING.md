# Live Demo Deployment Guide — Asif Snooker Club

> The demo is a **real, working** copy of the production system (billing timers, member
> portal, bookings, tournaments, expenses, reports) running on sanitized fake data.
> This is what makes the portfolio stand out: not a screenshot, but a clickable system.

## Demo credentials

| Role  | Email            | Password   | Purpose                          |
|-------|------------------|------------|----------------------------------|
| Owner | `admin@demo.local` | `demo12345` | Full admin dashboard            |
| Staff | `staff2@demo.local` … `staff4@demo.local` | `demo12345` | Counter/admin demo    |
| Member| Member portal PIN: **1234** (first member) | — | Customer portal login |

Any user account on the demo accepts password `demo12345`.

## What's in the data (60 simulated days)

- **229 table sessions** (3 currently "live" on their timers when exported), 5–7 per day
- **52 members** (owner/staff/counter accounts, member portal)
- **214 payments** (cash / JazzCash / bank / card), incl. partial & unpaid balances
- **35 expenses** (electricity, labour, rent, repairs…), **22 bookings** with deposits
- A **completed 8-player knockout tournament** with full bracket & champion

## Free hosting target (InfinityFree / ProFreeHost)

Both are free PHP 8 + MySQL hosts with phpMyAdmin that can run this app as-is.

### 1. Create the account
- Sign up at `https://infinityfree.net` (free, no card). Two clicks; domain like
  `asif-snooker-demo.infinityfreeapp.com`.

### 2. Create the database
- Control Panel → *MySQL Databases* → create DB (name usually prefixed, e.g. `if0_123_demo`).
- Open **phpMyAdmin**, select the new database, **Import** tab →
  upload `deploy/asif-snooker-club-demo.sql` (all-in-one; creates all tables + demo data).
- Note the panel's **DB host, DB name, DB user, DB password**.

### 3. Upload the application
- Upload the whole project folder (everything except `.git`, `deploy/`, `docs/`, `docker-compose.yml`, `Dockerfile`) into the host's web folder (usually `htdocs/`).
- Current project is small (~a few hundred KB with `vendor/`) — a plain FTP/File Manager drag-drop works.

### 4. Document root → `public/`
Free hosts root the site at `htdocs`, but the app is built to serve from `public/`.
Upload this as `htdocs/.htaccess` so every request is rewritten into `public/`:

```apache
# Force https
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>

# Route everything into /public
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^$ public/ [L]
    RewriteRule (.*) public/$1 [L]
</IfModule>

<FilesMatch "^\.">
    Require all denied
</FilesMatch>
```

### 5. Configure `.env`
Copy `.env.example` → `.env` and set (values from your panel):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://asif-snooker-demo.infinityfreeapp.com
APP_TIMEZONE=Asia/Karachi

DB_HOST=sqlXXX.infinityfree.com
DB_PORT=3306
DB_NAME=if0_123_demo
DB_USER=if0_123_user
DB_PASS=yourpassword

CLUB_NAME="Asif Snooker Club (Demo)"
CLUB_PHONE="+923001234567"
CLUB_ADDRESS="D Ground, Faisalabad, Punjab, Pakistan"
```

> Do **not** run `install.php` — the SQL dump is the install.

### 6. Verify
- Visit `https://your-domain/` → should show the login page.
- Login `admin@demo.local` / `demo12345` → dashboard with live timers, reports, charts.
- Try the member portal PIN **1234**.
- Check friendly URLs, e.g. `/reports` loads without `.php`.

## Notes
- If the host lacks `vendor/` (some reset it), run `composer install` locally and re-upload `vendor/`, or zip upload the full folder.
- Destructive operations (DB reset, install) are intentionally not on the demo.
- To regenerate the demo data locally: `bash deploy/rebuild-demo.sh` (needs the two local MariaDB instances described in the script header).