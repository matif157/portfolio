<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GRAVITY CRM - MANUAL ADMIN / PASSWORD SETUP TOOL
|--------------------------------------------------------------------------
|
| Roman Urdu guide:
|
| Ye temporary file tab use karein jab Gravity CRM ka admin login kaam
| na kar raha ho, admin password reset karna ho, ya admin account banana ho.
|
| IMPORTANT:
| 1) Is file ko sirf LOCAL XAMPP par use karein.
| 2) Neeche CONFIGURATION section mein apni actual database information
|    check/change karein.
| 3) File ko Gravity CRM ke ROOT folder mein rakhein.
| 4) Example:
|
|    C:\xampp\htdocs\gravity\setup_admin.php
|
| 5) Browser mein open karein:
|
|    http://localhost/gravity/setup_admin.php?key=SETUP-ADMIN-2026
|
| 6) Setup complete hone ke baad is file ko DELETE kar dein.
|
| Ye script password ko plaintext mein database mein save nahi karti.
| PHP password_hash() use hota hai.
|
|--------------------------------------------------------------------------
| CONFIGURATION - SAB SE ZAROORI PART
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| 1) DATABASE NAME
|--------------------------------------------------------------------------
|
| Roman Urdu:
| phpMyAdmin khol kar left side mein jo database aapke Gravity CRM ka hai,
| uska EXACT naam yahan likhein.
|
| Example:
| const DB_NAME = 'gravity';
|
| Agar database ka naam "gravity_crm" hai:
| const DB_NAME = 'gravity_crm';
|
| IMPORTANT: database name folder name se zaroori nahi same ho.
*/
const DB_NAME = 'CHANGE_THIS_DATABASE_NAME';


/*
|--------------------------------------------------------------------------
| 2) DATABASE HOST
|--------------------------------------------------------------------------
|
| Normal XAMPP mein isay change na karein.
*/
const DB_HOST = '127.0.0.1';


/*
|--------------------------------------------------------------------------
| 3) DATABASE PORT
|--------------------------------------------------------------------------
|
| Normal XAMPP MySQL/MariaDB:
| 3306
|
| Agar aapne MySQL ka port change kiya hai tab hi change karein.
*/
const DB_PORT = '3306';


/*
|--------------------------------------------------------------------------
| 4) DATABASE USERNAME
|--------------------------------------------------------------------------
|
| Fresh/default XAMPP mein aam tor par:
| root
*/
const DB_USER = 'root';


/*
|--------------------------------------------------------------------------
| 5) DATABASE PASSWORD
|--------------------------------------------------------------------------
|
| Agar XAMPP MySQL password blank hai:
|
| const DB_PASS = '';
|
| Agar aapne root password set kiya hua hai:
|
| const DB_PASS = 'YOUR_MYSQL_PASSWORD';
*/
const DB_PASS = '';


/*
|--------------------------------------------------------------------------
| 6) USERS TABLE
|--------------------------------------------------------------------------
|
| Normal CRM mein:
| users
|
| Agar actual database mein table ka naam "admin_users" hai to:
|
| const USERS_TABLE = 'admin_users';
|
| EXACT table name use karein.
*/
const USERS_TABLE = 'users';


/*
|--------------------------------------------------------------------------
| 7) ADMIN USERNAME
|--------------------------------------------------------------------------
|
| Aapke provided instructions ke mutabiq default:
| admin
|
| Isay tab change karein jab aap kisi aur username ko reset karna chahte hain.
*/
const ADMIN_USERNAME = 'admin';


/*
|--------------------------------------------------------------------------
| 8) NEW PASSWORD
|--------------------------------------------------------------------------
|
| Ye woh password hoga jis se aap login karenge.
|
| Example:
| const NEW_PASSWORD = 'ChangeMeImmediately!123';
|
| Beginner tip:
| Strong password rakhein:
| - uppercase
| - lowercase
| - number
| - symbol
| - at least 10 characters
|
| Ye plaintext sirf is temporary setup file mein hai.
| Database mein password_hash() ka secure hash save hoga.
*/
const NEW_PASSWORD = 'ChangeMeImmediately!123';


