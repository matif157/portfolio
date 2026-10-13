# Syed Professional Services CRM

PHP 8.1+ / MySQL CRM for professional-services operations. Designed for easy **XAMPP** setup and redeploy on another server via the built-in **Configure** screen.

## Quick start (XAMPP)

1. Copy this folder to `C:\xampp\htdocs\syed-professional-crm`.
2. Start **Apache** and **MySQL** in XAMPP.
3. Open: [http://localhost/syed-professional-crm/public/install](http://localhost/syed-professional-crm/public/install)
4. Fill the installer (XAMPP defaults: DB user `root`, blank password, database `sps_professional_crm`).
   Do **not** reuse `mera_crm` / `sps_crm` if those already hold your old CRM data — pick a new empty database name.
5. Sign in with the admin email/password you created.

Or open [http://localhost/syed-professional-crm/](http://localhost/syed-professional-crm/) — it redirects into `public/`.

## Configure / redeploy on another server

After login (Admin role), open **Configure** in the sidebar:

- App name, URL, base path, timezone, debug
- Company details
- MySQL connection (with Test database)
- Admin email/password
- Mail defaults

Saving updates:

- `.env`
- `storage/config/local.php`
- `public/.htaccess` (`RewriteBase` for the new path)
- Database schema / admin user as needed

On a new server: copy the project, point the web root at `public/` (or keep XAMPP `htdocs` layout), open `/install` or `/configure`, set the new base path and DB credentials, save.

## Default credentials (installer defaults)

- Email: `admin@sps.local`
- Password: `admin123`  
Change these during install or later under Configure.

## Structure

- `app/Core` — HTTP, routing, database, sessions, auth, views
- `app/Controllers` — Auth, dashboard, modules, install, configure
- `app/Services/Installer.php` — install & redeploy helper
- `config/config.php` — loads `.env` + `storage/config/local.php`
- `database/migrations` — MySQL schema
- `resources/views` — UI
- `public` — web entry (`index.php`) and assets
- `storage` — logs, uploads, install lock, local config

## Manual notes

- Document root should be `public/` when possible.
- Enable Apache `mod_rewrite` (on by default in XAMPP).
- For production: turn debug off, use HTTPS, set secure cookies in Configure.
