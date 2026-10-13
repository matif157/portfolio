# Syed Professional Services CRM (mera_crm)

Clean, beginner-friendly PHP + MySQL CRM built step-by-step for XAMPP.

## Requirements
- XAMPP (Apache + MySQL)
- PHP 7.4 or higher
- Browser

## Installation Steps

1. **Copy the project**
   - Extract this ZIP
   - Copy the `mera_crm` folder into:
     ```
     C:\xampp\htdocs\
     ```

2. **Create Database**
   - Open phpMyAdmin → http://localhost/phpmyadmin
   - Create a new database named: `mera_crm`
   - Go to Import tab
   - Choose file: `database/schema.sql`
   - Click Go

3. **Start XAMPP**
   - Start Apache
   - Start MySQL

4. **Open the CRM**
   - Go to: http://localhost/mera_crm/
   - Login with:
     - Username: `admin`
     - Password: `admin123`

## Default Admin
- Username: admin
- Password: admin123
- Role: Super Admin

## Included Features

### Authentication
- Secure login with password hashing
- Session based
- Logout with activity log

### Clients
- List with search & filter
- Add / Edit / View
- Soft delete (trash)
- Client types

### Invoices
- Create invoice
- View professional style invoice
- Add payments
- Auto status (Unpaid / Partial / Paid)
- Email button (mailto)

### Activity Logs
- Structured JSON logging
- Filter by module & action
- Who did what and when

### Users & Roles
- Users list
- Roles table
- Permissions table
- Role-permission mapping (ready for expansion)

### System
- Global error handler (clean errors)
- Helper functions
- Prepared statements (SQL injection safe)
- Soft delete pattern

## Folder Structure

```
mera_crm/
├── assets/
│   └── css/
│       └── style.css
├── database/
│   └── schema.sql
├── docs/
│   └── README.md
├── index.php              (Login)
├── log_in.php             (Config + DB connection)
├── functions.php          (Helpers + activity log)
├── error_handler.php
├── dashboard.php
├── clients.php
├── add_client.php
├── edit_client.php
├── view_client.php
├── invoices.php
├── add_invoice.php
├── view_invoice.php
├── add_payment.php
├── activity_logs.php
├── users.php
└── logout.php
```

## Next Steps You Can Add Later
- Full Role & Permission checks on every page
- Advanced Customizer (form fields)
- PDF generation for invoices
- Reports module
- Multi-business on one client
- Backup & Restore

## Notes for Beginners
- Every important file has heavy comments explaining WHY and HOW
- Always include `log_in.php` first on protected pages
- Always use prepared statements
- Never store plain text passwords

---
Built with care for Muhammad Atif  
Syed Professional Services CRM