/*
|--------------------------------------------------------------------------
| 9) FORCE PASSWORD CHANGE
|--------------------------------------------------------------------------
|
| true ka matlab:
| Agar users table mein must_change_password ya similar column milta hai,
| to script usay 1 karne ki koshish karegi.
|
| false ka matlab:
| Force password change nahi karegi.
|
| Aapke supplied login instructions mein first-login password change
| required hai, is liye TRUE rakha gaya hai.
*/
const FORCE_PASSWORD_CHANGE = true;


/*
|--------------------------------------------------------------------------
| 10) CREATE ADMIN IF USER DOES NOT EXIST
|--------------------------------------------------------------------------
|
| true:
| Admin username na mile to script create karne ki koshish karegi.
|
| false:
| Sirf existing admin ka password reset hoga.
|
| Recommended:
| true
*/
const CREATE_IF_MISSING = true;


/*
|--------------------------------------------------------------------------
| 11) TEMPORARY SECURITY KEY
|--------------------------------------------------------------------------
|
| Browser URL mein ye key deni hogi:
|
| http://localhost/gravity/setup_admin.php?key=SETUP-ADMIN-2026
|
| Aap is key ko change kar sakte hain.
|
| Example:
| const SETUP_KEY = 'MY-PRIVATE-2026';
*/
const SETUP_KEY = 'SETUP-ADMIN-2026';


/*
|--------------------------------------------------------------------------
| 12) LOGIN PAGE
|--------------------------------------------------------------------------
|
| Agar Gravity CRM ka login index.php hai to ye theek hai.
| Agar login.php hai to:
|
| const LOGIN_URL = 'login.php';
|
| Ye sirf success message ke link ke liye hai.
*/
const LOGIN_URL = 'index.php';


/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function quoteIdent(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function getTableColumns(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
         ORDER BY ORDINAL_POSITION'
    );

    $stmt->execute([$table]);

    $columns = [];

    foreach ($stmt->fetchAll() as $row) {
        $columns[$row['COLUMN_NAME']] = $row;
    }

    return $columns;
}

function pickColumn(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (isset($columns[$candidate])) {
            return $candidate;
        }
    }

    return null;
}

function isPasswordColumnSuitable(array $column): bool
{
    return in_array(
        strtolower((string)$column['DATA_TYPE']),
        ['varchar', 'char', 'text', 'tinytext', 'mediumtext'],
        true
    );
}


/*
|--------------------------------------------------------------------------
| SCRIPT START
|--------------------------------------------------------------------------
*/

$messages = [];
$errors = [];
$diagnostics = [];

$key = is_string($_GET['key'] ?? null)
    ? (string)$_GET['key']
    : '';

