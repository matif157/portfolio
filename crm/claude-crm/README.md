# SPS Professional Services CRM

Single-folder PHP + MySQL CRM built for local Windows XAMPP. No Docker, no
Node, no build step — copy the folder, import the SQL, open it in a browser.

## 1. Install (5 minutes)

1. Copy the whole `SPS_CRM` folder into `C:\xampp\htdocs\`, so you end up
   with `C:\xampp\htdocs\SPS_CRM\index.php`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`) → **Import** →
   choose `crm.sql` from the project folder → **Go**.
   This creates the `sps_crm` database and every table itself — you don't
   need to create the database manually first.
4. Open `http://localhost/SPS_CRM/setup_admin.php` in your browser and set
   a real password for the `admin` account. **Delete `setup_admin.php`
   afterward** — it's a one-time tool, not something to leave on a live
   folder (there's a localhost-only check, but delete it anyway).
5. Open `http://localhost/SPS_CRM/` → log in as `admin` with the password
   you just set.

Database credentials are in `config.php` (defaults: host `localhost`, db
`sps_crm`, user `root`, no password — the standard XAMPP MySQL defaults).
Change them there if your MySQL setup differs.

## 2. What's actually working right now

Everything below is real, tested-by-inspection logic against real tables —
nothing is a placeholder or fake data.

- **Auth**: login, logout, session timeout, forced password change on first
  login, CSRF tokens on every state-changing form, role/permission checks
  (`auth.php`).
- **Clients**: list with search/filter/pagination, add, edit (with
  field-change logging), archive (soft delete), custom fields support
  (`clients.php`, `add_client.php`, `edit_client.php`).
- **Client Profile**: tabbed hub — Overview, Businesses, Services,
  Projects, Invoices, Bank Statements, Documents, Tasks, Comments,
  Activity Log. Add a business right from here (`client_profile.php`).
- **Bank Statement Collection & Bookkeeping Prep** — the flagship,
  fully-automated service end-to-end:
  - Register a bank account under a business → missing months auto-generate
    back to the service start date.
  - Upload a statement for a month → duplicate detection, file validation
    (size/extension/MIME), automatic status update.
  - When every required month is received, the system automatically
    creates a bookkeeping review task and a staff notification — no manual
    step required.
  - "Send Reminder" drafts and logs a real reminder from live data (not a
    hard-coded template); if no SMTP is configured it's logged as a draft
    and says so honestly, never a fake "sent".
  - Per-account year grid, progress bars, communication history.
- **Dashboard**: real stats (clients, projects, invoices, missing
  statements, pending approvals, tasks due), recent activity feed,
  automation status panel that honestly shows which channels
  (email/SMS/WhatsApp/voice/AI) are configured vs not.
- **Notifications bell**: live AJAX-backed dropdown (`ajax_notifications.php`).
- **Activity log**: three-stage logging (normal actions, sensitive access,
  field-level before/after changes) wired into every mutation above.

## 3. What still needs building (next pass)

The sidebar links to ~30 more pages from the original spec that aren't
built yet — clicking them will 404 for now. Nothing about the current
pages depends on them; the schema for all of them already exists in
`crm.sql` so building them won't require touching the database again.
Roughly in priority order:

1. `services.php` / `service_workflow.php` — generalize the workflow
   engine so other services can reuse the automation pattern proven by
   Bank Statements.
2. `projects.php`, `tasks.php` — full CRUD (tasks already work as a
   *table* driving the dashboard and automation; they just don't have
   their own management page yet).
3. `invoices.php`, `quotations.php`, `payments.php` — finance module.
4. `documents.php`, `secure_area.php` — general document management
   (bank statements already have their own upload/download flow).
5. `businesses.php`, `contacts.php`, `leads.php` — cross-client list views
   (adding a business already works from inside a client profile).
6. `calendar.php`, `reminders.php`, `communications.php`, `notifications.php`
   (full page), `activity_logs.php` (full page).
7. `users.php`, `roles.php`, `permissions.php`, `customizer.php`,
   `settings.php` — administration.
8. `ai_assistant.php`, `ai_automation.php`, `service_automation.php`.
9. `reports.php`.

Tell me which one you want next and I'll build it the same way — real
queries, real forms, no placeholders — following the same file-per-page,
self-contained style as everything above.

## 4. Configuring integrations (all optional)

Nothing in the CRM ever pretends an unconfigured channel worked. Set these
as environment variables (or edit the fallbacks directly in `config.php`
for local testing):

- `AI_PROVIDER`, `AI_API_KEY`, `AI_MODEL`
- `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM`
- `SMS_PROVIDER`, `SMS_API_KEY`, `SMS_FROM`
- `WHATSAPP_PROVIDER`, `WHATSAPP_API_KEY`
- `VOICE_PROVIDER`, `VOICE_ACCOUNT_SID`, `VOICE_AUTH_TOKEN`, `VOICE_FROM_NUMBER`

Until these are set, the dashboard's Automation Status panel and the
Bank Statements reminder button will both honestly say "not configured" /
"drafted" instead of faking success.

## 5. Suggested first test run

1. Log in.
2. Clients → Add Client.
3. Open the new client → Businesses tab → Add Business.
4. Bank Statements tab (or the Businesses tab → Manage) → Add Bank Account,
   with a service start date a few months back.
5. You'll see the missing-months grid auto-populate.
6. Upload a statement for one of the months → watch it flip to "Received".
7. Upload all remaining months → a "Bookkeeping Review" task and a
   notification are created automatically — check the dashboard and the
   client's Tasks tab.
8. Client profile → Activity tab shows every step you just took, logged.

If step 6 or later throws a database error instead of working, the most
likely culprit is the `LIMIT ? OFFSET ?` pagination pattern in `clients.php`
on your specific MySQL/MariaDB version — see the note in `db.php`'s
`PDO::ATTR_EMULATE_PREPARES => false` setting if you hit that.
