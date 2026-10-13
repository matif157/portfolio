# SPS CRM Architecture

## Application layers
- Controllers: HTTP orchestration only.
- Services: business rules and transactions.
- Repositories: database persistence.
- Models/DTOs: typed domain data.
- Policies: authorization.
- Validators: request validation.
- Views: presentation.
- Support: database, auth, CSRF, routing, response, logging.
- Jobs: scheduled automation.
- API: JSON endpoints using the same service layer.

## Core relationships
Client -> Businesses -> Projects -> Service -> Workflow execution.
Client -> Quotations/Invoices/Payments.
Client -> Documents/Reminders/Tasks/Activities.
Service -> Workflow Version -> Steps -> Project Workflow Execution.
Recurring Project -> Project Series -> child renewal projects.

## Workflow semantics
Steps are required, optional or conditional. Required steps block completion unless completed; optional steps may be skipped; conditional steps are evaluated by configuration. Automatic steps can react to document upload, invoice creation and payment events.

## Activity stages
Stage 1: normal business activity.
Stage 2: authentication/billing/secure-area access.
Stage 3: edits with before/after change sets.
Permissions determine visibility. Secrets are never written to change logs.

## SaaS readiness
Major tables carry tenant_id so organization isolation can be enforced later without changing the domain model.
