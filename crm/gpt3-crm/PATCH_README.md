# SPS CRM Critical Patch 14

This patch contains only files that need to be replaced/added on top of the latest `SPS_CRM_AI_Professional_2026_ADVANCED_REVIEWED` build.

## Fixed / added
- Fixed the confirmed `clients` INSERT placeholder/value mismatch.
- Fixed confirmed business/comment/customizer INSERT mismatches.
- Added persistent client custom-field rendering and save/update support.
- Added client comment edit + version history/audit behavior.
- Added recurring reminder metadata, edit/delete handling, and recurrence display.
- Added stage-aware Activity visibility filtering.
- Added root-level `assets/` copies so XAMPP can serve CSS/JS even when rewrite rules are unavailable.
- Added idempotent database upgrade SQL for recurrence/comment-version features.

## Existing database
Run `database/14_critical_feature_patch.sql` against the current `sps_crm` database.

## Fresh database
Use the updated root `crm.sql` (also mirrored at `database/crm.sql`).

No dependency installation is required for this patch.
