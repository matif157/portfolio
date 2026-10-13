# mera_crm — Update Package

**Customizer · Services · Client Types · Error Logs**

Drop these folders into your existing CRM installation.

---

## 1. Run SQL first (required)

In phpMyAdmin → your `mera_crm` database → SQL tab:

Import / run: **`database/update_tables.sql`**

This creates:
- `client_types`
- `services`
- `settings` (customizer)
- `error_logs`
- Extra permissions
- Adds `client_type_id` column to `members` (if missing)

---

## 2. Copy folders into your CRM root

```
your-crm/
├── customizer/          ← copy this folder
│   └── index.php
├── services/            ← copy this folder
│   └── index.php
├── client_types/        ← copy this folder
│   └── index.php
├── error_logs/          ← copy this folder
│   └── index.php
├── includes/
│   └── error_handler.php  ← copy into includes/
└── database/
    └── update_tables.sql  ← already ran
```

---

## 3. Add menu links

See **`NAV_LINKS.txt`** for exact HTML to paste into `includes/header.php`.

Also update the subfolder regex in header.php so `$base` path works:
add `customizer|services|client_types|error_logs` to the preg_match list.

---

## 4. Optional — auto log PHP errors

In `includes/log_in.php` add:

```php
require_once __DIR__ . '/error_handler.php';
```

---

## Modules summary

| Module | URL | What it does |
|--------|-----|--------------|
| **Customizer** | `/customizer/` | Company info, invoice defaults, theme colors, general settings |
| **Services** | `/services/` | Service catalog (name, code, price, category) for invoices |
| **Client Types** | `/client_types/` | Individual / Business / Guest / custom types |
| **Error Logs** | `/error_logs/` | View & clear application error logs |

---

## Permissions

New permissions are inserted by the SQL.
Super Admin already gets them.
For other roles: go to **Roles → shield icon** and enable:
- System → customizer, error_logs
- Service → view, manage
- ClientType → view, manage

---

## No CSS file needed

Still uses Bootstrap 5 CDN from `includes/header.php`.
EOF
