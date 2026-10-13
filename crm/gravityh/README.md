# gravityh CRM — SPS 2040 Unified Professional Services CRM

A full-featured, modular, event-driven Professional Services CRM built for XAMPP deployment. Covers all nine SPS 2040 spec phases: foundation, core CRM, service/workflow engine, event bus & automation, integrations, AI orchestration, client portal & analytics.

**Status: All 9 phases complete · 42/42 regression tests pass · 62/62 PHP files lint clean**

---

## Quick Start

```bash
# 1. Start XAMPP (Apache + MySQL)
# 2. Import database
mysql -u root -e "CREATE DATABASE gravityh;"
mysql -u root gravityh < gravity.sql
# (apply migrations 010-023 from /migrations/ — see Installation below)

# 3. Open browser
http://localhost/gravityh/
# Default login: admin / ChangeMeImmediately!123

# 4. (Recommended) Schedule background jobs
# Windows Task Scheduler → run every 5 min:
#   php C:\xampp\htdocs\gravityh\cron.php
```

---

## Requirements

| Software | Version |
|---|---|
| XAMPP / Apache | 2.4+ |
| PHP | 7.4+ (tested on PHP 8.x) |
| MySQL / MariaDB | 5.7+/10.3+ |
| MySQL CLI | for imports (mysql.exe or mysqldump.exe) |
| Browser | Chrome, Edge, Firefox (modern) |

No external PHP frameworks or Composer dependencies required.

---

## Architecture Overview

The system follows the SPS 2040 master spec: a modular, configurable, event-driven platform where a single core serves three operating modes (Tax, Immigration, General) through configuration.

```
┌─────────────────────────────────────────────────────────┐
│                    WEB REQUEST (staff)                   │
│  index.php → login.php → dashboard.php → clients.php   │
│    → services.php → projects.php → invoices.php        │
│    → analytics.php → assistant.php → integrations.php  │
└─────────────────────────────────────────────────────────┘
         │                     │              │
         ▼                     ▼              ▼
┌────────────────┐   ┌───────────────┐   ┌──────────────┐
│  functions.php │   │  auth.php     │   │    db.php    │
│  (helpers)     │   │  (auth +     │   │  (PDO layer)  │
│                │   │   perms)     │   │               │
└────────────────┘   └───────────────┘   └──────────────┘
         │                     │              │
         ▼                     ▼              ▼
┌─────────────────────────────────────────────────────────┐
│      CORE ENGINE LAYER (procedural, plain PHP)          │
│                                                          │
│  core/event_bus.php       → durable events + consumers  │
│  core/service_engine.php  → service version freeze,     │
│                              workflow + requirements     │
│  core/integrations.php    → truthful adapter framework  │
│  core/ai_engine.php       → tier routing, permission-  │
│                              gated tools, usage ledger   │
│  core/portal.php          → client auth scope + data    │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│              DATABASE (gravityh)                         │
│  80+ tables: clients, businesses, services, workflows, │
│  requirements, documents, tasks, deadlines, invoices,  │
│  events, exceptions, outbound_queue, ai_usage, etc.    │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│   BACKGROUND (cron.php every 5 min)                      │
│  jobs_run_background() → deadline sweeps, retries,      │
│  invoice overdue sync, retention cleanup                │
│  jobs_process_outbound() → email, SMS, Teams via        │
│  truthful provider adapters                             │
└─────────────────────────────────────────────────────────┘
```

**Key design principles:**
- **Configuration-driven**: three operating modes via settings, not code forks
- **Event-driven**: all business milestones emit durable events consumed by automation, notifications, audit
- **Truthful integrations**: providers show real status (connected/testing/error/not-configured); never fake
- **Permission-scoped**: every query respects role/stage/data-scope; checked server-side
- **Idempotent**: service assignment, document upload, outbound delivery all dedupe safely
- **Audit complete**: 3-tier activity log (business → sensitive → change history)

---

## Installation

### 1. Copy files

```
C:\xampp\htdocs\gravityh\
```

### 2. Start XAMPP

Open XAMPP Control Panel and start **Apache** and **MySQL**.

