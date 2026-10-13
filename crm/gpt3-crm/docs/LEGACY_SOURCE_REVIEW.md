# Legacy CRM & Data Review

## Supplied legacy PHP archive
- `mera_crm_complete.zip`: 24 archive entries, including 16 PHP source files, CSS, README and SQL schema.
- All 16 PHP files were syntax-checked before modernization.
- Style preserved conceptually: simple XAMPP-friendly PHP, PDO, shared helper functions, direct page-oriented modules, explanatory comments.

## Legacy schema incorporated
The modern schema keeps/extends the legacy domains: roles, users, permissions, role_permissions, clients, form_fields, invoices, invoice_items, invoice_payments and activity_logs. The modern schema adds business/client-service/workflow/task/document/communications/security/AI/automation/reporting layers.

## Professional data archive
The supplied professional data ZIP contains 22 CSV files and 1 XLSX workbook. The CSV archive contains 81,512 data rows in total. All are retained under `database/legacy_data/` for controlled staging/import.

Duplicate exports intentionally not double-imported:
- 06_Invoice_Line_Items_Duplicate.csv duplicates 05_Invoice_Line_Items.csv.
- 08_Instant_Invoices_Guest_Clients_Duplicate.csv duplicates 07_Instant_Invoices_Guest_Clients.csv.

## Data-quality findings
- Several legacy date fields contain `0000-00-00` or mixed date formats. Importers normalize invalid/unknown dates to NULL.
- Some comments contain HTML fragments such as `<br />`/`&nbsp;`; they remain raw in staging so no data is silently lost.
- Bank account staging contains usernames/passwords.
- Credit-card staging contains PAN/CVV. These sensitive values remain in the legacy staging area only and are NOT copied into the live CRM credit-card workflow. Production use requires restricted storage and controlled encryption/retention.
- The XLSX workbook is a structured member-account snapshot and is imported only into a dedicated staging table; it does not silently overwrite live client records.

## Security modernization
The legacy error handler exposed developer details and the legacy login lacked the security controls expected for production. The new project uses centralized sessions, CSRF, permissions, audit/activity separation, encrypted sensitive-value helper, private document storage, approval gates and configurable external adapters.
