#!/usr/bin/env bash
# Rebuilds the sanitized SPS CRM demo dataset and exports the deployable dump
# (deploy/syed-professional-crm-demo.sql). Uses local MariaDB scratch instance:
#   port 3307 -> root, no password, datadir ~/.crm4-mariadb/data
set -euo pipefail
SCRATCH="mysql -h 127.0.0.1 -P 3307 -uroot"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(dirname "$SCRIPT_DIR")"

echo "==> Fresh demo database"
$SCRATCH -e "DROP DATABASE IF EXISTS sps_demo; CREATE DATABASE sps_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true

echo "==> Core schema (migrations/001_initial.sql without CREATE/USE header)"
sed '1,2d' "$ROOT/database/migrations/001_initial.sql" > "$SCRIPT_DIR/sps_core_schema.sql"
$SCRATCH sps_demo < "$SCRIPT_DIR/sps_core_schema.sql" 2>/dev/null || true

echo "==> Demo data (fixed seed, universal demo password)"
HASH=$(cd "$ROOT" && php -r "echo password_hash('demo12345', PASSWORD_ARGON2ID);")
(cd "$SCRIPT_DIR" && python3 gen_sps.py)
sed "s|\$HASH|$HASH|" /tmp/sps_extras.sql | $SCRATCH sps_demo 2>/dev/null || true

echo "==> Export deployable dump (no CREATE DATABASE lines)"
mysqldump -h 127.0.0.1 -P 3307 -uroot sps_demo --no-tablespaces --skip-lock-tables --no-create-db 2>/dev/null \
  > "$SCRIPT_DIR/syed-professional-crm-demo.sql"

echo "==> Re-import test"
$SCRATCH -e "DROP DATABASE IF EXISTS sps_reimport_test; CREATE DATABASE sps_reimport_test;" 2>/dev/null || true
$SCRATCH sps_reimport_test < "$SCRIPT_DIR/syed-professional-crm-demo.sql" 2>/dev/null || true
$SCRATCH -N -e "SELECT CONCAT('  clients=',(SELECT COUNT(*) FROM sps_reimport_test.clients),' invoices=',(SELECT COUNT(*) FROM sps_reimport_test.invoices),' admin email=',(SELECT email FROM sps_reimport_test.users WHERE role='Admin' LIMIT 1));" 2>/dev/null || true
$SCRATCH -e "DROP DATABASE IF EXISTS sps_reimport_test;" 2>/dev/null || true
echo "Done."