### 3. Import the database

```bash
mysql -u root -e "CREATE DATABASE gravityh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root gravityh < gravity.sql
```

Or via phpMyAdmin: Import → choose `gravity.sql` → Go.

### 4. Apply migrations (in order)

```bash
mysql -u root gravityh < migrations/002_phase2_core_modules.sql
mysql -u root gravityh < migrations/003_phase2_permissions.sql
mysql -u root gravityh < migrations/004_phase3_automation.sql
mysql -u root gravityh < migrations/010_core_security.sql
mysql -u root gravityh < migrations/011_service_workflow_engine.sql
mysql -u root gravityh < migrations/012_reconcile_requirements.sql
mysql -u root gravityh < migrations/020_event_bus_exceptions.sql
mysql -u root gravityh < migrations/021_integrations.sql
mysql -u root gravityh < migrations/022_ai_orchestrator.sql
mysql -u root gravityh < migrations/023_portal_analytics.sql
```

### 5. Login

Open: `http://localhost/gravityh/`

Default admin login:

```
Username: admin
Password: ChangeMeImmediately!123
```

> After first login, set a strong password immediately.

---

## Project Structure

```
gravityh/
├── index.php              Entry point → redirects to login or dashboard
├── login.php              Login page (rate-limited, lockout)
├── logout.php             Logout (logs security event)
├── dashboard.php          Staff dashboard with role-based widgets
├── clients.php            Client management (create/edit/list/profile)
├── businesses.php         Business/organization management
├── contacts.php           Contact management
├── leads.php              Lead management + conversion
├── services.php           Service definitions + client assignment
├── projects.php           Projects & cases (operational + authority-based)
├── workflows.php          Workflow template & instance management
├── tasks.php              Task assignment & tracking
├── deadlines.php          Deadline engine
├── documents.php          Document management
├── invoices.php           Invoice lifecycle
├── payments.php           Payment processing & allocation
├── analytics.php          Operational analytics (§33 question set)
├── reports.php            Financial reports
├── activities.php         Activity/audit log (3-stage sensitivity)
├── notifications.php      In-app notification center
├── customizer.php         Module enable/disable + custom fields
├── themes.php             Themes & role-specific layouts
├── settings.php           System settings (general, deployment, API keys)
├── automation.php         Visual automation rule builder/editor
├── ai_service.php         Legacy AI transport (OpenAI/Anthropic/Google/Ollama)
├── assistant.php          AI assistant console (NL → operations + approvals)
├── integrations.php       Integration Center UI (truthful status grid)
├── exceptions.php         Exception engine UI (categories × states)
├── portal_login.php       Client portal login
├── portal_logout.php      Client portal logout
├── portal_index.php       Client portal dashboard
├── global_search.php      Global search endpoint
├── cron.php               Background job runner (CLI or admin-triggered)
├── run_automation.php     Legacy automation trigger
├── config.php             Application configuration & constants
├── db.php                 PDO database layer (singleton + helpers)
├── auth.php               Auth, permissions, security events, lockout
├── functions.php          Core helper functions (2000+ lines)
├── header.php             Shared UI scaffolding (header/sidebar)
├── footer.php             Shared footer
├── core/                  Core engine layer
│   ├── event_bus.php      Durable event bus + consumers + background jobs
│   ├── service_engine.php Version-freeze service delivery engine
│   ├── integrations.php   Adapter framework + outbound queue
│   ├── ai_engine.php      Tier routing + permission-gated tools + ledger
│   └── portal.php         Client portal auth scope + scoped data queries
├── migrations/            Additive SQL migrations (002–023)
├── tests/                 Regression test suite (42 assertions)
│   ├── run.php            CLI test runner
│   ├── harness.php        Test harness + bootstrap
│   └── suites/            6 test suites
├── gravity.sql            Master database schema
└── README.md              This file
```

---

## Operating Modes

The same core platform serves three professional-service modes. Mode-specific behavior is configuration-driven via service categories and workflow templates — no code forks.

