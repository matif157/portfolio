# SPS CRM — Phase 1

This is the XAMPP-safe foundation phase.

## Target

Copy the folder to:

`C:\xampp\htdocs\newgpt\`

Start Apache and MySQL in XAMPP.

## Database

1. Open phpMyAdmin.
2. Import `crm.sql`.
3. The SQL creates the `gpt` database and its Phase 1 tables.
4. Default XAMPP credentials are used:
   - Host: 127.0.0.1
   - Port: 3306
   - User: root
   - Password: empty

If your MySQL password differs, edit `config.php`.

## Open

`http://localhost/newgpt/`

Health check:

`http://localhost/newgpt/health_check.php`

## Default login

Username: `admin`

Password: `ChangeMeImmediately!123`

Change the default password in a later administration phase.

## Phase 1 scope

- XAMPP-safe root-level PHP structure
- PDO/MySQL foundation
- Session authentication
- Password hashing
- CSRF protection
- Permission foundation
- Dashboard
- Client foundation
- Activity logging
- Health check
- Master initialization SQL

This phase deliberately does not create placeholder pages for modules that have not yet been implemented. Later phases extend this same structure.
