# SPS CRM — Phase 2

Phase 2 directly extends Phase 1 and keeps the same root-folder/XAMPP model.

## Installation
Copy/replace the contents into:
`C:\xampp\htdocs\newgpt\`

Start Apache + MySQL, sign in, then open:
`http://localhost/newgpt/upgrade_phase2.php`

Click **Apply Phase 2 Database Upgrade** once.

The migration is additive and does not drop Phase 1 data.

## Added functionality
- Expanded client management
- Client profile
- Client comments
- Business creation/listing
- Task creation/completion
- Calendar events and recurrence fields
- Invoice creation/listing
- Outstanding balance
- Non-destructive Phase 2 database migration
- Phase-aware dashboard/health checks

No Node, npm, Docker, virtual host or framework was introduced.

External AI/OCR/SMS/WhatsApp/voice integrations are intentionally not faked.
