# SPS AI-Powered Professional Services CRM

A framework-light PHP 8.2+/MySQL 8 application for professional-services operations. The system is designed around **service delivery**, not just contacts and invoices.

## Implemented architecture

HTTP → Router → Controllers → Domain/Application Services → Repositories → PDO/MySQL

Cross-cutting layers include:

- RBAC and staged activity permissions
- Client/business/contact data
- Service catalog + versioned service definitions
- Service requirements and client-service lifecycle
- Generic workflow execution with required/optional steps
- Dynamic task generation
- Deadline and escalation engine
- Recurring project processing
- Invoices, quotations, payments and receivables
- Secure private documents + access logging
- Document intelligence adapter architecture
- AI operations/orchestration layer with confidence and human gates
- Rules/eligibility engine
- Exceptions and cases
- Communications history with email/SMS/WhatsApp/voice adapter boundaries
- Client portal architecture
- Approvals and professional review gates
- Authority/filing adapter architecture that **does not fake submissions**
- Tax profile/income/deduction/calculation foundation
- Knowledge sources and traceable service-research records
- External integration adapter architecture
- Reports and operational dashboards
- Audit/activity trail

## Service catalog

`database/catalog/services.csv` contains the supplied SPS service list. The generated catalog definitions under `config/services/catalog/` turn each supplied service into a configurable operational definition with workflow stages, risk level, approval level and automation capabilities.

Prices are seeded from the supplied catalog where a price was present; blank prices remain `NULL` and should be configured by the firm.

## Safe automation model

The platform is deliberately **maximum safe automation**, not uncontrolled automation. Tax filings, regulatory submissions, legally significant declarations and other configured high-risk actions can be prepared and validated automatically, but an external submission is blocked until the configured approval gate is satisfied and a real official integration is configured.

The external adapters currently return `not_configured` rather than pretending an authority, payment provider, telephony provider or AI provider was contacted.

## Install on XAMPP

1. Install PHP 8.2+ and MySQL 8.x.
2. Create a database such as `sps_crm`.
3. Copy `.env.example` to `.env` and set database credentials.
4. Point Apache's document root to the project's `public/` directory (or use an Apache alias/rewrite setup).
5. Run:

```bash
php database/migrations/migrate.php
php database/seeders/seed.php
```

Alternatively import `database/schema.sql` into phpMyAdmin and then run the seeder.

6. Open `/login`.
7. Seed login: `admin` / `ChangeMeImmediately!123`. Change it immediately.

## Cron

Run `php cron/run.php` periodically (every minute is appropriate for a development environment). It processes recurring projects, overdue invoices and reminder work.

## External configuration still required

The project intentionally does not invent credentials or pretend integrations exist. Production configuration is still required for whichever services the firm selects:

- OpenAI/another approved AI provider
- SMTP/email provider
- SMS provider
- WhatsApp Business provider
- Voice/telephony provider
- Stripe or another payment gateway
- Official government/authority APIs where available
- OCR provider
- E-signature provider
- Cloud storage provider
- Accounting/calendar integrations

Each integration has an adapter boundary so these can be connected without rewriting CRM business logic.

## Validation performed in this build

- PHP syntax scan: all generated PHP files pass `php -l`.
- File/reference review was performed across routes, controllers, services, repositories, views, migrations and seeders.
- A live MySQL migration could **not** be executed in this development environment because the PHP `pdo_mysql` driver/MySQL client are not installed here. This is an environment limitation, not a claimed migration success.

## Security notes

- Passwords use `password_hash`/`password_verify`.
- Sessions regenerate on authentication.
- Session cookies are HttpOnly and SameSite=Lax.
- CSRF tokens are used on state-changing forms.
- SQL uses PDO prepared statements in application code.
- Private documents are stored outside the public directory.
- Document access is logged.
- AI decisions record confidence/reason/source/rules and can require human review.
- External filing is blocked when approval/integration prerequisites are missing.

This project is a substantial runnable implementation and an extensible foundation for the remaining jurisdiction-specific professional rules and real provider credentials.

## Quick local test (no Apache configuration required)

From Windows, double-click `start-local.bat`. It automatically changes into `public/` and starts PHP with `public/index.php` as the front-controller router. Then open:

`http://127.0.0.1:8000/login`

Do **not** run `php -S localhost:8000` from the project root; that serves the root directory as a static document root and requests such as `/login` can return `404 No such file or directory`.

If `php` is not recognized, use the XAMPP PHP executable instead:

`C:\xampp\php\php.exe -S 127.0.0.1:8000 index.php`

For Apache/XAMPP production-style local hosting, point the VirtualHost `DocumentRoot` at this project's `public/` directory and enable `AllowOverride All` for that directory.