| Mode | Typical Objects | Key Workflows |
|---|---|---|
| **TAX** | Tax profile, tax year, filing type, income, deductions | intake → eligibility → year → questions → docs → AI org → prep → calc → review → approval → filing → response → renewal |
| **IMMIGRATION** | Case type, beneficiary, dependents, evidence | intake → case → eligibility → family → evidence → collection → AI org → completeness → review → approval → submission → receipt → deadlines → response |
| **GENERAL** | Bookkeeping, EIN, sales tax, accounting | Configured via service templates — same engines (requirements, workflow, tasks, docs, finance) |

---

## Client Lifecycle

```
CLIENT → BUSINESS/ORG → SERVICE/CASE → REQUIREMENTS → INFO COLLECTION
  → DOCUMENT COLLECTION → AI UNDERSTANDING → RULES/VALIDATION
  → WORKFLOW INSTANCE → TASKS/DEADLINES → COMMUNICATION
  → CALCULATIONS/PREPARATION → REVIEW → HUMAN APPROVAL
  → EXTERNAL ACTION/SUBMISSION → RESPONSE → EXCEPTION HANDLING
  → COMPLETION → FOLLOW-UP → RENEWAL
```

When a service is assigned, the system automatically:
1. Creates `client_services` assignment (idempotent)
2. Freezes service version snapshot (immutable)
3. Instantiates workflow from template
4. Creates step executions
5. Generates requirements (deduped)
6. Creates requirement deadlines
7. Logs `SERVICE_STARTED` + emits durable event
8. Triggers automation rules + AI recommendations

---

## Modules & Features

### Core CRM
- Client & Business Management: multiple businesses per client, custom fields, portal account auto-creation
- Project/Case Management: operational + authority-based cases
- Document Management: upload, classify, validate, AI-intelligence
- Invoice & Payment Processing: lifecycle management, payment allocation

### Service Engine (Phase 4)
- Service definitions with categories, pricing models, eligibility rules
- Version-freezing: immutable snapshots at assignment time
- Workflow templates with step definitions
- Requirements engine (13 states) with fulfillment matrix
- Deadlines engine (8 types) with warn-days and escalation

### Event Bus & Automation (Phase 5)
- 21 canonical event types: `CLIENT_CREATED`, `SERVICE_STARTED`, `DOCUMENT_UPLOADED`, `WORKFLOW_ADVANCED`, `DEADLINE_BREACHED`, `TASK_ASSIGNED`, `INVOICE_OVERDUE`, `INTEGRATION_FAILURE`, `EXCEPTION_OPENED`, `AI_DECISION_MADE`, ...
- Durable persistence with retry/backoff + dead-letter + retention cleanup
- Consumer registry with wildcard support (`client.*`, `*.uploaded`)
- Automation bridge → existing rules engine (no behavior change)
- Exception auto-opening from failure-class events
- Background jobs: deadline sweeps, invoice overdue sync, event retries, retention cleanup

### Integrations (Phase 6)
Every integration shows **truthful status** — connected only after real passing test. Never fakes "Connected".

| Provider | Capabilities | Status Check |
|---|---|---|
| SMTP / Email | Send emails, inbound routing | Socket-level test |
| Twilio | SMS, Voice calls | API auth test |
| WhatsApp Business | WhatsApp messaging | Config validation |
| Microsoft Teams | Webhook notifications (staff ops) | Webhook POST test |
| Stripe | Payment processing | Config validation |
| QuickBooks | Invoice sync (idempotent) | Config validation |
| OneDrive | Folder/file sync, doc matching | Not configured (OAuth pending) |
| LinkedIn | Authorized API only | Not configured (OAuth pending) |
| Tawk.to | Live chat widget | Config validation |

**Outbound queue**: all external notifications go through `outbound_queue` with idempotency keys, bounded retries (max 5), and abandonment → auto-opens `integration_failure` exception.