if (!hash_equals(SETUP_KEY, $key)) {

    $errors[] =
        'Invalid setup key. Roman Urdu: URL ke end mein ?key=SETUP-ADMIN-2026 lagana zaroori hai.';

} else {

    try {

        /*
        | DATABASE CONNECT
        */
        $dsn =
            'mysql:host=' . DB_HOST .
            ';port=' . DB_PORT .
            ';dbname=' . DB_NAME .
            ';charset=utf8mb4';

        $pdo = new PDO(
            $dsn,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $diagnostics[] =
            'Database connection OK: ' . DB_NAME;


        /*
        | USERS TABLE CHECK
        */
        $tableStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?'
        );

        $tableStmt->execute([USERS_TABLE]);

        if (!(bool)$tableStmt->fetchColumn()) {
            throw new RuntimeException(
                'Users table "' . USERS_TABLE . '" nahi mili. ' .
                'Roman Urdu: phpMyAdmin mein actual users table ka naam check karein.'
            );
        }

        $columns = getTableColumns($pdo, USERS_TABLE);

        if (!$columns) {
            throw new RuntimeException(
                'Users table ki columns read nahi ho sakin.'
            );
        }


        /*
        | USERNAME COLUMN AUTO-DETECT
        |
        | Roman Urdu:
        | Har CRM mein username column ka naam same nahi hota.
        | Script common names check karti hai.
        */
        $usernameColumn = pickColumn(
            $columns,
            [
                'username',
                'user_name',
                'login',
                'login_name',
                'email',
            ]
        );

        if (!$usernameColumn) {
            throw new RuntimeException(
                'Username column detect nahi hui. ' .
                'users table mein username/email/login column check karein.'
            );
        }


        /*
        | PASSWORD COLUMN AUTO-DETECT
        */
        $passwordColumn = pickColumn(
            $columns,
            [
                'password_hash',
                'password',
                'passwd',
                'user_password',
                'login_password',
            ]
        );

        if (!$passwordColumn) {
            throw new RuntimeException(
                'Password column detect nahi hui. ' .
                'users table mein password/password_hash jaisa column check karein.'
            );
        }

        if (!isPasswordColumnSuitable($columns[$passwordColumn])) {
            throw new RuntimeException(
                'Detected password column "' .
                $passwordColumn .
                '" text/hash storage ke liye suitable nahi lagta.'
            );
        }

        $diagnostics[] =
            'Username column detected: ' . $usernameColumn;

        $diagnostics[] =
            'Password column detected: ' . $passwordColumn;


        /*
        | OPTIONAL COLUMNS
        |
        | Agar exist karte hain to use honge.
        | Agar nahi hain to script fail nahi karegi.
        */
        $statusColumn = pickColumn(
            $columns,
            [
                'status',
                'account_status',
                'user_status',
            ]
        );

        $forcePasswordColumn = pickColumn(
            $columns,
            [
                'must_change_password',
                'force_password_change',
                'password_change_required',
                'must_reset_password',
            ]
        );

        $firstNameColumn = pickColumn(
            $columns,
            [
                'first_name',
                'firstname',
                'firstName',
            ]
        );

        $lastNameColumn = pickColumn(
            $columns,
            [
                'last_name',
                'lastname',
                'lastName',
            ]
        );


        /*
        | FIND ADMIN
        */
        $sql =
            'SELECT *
             FROM ' . quoteIdent(USERS_TABLE) . '
             WHERE ' . quoteIdent($usernameColumn) . ' = ?
             LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([ADMIN_USERNAME]);

        $existing = $stmt->fetch();


        /*
        | GENERATE REAL PASSWORD HASH
        */
        $hash = password_hash(
            NEW_PASSWORD,
            PASSWORD_DEFAULT
        );

        if (!$hash) {
            throw new RuntimeException(
                'password_hash() failed.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | EXISTING ADMIN
        |--------------------------------------------------------------------------
        */
        if ($existing) {

            $set = [];
            $params = [];

            $set[] =
                quoteIdent($passwordColumn) . ' = ?';

            $params[] = $hash;


            /*
            | Status column ho to active karne ki koshish.
            */
            if ($statusColumn) {

                $statusType =
                    strtolower((string)$columns[$statusColumn]['DATA_TYPE']);

                if (
                    in_array(
                        $statusType,
                        ['varchar', 'char', 'text', 'enum'],
                        true
                    )
                ) {
                    $set[] =
                        quoteIdent($statusColumn) . " = 'active'";
                }
            }


            /*
            | First login par password change.
            */
            if ($forcePasswordColumn) {

                $set[] =
                    quoteIdent($forcePasswordColumn) .
                    ' = ?';

                $params[] =
                    FORCE_PASSWORD_CHANGE ? 1 : 0;
            }

            $params[] = ADMIN_USERNAME;

            $updateSql =
                'UPDATE ' .
                quoteIdent(USERS_TABLE) .
                ' SET ' .
                implode(', ', $set) .
                ' WHERE ' .
                quoteIdent($usernameColumn) .
                ' = ?';

            $update =
                $pdo->prepare($updateSql);

            $update->execute($params);

            $messages[] =
                'Admin "' .
                ADMIN_USERNAME .
                '" ka password successfully reset ho gaya.';

            $diagnostics[] =
                'Operation: EXISTING USER PASSWORD RESET.';


        /*
        |--------------------------------------------------------------------------
        | ADMIN MISSING
        |--------------------------------------------------------------------------
        */
        } elseif (CREATE_IF_MISSING) {

            /*
            | Roman Urdu:
            | Ab script admin create karne ki koshish karegi.
            |
            | Sirf woh common fields automatically fill hongi jo table
            | mein milti hain. Agar aapki users table mein koi extra
            | required field hai aur uska default nahi hai, script error
            | degi. Hum random value guess nahi karenge.
            */

            $insertColumns = [];
            $placeholders = [];
            $values = [];


            $insertColumns[] = $usernameColumn;
            $placeholders[] = '?';
            $values[] = ADMIN_USERNAME;


            $insertColumns[] = $passwordColumn;
            $placeholders[] = '?';
            $values[] = $hash;


            if ($firstNameColumn) {

                $insertColumns[] = $firstNameColumn;
                $placeholders[] = '?';
                $values[] = 'Admin';
            }


            if ($lastNameColumn) {

                $insertColumns[] = $lastNameColumn;
                $placeholders[] = '?';
                $values[] = 'Administrator';
            }


            if ($statusColumn) {

                $insertColumns[] = $statusColumn;
                $placeholders[] = '?';
                $values[] = 'active';
            }


            if ($forcePasswordColumn) {

                $insertColumns[] = $forcePasswordColumn;
                $placeholders[] = '?';
                $values[] =
                    FORCE_PASSWORD_CHANGE ? 1 : 0;
            }


            $insertSql =
                'INSERT INTO ' .
                quoteIdent(USERS_TABLE) .
                ' (' .
                implode(
                    ',',
                    array_map(
                        'quoteIdent',
                        $insertColumns
                    )
                ) .
                ') VALUES (' .
                implode(',', $placeholders) .
                ')';


            $insert =
                $pdo->prepare($insertSql);

            $insert->execute($values);

            $messages[] =
                'Admin "' .
                ADMIN_USERNAME .
                '" successfully create ho gaya.';

            $diagnostics[] =
                'Operation: NEW ADMIN USER CREATED.';


        } else {

            $errors[] =
                'Admin user "' .
                ADMIN_USERNAME .
                '" nahi mila aur CREATE_IF_MISSING=false hai.';
        }

    } catch (Throwable $e) {

        $errors[] =
            'Setup failed: ' . $e->getMessage();

        $diagnostics[] =
            'Technical error: ' . get_class($e);
    }
}

?>
<!doctype html>
<html lang="en">
<head>

<meta charset="utf-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>Gravity CRM - Admin Setup</title>

<style>

body{
    margin:0;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#eef3f9;
    font-family:Arial,sans-serif;
    color:#172033;
}

.box{
    width:min(780px,94vw);
    background:#fff;
    border:1px solid #dce5ef;
    border-radius:18px;
    padding:30px;
    box-shadow:0 20px 70px rgba(0,0,0,.12);
}

h1{
    margin-top:0;
}

.warning{
    background:#fff7df;
    border:1px solid #efd48c;
    color:#684d12;
    padding:14px;
    border-radius:10px;
    line-height:1.55;
    margin-bottom:16px;
}

.success{
    background:#eaf9f0;
    border:1px solid #b9dfc7;
    color:#176638;
    padding:14px;
    border-radius:10px;
    line-height:1.55;
    margin-bottom:16px;
}

.error{
    background:#fff0f0;
    border:1px solid #efc4c4;
    color:#982e2e;
    padding:14px;
    border-radius:10px;
    line-height:1.55;
    margin-bottom:16px;
}

.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
}

.item{
    background:#f7f9fc;
    border:1px solid #e2e8f0;
    border-radius:10px;
    padding:12px;
}

.danger{
    color:#a52828;
    font-weight:700;
    margin-top:15px;
}

.diagnostics{
    background:#f6f8fb;
    border:1px solid #e2e8f0;
    padding:14px;
    border-radius:10px;
    margin-top:18px;
}

pre{
    white-space:pre-wrap;
    background:#0c1625;
    color:#e6f5ff;
    padding:14px;
    border-radius:10px;
    overflow:auto;
}

code,
pre{
    font-family:Consolas,Monaco,monospace;
}

a{
    color:#245fd7;
    text-decoration:none;
}

@media(max-width:700px){
    .grid{
        grid-template-columns:1fr;
    }
}

</style>

</head>

<body>

<main class="box">

<h1>Gravity CRM - Admin Setup</h1>

<div class="warning">

<strong>Roman Urdu - Zaroori:</strong><br>

Ye temporary recovery/setup file hai.
Is ka purpose sirf local XAMPP mein admin account
banana ya password reset karna hai.

Setup complete hone ke baad:
<br><br>

<strong>
setup_admin.php DELETE KARNA HAI.
</strong>

<br><br>

Is file ko live/public website par permanently na rakhein.

</div>


<?php foreach ($messages as $message): ?>

<div class="success">

<?= h($message) ?>

</div>

<?php endforeach; ?>


<?php foreach ($errors as $error): ?>

<div class="error">

<?= h($error) ?>

</div>

<?php endforeach; ?>


<div class="grid">

<div class="item">

<strong>Database</strong><br>

<?= h(DB_NAME) ?>

</div>


<div class="item">

<strong>Users Table</strong><br>

<?= h(USERS_TABLE) ?>

</div>


<div class="item">

<strong>Admin Username</strong><br>

<?= h(ADMIN_USERNAME) ?>

</div>


<div class="item">

<strong>Password Change Flag</strong><br>

<?= FORCE_PASSWORD_CHANGE ? 'Enabled' : 'Disabled' ?>

</div>

</div>


<?php if ($messages): ?>

<div class="success">

<strong>Login information:</strong>

<br><br>

Username:
<strong><?= h(ADMIN_USERNAME) ?></strong>

<br>

Password:
<strong><?= h(NEW_PASSWORD) ?></strong>

<br><br>

Login page:

<a href="<?= h(LOGIN_URL) ?>">
<?= h(LOGIN_URL) ?>
</a>

</div>


<div class="danger">

Roman Urdu:
Setup successful ho gaya hai.
Ab browser band karne se pehle
<code>setup_admin.php</code>
delete kar dein.

</div>

<?php endif; ?>


<?php if ($diagnostics): ?>

<div class="diagnostics">

<strong>Diagnostic information</strong>

<pre><?php foreach ($diagnostics as $line): ?><?= h($line) . "\n" ?><?php endforeach; ?></pre>

</div>

<?php endif; ?>


<hr style="margin:25px 0;border:0;border-top:1px solid #e4e9f0">


<p>

<strong>Roman Urdu troubleshooting:</strong>

</p>

<ul>

<li>
Agar "Database connection" error aaye:
DB_NAME, DB_USER, DB_PASS check karein.
Aur XAMPP mein MySQL START karein.
</li>

<li>
Agar "Users table nahi mili" aaye:
USERS_TABLE ka exact naam phpMyAdmin se check karein.
</li>

<li>
Agar "Username column detect nahi hui" aaye:
users table mein username/email/login column ka naam check karein.
</li>

<li>
Agar "Password column detect nahi hui" aaye:
password ya password_hash column ka exact naam check karein.
</li>

<li>
Agar admin create karte waqt required-field error aaye:
database mein koi additional mandatory field ho sakti hai.
Usay guess nahi karna chahiye.
</li>

</ul>

<p>

Roman Urdu:
Agar aapko koi error milta hai to us error ka exact text mujhe
bhej dein. Phir hum guess kiye baghair actual database structure
ke mutabiq file ko adjust kar sakte hain.

</p>

</main>

</body>
</html>
