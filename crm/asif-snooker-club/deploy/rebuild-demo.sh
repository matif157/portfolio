#!/usr/bin/env bash
# Rebuilds the sanitized demo dataset from the local dev database and exports
# the deployable SQL dump (deploy/asif-snooker-club-demo.sql).
#
# Requires local MariaDB:
#   port 3306 -> dev DB  asif_snooker_club  (user asif_app / asif_secret)
#   port 3307 -> scratch DB engine (root, no password, datadir ~/.crm4-mariadb/data)
set -euo pipefail

DEV_MYSQL="mysql -h 127.0.0.1 -P 3306 -uasif_app -pasif_secret"
DEV_DB="asif_snooker_club"
SCRATCH_MYSQL="mysql -h 127.0.0.1 -P 3307 -uroot"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "==> Creating fresh demo database"
$SCRATCH_MYSQL -e "DROP DATABASE IF EXISTS asif_demo; CREATE DATABASE asif_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "==> Copying dev database (structure + data)"
mysqldump -h 127.0.0.1 -P 3306 -uasif_app -pasif_secret "$DEV_DB" --skip-lock-tables --no-tablespaces 2>/dev/null \
  | $SCRATCH_MYSQL asif_demo 2>/dev/null

echo "==> Sanitizing PII (people, phones, emails, camera URLs, audit log)"
HASH=$(cd "$SCRIPT_DIR/.." && php -r "echo password_hash('demo12345', PASSWORD_ARGON2ID);")
sed "s|\$HASH|$HASH|" "$SCRIPT_DIR/sanitize.sql" | $SCRATCH_MYSQL asif_demo

echo "==> Generating realistic 60-day activity"
(cd "$SCRIPT_DIR" && python3 gen_demo.py >/dev/null && $SCRATCH_MYSQL asif_demo < /tmp/demo_extras.sql)

echo "==> Fixing tournament references"
$SCRATCH_MYSQL asif_demo << 'SQL'
UPDATE tournament_matches m JOIN (SELECT seed,id FROM tournament_players) p ON p.seed=m.winner_id SET m.winner_id=p.id;
UPDATE tournaments SET champion_id=(SELECT customer_id FROM tournament_players WHERE status='champion') WHERE id=1;
SQL

echo "==> Exporting deployable dump (no CREATE DATABASE, import into any db name)"
mysqldump -h 127.0.0.1 -P 3307 -uroot asif_demo --no-tablespaces --skip-lock-tables --no-create-db 2>/dev/null \
  > "$SCRIPT_DIR/asif-snooker-club-demo.sql"

echo "==> Done. Re-import test:"
$SCRATCH_MYSQL -e "DROP DATABASE IF EXISTS asif_reimport_test; CREATE DATABASE asif_reimport_test;" 2>/dev/null || true
$SCRATCH_MYSQL asif_reimport_test < "$SCRIPT_DIR/asif-snooker-club-demo.sql" 2>/dev/null || true
$SCRATCH_MYSQL -N -e "SELECT CONCAT('  sessions=',COUNT(*),' customers=', (SELECT COUNT(*) FROM asif_reimport_test.customers)) FROM asif_reimport_test.sessions;" 2>/dev/null || true
$SCRATCH_MYSQL -e "DROP DATABASE IF EXISTS asif_reimport_test;" 2>/dev/null || true