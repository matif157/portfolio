# mera_crm — Phase 5

**Invoice PDF Template · Improved Clients · Customizer Integration**

## What's included

| File | Action |
|------|--------|
| `invoices/print.php` | New — Syed Pro style invoice print/PDF |
| `clients/create.php` | Replace — Client Types + Business Details |
| `clients/edit.php` | Replace — Client Types + Business Details |
| `includes/settings_helper.php` | New — loads Customizer company settings |
| `INSTALL.txt` | Step-by-step install instructions |

## Invoice template matches your sample

- Logo area (SP / company name)
- Invoice #, By (staff), Created, Due, UNPAID/PAID badge
- From: company (from Customizer)
- To: client name, business, address, phone, email
- Service / Price table (gold header)
- Sub Total / Grand Total
- Description section
- "Thank you for choosing..." footer

Company address/phone/email on the PDF come from **Customizer** settings.

## Clients improved

- Client Type dropdown (Individual, Business, Guest...)
- Business Details section (president, bus address, client since)
- Linked to `client_types` + `business_details` tables

## Install

1. Copy the 4 files into matching folders of your live CRM
2. Follow `INSTALL.txt` (add print button + require settings_helper)
3. Open any invoice → Print / PDF

No new SQL required if you already ran `update_tables.sql` from the previous update package.
If client types dropdown is empty, run that SQL first.
