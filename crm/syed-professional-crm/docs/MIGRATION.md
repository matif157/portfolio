# CRM migration

The schema is designed to preserve external identifiers and legacy comments. Migrate in dependency order: users/organizations → clients/contacts → services/requirements → cases → documents/projects/tasks → invoices/payments → comments/notifications → audit/import history.

Do not delete source records during the first migration. Preserve legacy IDs in `external_id` fields and retain the source system reference in migration metadata.
