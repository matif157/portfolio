# SPS CRM — Phase 3

Phase 3 converts the service concept into a real workflow/automation foundation.

## Install

1. Copy/replace the ZIP contents into:
   `C:\xampp\htdocs\newgpt\`
2. Start Apache and MySQL.
3. Make sure Phase 2 is installed.
4. Open:
   `http://localhost/newgpt/upgrade_phase3.php`
5. Click **Apply Phase 3 Database Upgrade**.

The migration is additive and does not reset previous CRM data.

## Phase 3 functionality

### Service workflow engine
- Client service instances
- Workflow step instances
- Active/current step tracking
- Step completion
- Automatic progression to the next step
- Service completion when all required workflow steps are complete

### Bank Statement Collection & Monthly Bookkeeping Preparation
- Multiple bank accounts per client
- Optional business link
- Account last-four tracking
- Automatic 12-month checklist for a selected year
- Missing/uploaded/validated/rejected states
- PDF/JPG/PNG upload handling
- 10 MB upload cap
- MIME validation
- Random stored filenames
- Document metadata
- Activity records
- User notifications

### Automation
- Automation rules
- Automation logs
- Visible automation control center
- Bank statement scan
- Missing-month detection
- No fake external email/SMS/voice/OCR success

### Notifications
- User notification inbox
- Read/unread state
- Mark individual notification read
- Mark all read

## Important

Phase 3 deliberately does not pretend that OCR, AI, email, SMS, WhatsApp, voice or filing integrations are active without providers and credentials. Those are integration phases.
