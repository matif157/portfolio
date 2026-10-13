# Attendance Management System

A PHP-based attendance manager for ZKTeco / ESSL / Granding-family biometric devices that speak the ZKTeco TCP protocol on port 4370.

Built and verified against device `192.168.15.76` (ZKTeco-compatible, comm_key=0). The driver is hand-rolled — no Composer dependency needed for device I/O.

## Tech stack

| Layer    | Stack |
|----------|-------|
| Backend  | PHP 8.1+ (PDO, MySQL/MariaDB) |
| Frontend | HTML5, modern CSS (Grid/Flex), Vanilla JS |
| Icons    | Lucide (CDN) |
| Charts   | Chart.js 4 (CDN) |
| Server   | XAMPP / Apache |

## Detected device

Phase 1 probe results, recorded against the live device:

| Field            | Value                                |
|------------------|--------------------------------------|
| IP               | 192.168.15.76                         |
| Open ports       | 4370/tcp                              |
| Protocol         | ZKTeco-compatible (TCP frame magic `5050827d`) |
| Brand class      | ZKTeco / ESSL / Granding family       |
| comm_key         | 0 (default)                           |
| Verified flows   | Connect + AUTH + GetTime + GetUsers + GetAttendanceLogs |

## XAMPP setup

1. **Place the project** under XAMPP's `htdocs`:
   ```
   c:\xampp\htdocs\attendance-system\
   ```
   (this project is already located at `c:\xampp\htdocs\new\attendance-system\`; either move it or adjust paths in the asset URLs from `/attendance-system/...` to `/new/attendance-system/...`).

2. **Start Apache and MySQL** from XAMPP Control Panel.

3. **Import the database**. From a terminal:
   ```bash
   mysql -u root < c:\xampp\htdocs\new\attendance-system\database.sql
   ```
   Or use phpMyAdmin → Import → upload `database.sql`.

4. **(Optional) Composer autoload**. Composer isn't needed for the device protocol but enables PSR-4 autoload:
   ```bash
   composer install
   ```

5. **Open in browser**:
   ```
   http://localhost/new/attendance-system/
   ```
   (path matches the project's current location; rename `new/` to `attendance-system/` to drop the prefix.)

6. **Sign in** with the seeded admin account:
   - Username: `admin`
   - Password: `admin123`

## Project layout

```
attendance-system/
├── config/
│   ├── database.php       PDO singleton (host/user/db in constants)
│   └── device.php         DeviceConfig loader (IP/port/comm_key in DB)
├── classes/
│   ├── ZKDevice.php       Hand-rolled ZKTeco TCP/4370 driver
│   ├── Auth.php           Session + CSRF + bcrypt
│   ├── Employee.php       Employee CRUD + reconciliation
│   └── Attendance.php     Punch logs, classification, reports
├── api/
│   ├── _bootstrap.php
│   ├── device_status.php           — poll ping status
│   ├── fetch_live_logs.php         — incremental log feed (+ optional ?sync=1)
│   ├── sync_attendance.php         — full sync from device
│   ├── get_users.php               — reconcile users device→DB
│   ├── add_employee_to_device.php  — upsert local + push to device
│   ├── delete_employee.php         — delete from DB + device
│   ├── manual_punch.php            — manual entry (source=manual)
│   ├── restart_device.php
│   ├── sync_device_time.php        — push PC clock → device
│   ├── clear_device_logs.php       — wipe device's attendance buffer
│   ├── test_connection.php         — settings page connectivity test
│   ├── save_settings.php           — persist device config + office timings
│   ├── dashboard_stats.php         — KPI + hourly buckets
│   └── export_csv.php              — CSV download
├── views/
│   ├── login.php
│   ├── dashboard.php
│   ├── employees.php
│   ├── attendance.php
│   ├── reports.php
│   └── settings.php
├── partials/  head.php, sidebar.php, topbar.php
├── assets/
│   ├── css/style.css
│   └── js/  app.js, dashboard.js, employees.js, attendance.js, settings.js
├── logs/      device.log (created automatically)
├── index.php  Front controller
├── test_connection.php  Standalone CLI smoke test
├── composer.json
└── database.sql
```

## CLI smoke test

Verify the device is talking before using the UI:

```bash
php test_connection.php 192.168.15.76 4370 0
```

Expected output (working state):
```
[1/5] Ping ............ OK
[2/5] CMD_CONNECT + auth OK
[3/5] CMD_GET_TIME ..... 2026-05-19 23:43:22
[4/5] getUsers() — first 5
    fetched 1 user(s)
[5/5] getAttendanceLogs() — last 5
    fetched 2 log(s)
```

## How the live feed works

* `dashboard.js` polls `api/fetch_live_logs.php?after=<id>` every 7 seconds — DB-only, fast.
* Every 30 seconds it calls `fetch_live_logs.php?after=<id>&sync=1` — opens a socket to the device, ingests only punches not already stored (see *Punch review* below), and returns the delta.
* New rows fade in via the CSS `@keyframes fade-in` rule on `tr.new`.
* The device-status pill polls `api/device_status.php` every 10 seconds — TCP ping only, no auth.

## Punch review — why wrong entries no longer come back

The device never forgets: every sync it hands back its whole buffer, not just what
is new. So "already imported" has to be decided here, and it has to survive an
admin correcting or removing a punch.

* `attendance_logs.device_punch_time` stores the timestamp **exactly as the device
  reported it** and is never edited. `UNIQUE (device_user_id, device_punch_time)`
  is the dedup anchor, so a corrected or crossed-off punch keeps blocking its own
  original from being re-imported. `punch_time` is the effective time and is free
  to be edited; `(device_user_id, punch_time)` is only an index, not a unique key,
  so a correction may land on the same minute as the row it replaces.
* `review_status` is `unreviewed` / `approved` (tick) / `rejected` (cross). A
  **rejected** punch stays in the table — that row is what stops the re-import —
  but is excluded from every count, report, working-hours figure and payroll
  total. The admin crosses the bad punch and adds the real one via *Manual punch*.
* Deleting a device punch therefore **crosses it off** instead of removing it;
  only manual entries are deleted outright. `api/delete_punch.php` reports which
  happened via `outcome`.
* Every sync records the device clock and its skew against the server in
  `sync_history`, and warns when the gap exceeds
  `settings.clock_drift_tolerance_seconds` (default 120) — a drifted clock is what
  produces wrong timestamps in the first place. Fix it with Settings → *Sync clock*.

Two CLI tools support this, both idempotent:

```powershell
# structure only: adds the columns, the unique anchor and the review indexes
C:\xampp\php\php.exe tools\migrate_punch_review.php

# data repair: removes duplicate rows left by syncs that ran without an anchor
C:\xampp\php\php.exe tools\dedupe_attendance_logs.php          # dry run, reports only
C:\xampp\php\php.exe tools\dedupe_attendance_logs.php --apply  # backs up first, then deletes
```

`--apply` copies the whole table to `attendance_logs_backup_<timestamp>` before
deleting anything, and keeps one row per punch — preferring a reviewed or
hand-corrected row, then an anchored one, then a manual one, then the oldest.
Neither tool touches the device's own buffer (that is Settings → *Clear device logs*).

## ZKTeco protocol notes

* TCP frame:`[50 50 82 7d][payload_size:LE32][payload]`
* Payload header: `[cmd:LE16][chksum:LE16][session:LE16][reply:LE16][data]`
* Checksum: 16-bit ones'-complement of LE16-word sum (folded carry)
* Auth: bit-reverse `comm_key`, XOR with `session_id`, XOR bytes with `"ZKSO"`, word-swap, then ticks transform (`ticks=50` works on this firmware)
* Bulk read for **attendance**: send `CMD_ATTLOG_RRQ` (13) directly → device replies `CMD_PREPARE_DATA` (1500) with total size → stream of `CMD_DATA` (1501) frames → `CMD_ACK_OK` (2000) terminates
* Bulk read for **users**: send `CMD_DATA_WRRQ` (1503) with envelope `[01][CMD_USERTEMP_RRQ:LE16][FCT_USER=5:LE32][ext:LE32]` → device returns data inline in `CMD_ACK_OK` or via streamed `CMD_DATA` frames

Both paths are implemented; this device required different paths for users vs. attendance (verified empirically).

## Troubleshooting

| Symptom | Cause / Fix |
|---|---|
| `CMD_ACK_UNAUTH` in logs/device.log | Wrong `comm_key`. Set via Settings page or directly in `device_config.password`. |
| `device_unreachable` 503 from API | Device offline / network blocked / firewall on port 4370. Run `Test-NetConnection -ComputerName <ip> -Port 4370`. |
| getUsers returns 0 | Device truly has no enrolled users, or firmware uses a non-standard record layout. Inspect raw bytes with the protocol section above. |
| Time on records looks wrong | The device clock drifted. Settings → "Sync clock", then cross off the affected punches (✗) and add the real times with *Manual punch*. |
| The same punches keep reappearing after every sync | The device re-sends its whole buffer; dedup needs `device_punch_time` populated. Run `tools/dedupe_attendance_logs.php --apply`, then `tools/migrate_punch_review.php`. |
| Login page loops back | PHP session storage not writable; check `php.ini` `session.save_path`. |
| Web panel URL is 404 | Project path is `htdocs/new/attendance-system/`. Either rename to `attendance-system/` at root, or adjust the asset paths in `partials/head.php` and the views. |

## Security checklist

- All DB writes use PDO prepared statements.
- All state-changing API endpoints require a CSRF token (`Auth::requireCsrf()`).
- Sessions are HTTP-only, SameSite=Lax, regenerated on login.
- Session TTL: 1 hour idle.
- Bcrypt for the admin password (cost 10).
- Every device socket call wrapped in try/catch; device protocol exchanges logged to `logs/device.log`.

## Changing the seed admin password

```php
php -r "echo password_hash('YourNewPassword', PASSWORD_BCRYPT) . PHP_EOL;"
```
Then update the `password_hash` field in the `users` row.