### AI Orchestrator (Phase 7)
- Multi-provider: OpenAI, Anthropic, Google, Ollama, OpenRouter
- Tier routing: fast (classification/summaries) vs strong (complex reasoning)
- Permission-gated tools: 5 tools checked server-side before execution
- Decision audit (`ai_decisions` table): provider, model, confidence, record, approval status
- Usage/cost ledger (`ai_usage` table): tokens, USD cost, provider, model, tier, duration
- Approval gates: AI-proposed actions with `needs_approval=1` → admin queue
- AI document intelligence: classify, extract, detect conflicts/duplicates/expirations

### Client Portal (Phase 8)
- Separate auth scope, fully isolated from staff session
- Lockout after 5 failed attempts (15-min lockout)
- Scope isolation: portal users only see their own client data
- Document upload → emits `DOCUMENT_UPLOADED` event → becomes `communications` on client timeline
- Messaging thread → staff-visible communication

### Operational Analytics (Phase 8)
Permission-scoped dashboard answering §33 questions: busiest services, missing requirements, blocked work, overdue invoices, automation/integration failures, exceptions by category, staff workload, revenue by service, AI spend, delivery time.

### Notification Center
Header bell with unread count. Sources: tasks, deadlines, approvals, payments, exceptions, workflow changes, integration errors, AI recommendations. All via event bus bridge.

### Automation Rule Engine
`WHEN event IF condition THEN action` with visual editor. All rules fire via event bus bridge to existing `trigger_automation()`.

### Exception Engine
9 categories × 6 states. Auto-opened from bus events (deadline breaches, integration failures, AI low confidence). Severities: info, warning, critical.

---

## AI Assistant

Access via **AI Assistant** menu (requires `ai.view` permission).

### What it can do:
- Answer questions about clients, services, invoices, deadlines
- "Show clients missing March bank statements"
- "Start bookkeeping for ABC LLC" (requires approval)
- "Which invoices are overdue?"
- "Summarize this client"
- "Why is this project blocked?"

### Flow:
1. NL query → AI response + optional tool calls
2. Tool calls permission-checked server-side
3. Actions requiring approval → `ai_decisions` table with `needs_approval=1`
4. Admin approves/rejects in console → decision executed + audited
5. Everything logged to `ai_usage` with tokens/cost/provider/model/duration

---

## Integrations

### Configuring providers
1. **Settings → API / Integrations**
2. Select provider, enter credentials
3. Click **Test** (real connectivity check)
4. Status updates truthfully: Connected / Error / Not Configured

### Integration Center
Via **Integrations** menu. Shows status badges, last test/sync timestamps, and actions: Test, Disconnect, Sync Now, View Logs.

### OneDrive mapping
```
CRM CLIENT → BUSINESS → SERVICE → PROJECT/CASE → ONEDRIVE FOLDER
```
External IDs stored to prevent duplicates. New file → match doc → AI classify → match requirement → advance workflow.

### Outbound queue
- Processed by `cron.php` via `jobs_process_outbound(25)`
- Idempotency keys prevent duplicates
- Retries up to `outbound_max_attempts` (default 5)
- Abandonment → auto-opens `integration_failure` exception

---

## Background Jobs (Cron)

**Schedule every 5 minutes** via Windows Task Scheduler:

```
Program:  C:\xampp\php\php.exe
Arguments: C:\xampp\htdocs\gravityh\cron.php
```

Executes:
1. `jobs_run_background()` — deadline sweeps, invoice overdue sync, event retries, retention cleanup
2. `jobs_process_outbound(25)` — queued outbound messages

**Manual**: admins can visit `http://localhost/gravityh/cron.php` (requires `settings.view`).

---

## Security

### Authentication
- Passwords hashed with `password_hash()` (bcrypt)
- Login rate-limiting + per-IP throttle (shared office IPs)
- Account lockout after 5 failed attempts (30-min default; configurable)
- Login attempts logged with IP + session metadata

### Authorization
- Role-based: `ROLE → MODULE → ACTION`
- Admin (role 1) has full access
- **All checks server-side** — frontend UI is advisory only

### Audit & Activity Logging
3-stage system:
- **Stage 1**: business activity (client edits, invoice creation, document uploads)
- **Stage 2**: sensitive events (login, lockout, permission changes, security events with severity)
- **Stage 3**: change history (field-level before/after)

