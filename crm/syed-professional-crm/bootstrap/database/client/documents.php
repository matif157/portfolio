```php
<?php
/**
 * =====================================================
 * FILE: client/documents.php
 *
 * PURPOSE:
 * Client ke active service ke required documents
 * ko friendly checklist ki form mein show karna.
 *
 * WORKFLOW:
 *
 * Client Login
 *      ↓
 * Client Identity Check
 *      ↓
 * Active Service Load
 *      ↓
 * Document Requests Load
 *      ↓
 * Required Documents Display
 *      ↓
 * Client Upload / Review Status
 *
 * BHAI:
 * Is page ka kaam sirf documents dikhana hai.
 * Actual upload processing upload_document.php karegi.
 * Is separation se code clean aur secure rehta hai.
 * =====================================================
 */


/*
=========================================================
1. SESSION + DATABASE
=========================================================

session_start() = PHP built-in function.

Why:
Client ki login session ko access karne ke liye.

require_once = PHP built-in include mechanism.

Why:
Same file ko multiple dafa load hone se rokta hai.
=========================================================
*/

require_once '../config/bootstrap.php';


/*
=========================================================
2. CLIENT AUTHENTICATION
=========================================================

Bhai protected client page hai.

Agar client login nahi hai to documents page
direct access nahi hona chahiye.
=========================================================
*/

if (
    !isset($_SESSION['client_id']) ||
    !is_numeric($_SESSION['client_id'])
) {
    /*
    header() = PHP built-in redirect function.

    Client ko login page par bhej rahe hain.
    */
    header('Location: ../index.php');
    exit;
}


/*
=========================================================
3. CLIENT ID
=========================================================

intval() = PHP built-in function.

Why:
Session value ko integer mein convert karta hai.

Security ke liye hum session value ko direct SQL mein
concatenate nahi karenge.
=========================================================
*/

$clientId = (int) $_SESSION['client_id'];


/*
=========================================================
4. ACTIVE SERVICE IDENTIFY KARNA
=========================================================

Bhai ek client ke multiple services ho sakti hain.

Is page ko ideally selected service ke context mein
chalna chahiye.

Example:

/client/documents.php?service=125

Lekin service ID par blindly trust nahi karenge.

Pehle verify karenge ke service actually isi client
ki hai.
=========================================================
*/

$serviceCaseId = isset($_GET['service'])
    ? (int) $_GET['service']
    : 0;


/*
=========================================================
5. DATABASE CONNECTION CHECK
=========================================================

bootstrap.php se PDO connection available hona chahiye.

Agar architecture mein $pdo ka naam different hai,
to central bootstrap file mein hi standardize karenge.

Bhai har page par connection manually banana avoid
karna hai.
=========================================================
*/

if (!isset($pdo) || !($pdo instanceof PDO)) {

    /*
    RuntimeException = PHP built-in Exception class.

    Development stage mein clear error dene ke liye.
    */
    throw new RuntimeException(
        'Database connection is not available.'
    );
}


/*
=========================================================
6. SERVICE VERIFY KARNA
=========================================================

Bhai ye bohat important security check hai.

Client URL change karke:

?service=999

nahi dekh sakta agar service kisi aur client ki ho.

Prepared statement use kar rahe hain.

prepare() aur execute() = PDO methods.

Why:
SQL injection se protection aur clean query handling.
=========================================================
*/

$serviceSql = "
    SELECT
        sc.id,
        sc.client_id,
        sc.service_name,
        sc.status
    FROM service_cases sc
    WHERE sc.id = ?
      AND sc.client_id = ?
    LIMIT 1
";

$serviceStmt = $pdo->prepare($serviceSql);

$serviceStmt->execute([
    $serviceCaseId,
    $clientId
]);


/*
fetch() = PDO method.

Why:
Database se ek service record retrieve karna.
*/

$service = $serviceStmt->fetch(PDO::FETCH_ASSOC);


/*
=========================================================
7. SERVICE NOT FOUND
=========================================================

Agar service client ki nahi hai ya exist nahi karti,
access deny kar denge.

Client ko database error nahi dikhana.
=========================================================
*/

if (!$service) {

    http_response_code(404);

    echo 'Service not found.';
    exit;
}


/*
=========================================================
8. DOCUMENT REQUESTS LOAD KARNA
=========================================================

Ab CRM dekhega ke is service ke liye client se
kaun kaun se documents required/requested hain.

document_requests:
    actual client-specific request

document_types:
    document ka master type

Example:

document_requests
        ↓
W-2
        ↓
W-2 document type information
=========================================================
*/

$documentSql = "
    SELECT
        dr.id,
        dr.title,
        dr.instructions,
        dr.status,
        dr.priority,
        dr.due_at,
        dr.requested_at,

        dt.document_name,
        dt.category

    FROM document_requests dr

    INNER JOIN document_types dt
        ON dt.id = dr.document_type_id

    WHERE dr.client_id = ?
      AND dr.service_case_id = ?

    ORDER BY
        CASE dr.status
            WHEN 'requested' THEN 1
            WHEN 'partially_received' THEN 2
            WHEN 'under_review' THEN 3
            WHEN 'rejected' THEN 4
            WHEN 'approved' THEN 5
            ELSE 6
        END,
        dr.due_at ASC,
        dr.id ASC
";

$documentStmt = $pdo->prepare($documentSql);

$documentStmt->execute([
    $clientId,
    $serviceCaseId
]);


/*
fetchAll() = PDO built-in method.

Why:
Saare requested documents ek array mein load karne ke liye.
*/

$documents = $documentStmt->fetchAll(PDO::FETCH_ASSOC);


/*
=========================================================
9. SAFE OUTPUT HELPER
=========================================================

htmlspecialchars() = PHP built-in security function.

Why:
Database ka text directly HTML mein print karna
XSS vulnerability create kar sakta hai.

Isliye har user/database supplied value ko escape
karna hai.
=========================================================
*/

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
=========================================================
10. STATUS LABEL HELPER
=========================================================

Database status technical ho sakta hai.

Example:

under_review

Client ko:

"Under Review"

dikhana zyada friendly hai.
=========================================================
*/

function documentStatusLabel(string $status): string
{
    return match ($status) {

        'requested' =>
            'Action Required',

        'partially_received' =>
            'Partially Received',

        'received' =>
            'Received',

        'under_review' =>
            'Under Review',

        'approved' =>
            'Approved',

        'rejected' =>
            'Changes Needed',

        'cancelled' =>
            'Cancelled',

        'expired' =>
            'Expired',

        default =>
            'Pending'
    };
}


/*
=========================================================
11. STATUS CSS CLASS
=========================================================

Frontend ko status ke according visual state deni hai.

Ye sirf UI classification hai.
Business logic database status par hi depend karega.
=========================================================
*/

function documentStatusClass(string $status): string
{
    return match ($status) {

        'approved' =>
            'status-approved',

        'under_review',
        'received' =>
            'status-review',

        'rejected',
        'expired',
        'requested' =>
            'status-action',

        default =>
            'status-pending'
    };
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Documents -
        <?= e($service['service_name']) ?>
    </title>

    <style>

        /*
        =====================================================
        BASIC CLIENT DOCUMENT UI
        =====================================================

        Bhai ye initial functional UI hai.
        Final SPS design system baad mein centralized CSS
        file mein move hoga.
        =====================================================
        */

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fa;
            color: #1f2937;
        }

        .page {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            margin-bottom: 8px;
        }

        .service-name {
            color: #64748b;
        }

        .document-list {
            display: grid;
            gap: 16px;
        }

        .document-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 20px;
        }

        .document-top {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: flex-start;
        }

        .document-title {
            margin: 0 0 7px;
            font-size: 18px;
        }

        .document-type {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-approved {
            background: #dcfce7;
            color: #166534;
        }

        .status-review {
            background: #fef3c7;
            color: #92400e;
        }

        .status-action {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-pending {
            background: #e5e7eb;
            color: #374151;
        }

        .instructions {
            margin: 16px 0;
            color: #475569;
            line-height: 1.6;
        }

        .document-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            color: #64748b;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .upload-button {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 8px;
            background: #111827;
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
        }

        .empty-state {
            background: #ffffff;
            border: 1px dashed #cbd5e1;
            padding: 40px;
            text-align: center;
            border-radius: 14px;
        }

    </style>

</head>

<body>

<div class="page">

    <!--
    =====================================================
    PAGE HEADER
    =====================================================

    Client ko clear batana hai ke woh kis service ke
    documents dekh raha hai.
    =====================================================
    -->

    <div class="header">

        <h1>
            Documents
        </h1>

        <div class="service-name">
            <?= e($service['service_name']) ?>
        </div>

    </div>


    <!--
    =====================================================
    DOCUMENT LIST
    =====================================================
    -->

    <?php if (!$documents): ?>

        <!--
        Agar koi document request nahi hai to confusing
        blank screen ke bajaye friendly message.
        -->

        <div class="empty-state">

            <h2>
                No documents requested
            </h2>

            <p>
                You currently have no document requests
                for this service.
            </p>

        </div>

    <?php else: ?>

        <div class="document-list">

            <?php foreach ($documents as $document): ?>

                <!--
                foreach = PHP built-in language construct.

                Why:
                Har document request ko individually display
                karne ke liye.
                -->

                <div class="document-card">

                    <div class="document-top">

                        <div>

                            <h2 class="document-title">

                                <?= e($document['title']) ?>

                            </h2>

                            <p class="document-type">

                                <?= e($document['document_name']) ?>

                            </p>

                        </div>


                        <span
                            class="status
                            <?= e(
                                documentStatusClass(
                                    $document['status']
                                )
                            ) ?>"
                        >

                            <?= e(
                                documentStatusLabel(
                                    $document['status']
                                )
                            ) ?>

                        </span>

                    </div>


                    <?php if (!empty($document['instructions'])): ?>

                        <div class="instructions">

                            <?= nl2br(
                                e($document['instructions'])
                            ) ?>

                        </div>

                    <?php endif; ?>


                    <div class="document-meta">

                        <?php if (!empty($document['due_at'])): ?>

                            <span>

                                Due:
                                <?= e($document['due_at']) ?>

                            </span>

                        <?php endif; ?>


                        <?php if (!empty($document['priority'])): ?>

                            <span>

                                Priority:
                                <?= e($document['priority']) ?>

                            </span>

                        <?php endif; ?>

                    </div>


                    <!--
                    =================================================
                    UPLOAD ACTION
                    =================================================

                    Sirf woh requests upload button dikhayengi
                    jahan client ko action karna hai.

                    Approved document ke liye unnecessary
                    upload button nahi dikhana.
                    =================================================
                    -->

                    <?php if (
                        in_array(
                            $document['status'],
                            [
                                'requested',
                                'rejected',
                                'partially_received'
                            ],
                            true
                        )
                    ): ?>

                        <a
                            class="upload-button"
                            href="upload_document.php?request=<?= (int) $document['id'] ?>"
                        >
                            Upload Document
                        </a>

                    <?php elseif (
                        $document['status'] === 'under_review'
                    ): ?>

                        <span>
                            Our team is reviewing this document.
                        </span>

                    <?php elseif (
                        $document['status'] === 'approved'
                    ): ?>

                        <span>
                            ✓ Document approved
                        </span>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

</body>

</html>
```
