# XAMPP Installation

1. Place project in `htdocs/SPS_CRM_Phase1` (or any name)
2. Copy `.env.example` → `.env`
3. Set in `.env`:
   ```
   DB_HOST=127.0.0.1
   DB_NAME=sps_crm
   DB_USER=root
   DB_PASS=
   ```
4. Import `database/install.sql` via phpMyAdmin or CLI
5. Ensure Apache `mod_rewrite` is on (httpd.conf: LoadModule rewrite_module)
6. Visit `http://localhost/SPS_CRM_Phase1/`
7. Login admin / admin123

If assets 404: check that `public/assets` is reachable and base URL is auto-detected.