Every event captures: timestamp, user, role, module, action, entity, IP, user agent, session ID, severity.

**Never logged**: passwords, tokens, API keys, session secrets.

### Data Protection
- All SQL uses PDO prepared statements
- CSRF tokens on all forms
- HTML output escaped with `htmlspecialchars()`
- File uploads: size, extension, MIME validation
- API credentials encrypted before storage
- Client portal sessions fully isolated

---

## Configuration

Core constants in `config.php`:

| Constant | Description | Default |
|---|---|---|
| `DB_HOST` | Database host | `localhost` |
| `DB_NAME` | Database name | `gravityh` |
| `DB_USER` | Database user | `root` |
| `DB_PASS` | Database password | `(empty)` |
| `APP_NAME` | Application name | `gravityh CRM` |
| `APP_VERSION` | Application version | `1.0.0` |
| `APP_TIMEZONE` | Timezone | `America/New_York` |
| `APP_CURRENCY` | Currency | `USD` |
| `SESSION_TIMEOUT` | Session timeout (sec) | `3600` |
| `UPLOAD_MAX_SIZE` | Max upload (bytes) | `5242880` (5MB) |
| `ENCRYPTION_KEY` | Encryption key | `change-this-key-in-production` |

**Settings table** (via Settings UI): AI provider/model, security thresholds, deadline grace days, retention periods.

---

## Testing

Plain-PHP regression suite:

```bash
# All suites
php C:\xampp\htdocs\gravityh\tests\run.php

# Single suite
php C:\xampp\htdocs\gravityh\tests\run.php --filter=ai

# Suites: ai, events, integrations, portal, security, workflow
```

**42 assertions · all passing.** Each suite is self-contained — builds fixtures, asserts, cleans up.

---

## Migration Reference

| Migration | Phase | Description |
|---|---|---|
| `002` | 2 | Core CRM tables (clients, businesses, services, invoices) |
| `003` | 2 | Roles, permissions, role_permissions seed |
| `004` | 3 | Automation rules, logs, workflow templates |
| `010` | 3 | Tenants, teams, login_attempts, permission stages/scopes, audit |
| `011` | 4 | Service versions, workflow templates/steps, deadlines |
| `012` | 4 | Requirements engine (states, fulfillment matrix, dedupe) |
| `020` | 5 | events table, exceptions table |
| `021` | 6 | integration_connections, integration_logs, outbound_queue |
| `022` | 7 | ai_usage, ai_decisions, tier settings |
| `023` | 8 | portal_users, analytics module |

All idempotent (`IF NOT EXISTS`, `ON DUPLICATE KEY UPDATE`).

---

## Troubleshooting

### "Database connection failed"
1. Ensure MySQL is running in XAMPP
2. Verify `gravityh` database exists
3. Check credentials in `config.php`
4. Confirm all migrations applied

### "Access denied"
1. Ensure you're logged in
2. Check role permissions in **Roles** page
3. Admins always have full access

### "Account locked"
1. 5 failed attempts locks account (30-min default)
2. Admin unlocks via **Users → Unlock**
3. Clears automatically after lock duration

### Blank page / 500 error
1. Check `logs/error.log`
2. Ensure `logs/` and `uploads/` are writable

### Background jobs not running
1. Confirm `cron.php` is scheduled every 5 min
2. Test manually: `php C:\xampp\htdocs\gravityh\cron.php`

### Integration shows "Error"
1. Verify credentials
2. Check provider status pages
3. Review logs in Integration Center

### AI Assistant errors
1. Ensure AI provider configured in Settings → API
2. Check API key validity and billing
3. Approval-required actions appear in **Pending Approvals**

---

## License

**gravityh CRM** — Professional Services CRM built to the SPS 2040 specification.

All 9 phases complete: Foundation · Core CRM · Service Engine · Event Bus · Integrations · AI Orchestrator · Portal/Analytics · Regression Harness.

**Verified: 42/42 tests pass · 62/62 PHP files lint clean · zero syntax errors.**

---

*Built with plain PHP 7.4+ on XAMPP — no external frameworks or Composer dependencies.*
