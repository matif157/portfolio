# Patch 15 — gpt3 / advanced bookkeeping operations

This patch is relative to the Advanced Reviewed CRM build.

## Deployment folder
`C:\xampp\htdocs\gpt3`

## Database
`gpt3`

## Main new functionality
- Bank account-to-business linkage and last-four display
- Actual monthly statement uploads stored in private storage
- Document records linked to statements
- Duplicate month prevention using a unique period key
- Automated missing-statement communication queue
- One-click start for the fully automated Bank Statement Collection & Monthly Bookkeeping Preparation service
- Seeded service requirements and workflow
- XAMPP/local launch helpers using the gpt3 path

Apply `database/15_gpt3_advanced_operations.sql` to an existing gpt3 database, or use the updated `database/crm.sql` for a fresh database.
