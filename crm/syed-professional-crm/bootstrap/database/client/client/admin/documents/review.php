```php
<?php
/**
 * =====================================================
 * FILE: admin/documents/review.php
 *
 * PURPOSE:
 * SPS staff ke liye Document Review Center.
 *
 * Is page par authorized staff:
 *
 * 1. Pending documents dekh sakta hai
 * 2. Document details dekh sakta hai
 * 3. Document approve kar sakta hai
 * 4. Document reject kar sakta hai
 * 5. Rejection reason mandatory de sakta hai
 *
 * WORKFLOW:
 *
 * Staff Login
 *      ↓
 * Authentication
 *      ↓
 * Role / Permission Check
 *      ↓
 * POST Action Check
 *      ↓
 * Document Ownership / Record Check
 *      ↓
 * Approve OR Reject
 *      ↓
 * Audit Log
 *      ↓
 * Redirect
 *      ↓
 * Pending Review List
 *
 * BHAI:
 * Ye page client upload ki file ko directly public
 * nahi karta.
 *
 * Actual private file ko future secure download controller
 * ke through serve karna hai.
 * =====================================================
 */


/*
=========================================================
1. CRM BOOTSTRAP
=========================================================

require_once = PHP built-in include mechanism.

Why:
Central database connection, session aur common
configuration load karne ke liye.
=========================================================
*/

require_once '../../config/bootstrap.php';


/*
=========================================================
2. STAFF AUTHENTICATION
=========================================================

Bhai client portal aur staff portal alag security
boundary hain.

Sirf logged-in staff member ko review center access
milega.
=========================================================
*/

if (
    !isset($_SESSION['user_id']) ||
    !is_numeric($_SESSION['user_id'])
) {

    header(
        'Location: ../../index.php'
    );

    exit;
}


/*
=========================================================
3. STAFF ID
=========================================================

intval() = PHP built-in function.

Session value ko integer mein convert kar rahe hain.
=========================================================
*/

$staffId = (int) $_SESSION['user_id'];


/*
=========================================================
4. DATABASE CONNECTION CHECK
=========================================================
*/

if (!isset($pdo) || !($pdo instanceof PDO)) {

    throw new RuntimeException(
        'Database connection is not available.'
    );
}


/*
=========================================================
5. LOAD CURRENT STAFF ROLE
=========================================================

Bhai login hona aur authorized hona do different cheezen
hain.

Example:

Employee login kar sakta hai,
lekin har employee documents approve nahi kar sakta.

Isliye role database se verify karenge.
=========================================================
*/

$staffSql = "
    SELECT
        u.id,
        u.full_name,
        u.role_id,
        r.role_name

    FROM users u

    LEFT JOIN roles r
        ON r.id = u.role_id

    WHERE u.id = ?

    LIMIT 1
";


$staffStmt = $pdo->prepare(
    $staffSql
);

$staffStmt->execute([
    $staffId
]);


$currentStaff = $staffStmt->fetch(
    PDO::FETCH_ASSOC
);


/*
=========================================================
6. STAFF ACCOUNT CHECK
=========================================================
*/

if (!$currentStaff) {

    session_destroy();

    header(
        'Location: ../../index.php'
    );

    exit;
}


/*
=========================================================
7. DOCUMENT REVIEW PERMISSION
=========================================================

Bhai abhi architecture mein role-based permission
system ka foundation use kar rahe hain.

Future mein hard-coded role names ko permissions table
se replace kiya ja sakta hai:

permissions
role_permissions
user_permissions

For now approved review roles:
=========================================================
*/

$reviewRoles = [
    'admin',
    'manager',
    'accountant',
    'case_manager',
    'document_reviewer'
];


$currentRole = strtolower(
    trim(
        (string) (
            $currentStaff['role_name'] ?? ''
        )
    )
);


if (
    !in_array(
        $currentRole,
        $reviewRoles,
        true
    )
) {

    http_response_code(403);

    echo 'You do not have permission to review documents.';
    exit;
}


/*
=========================================================
8. CSRF SECURITY FOUNDATION
=========================================================

Bhai POST actions ke liye CSRF protection zaroori hai.

Agar central bootstrap already token helper provide
karta hai to usko use karenge.

Agar helper available nahi hai to temporary local
implementation provide kar rahe hain.

random_bytes() = PHP built-in cryptographic function.
=========================================================
*/

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    strlen($_SESSION['csrf_token']) < 32
) {

    $_SESSION['csrf_token'] = bin2hex(
        random_bytes(32)
    );
}


$csrfToken = $_SESSION['csrf_token'];


/*
=========================================================
9. SAFE OUTPUT HELPER
=========================================================

htmlspecialchars() = PHP built-in security function.

Why:
Database values ko HTML mein safely print karne ke liye.
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
10. ACTION STATE
=========================================================
*/

$errors = [];

$successMessage = '';


/*
=========================================================
11. POST ACTION PROCESSING
=========================================================

Bhai redirect se pehle POST handling karni hai.

Actions:

approve
reject

GET request:
    review list show

POST request:
    database action perform
=========================================================
*/

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
) {


    /*
    =====================================================
    11-A. CSRF TOKEN CHECK
    =====================================================

    hash_equals() = PHP built-in security function.

    Why:
Expected token aur submitted token ko timing-safe
comparison ke saath compare karta hai.
    =====================================================
    */

    $submittedToken =
        $_POST['csrf_token'] ?? '';


    if (
        !is_string($submittedToken) ||
        !hash_equals(
            $csrfToken,
            $submittedToken
        )
    ) {

        $errors[] =
            'Security verification failed. Please refresh the page and try again.';
    }


    /*
    =====================================================
    11-B. DOCUMENT ID
    =====================================================
    */

    $documentId = isset(
        $_POST['document_id']
    )
        ? (int) $_POST['document_id']
        : 0;


    if (
        empty($errors) &&
        $documentId <= 0
    ) {

        $errors[] =
            'Invalid document selected.';
    }


    /*
    =====================================================
    11-C. ACTION
    =====================================================
    */

    $action = strtolower(
        trim(
            (string) (
                $_POST['action'] ?? ''
            )
        )
    );


    if (
        empty($errors) &&
        !in_array(
            $action,
            ['approve', 'reject'],
            true
        )
    ) {

        $errors[] =
            'Invalid document action.';
    }


    /*
    =====================================================
    11-D. REJECTION REASON
    =====================================================

    Reject karte waqt reason mandatory hai.

    Example:

    "Bank statement is incomplete."

    Staff sirf reject click karke client ko blank
    rejection nahi bhej sakta.
    =====================================================
    */

    $rejectionReason = trim(
        (string) (
            $_POST['rejection_reason'] ?? ''
        )
    );


    if (
        empty($errors) &&
        $action === 'reject' &&
        $rejectionReason === ''
    ) {

        $errors[] =
            'Please provide a reason before rejecting the document.';
    }


    /*
    =====================================================
    11-E. REASON LENGTH LIMIT
    =====================================================
    */

    if (
        empty($errors) &&
        $action === 'reject' &&
        mb_strlen($rejectionReason) > 2000
    ) {

        $errors[] =
            'The rejection reason cannot exceed 2000 characters.';
    }


    /*
    =====================================================
    12. LOAD DOCUMENT FOR UPDATE
    =====================================================

    BHAI:
Action perform karne se pehle current database state
dobara read kar rahe hain.

Why?

UI par document "under_review" tha.

Lekin doosra staff member meanwhile approve kar sakta
hai.

Database state is the source of truth.
=========================================================
*/

    if (empty($errors)) {

        $documentSql = "
            SELECT
                cd.id,
                cd.client_id,
                cd.service_case_id,
                cd.document_request_id,
                cd.document_type_id,

                cd.original_filename,
                cd.stored_filename,
                cd.storage_path,

                cd.mime_type,
                cd.file_extension,
                cd.file_size,
                cd.file_hash,

                cd.version_number,
                cd.status,
                cd.upload_source,

                cd.uploaded_at,

                dr.title AS request_title,
                dr.status AS request_status,

                c.full_name AS client_name,

                sc.service_name

            FROM client_documents cd

            INNER JOIN document_requests dr
                ON dr.id = cd.document_request_id

            INNER JOIN clients c
                ON c.id = cd.client_id

            INNER JOIN service_cases sc
                ON sc.id = cd.service_case_id

            WHERE cd.id = ?

            LIMIT 1
        ";


        $documentStmt = $pdo->prepare(
            $documentSql
        );


        $documentStmt->execute([
            $documentId
        ]);


        $document = $documentStmt->fetch(
            PDO::FETCH_ASSOC
        );


        if (!$document) {

            $errors[] =
                'The selected document could not be found.';
        }
    }


    /*
    =====================================================
    13. VERIFY DOCUMENT STATE
    =====================================================

    Only "under_review" documents can be approved/rejected.

    Is se duplicate action prevent hota hai.
    =====================================================
    */

    if (
        empty($errors) &&
        $document['status'] !== 'under_review'
    ) {

        $errors[] =
            'This document has already been processed and cannot be changed from this screen.';
    }


    /*
    =====================================================
    14. DATABASE TRANSACTION
    =====================================================
    */

    if (empty($errors)) {

        try {

            /*
            beginTransaction() = PDO method.

            Document status + request status + audit log ko
            ek logical operation mein rakh rahe hain.
            */

            $pdo->beginTransaction();


            /*
            =================================================
            15. APPROVE DOCUMENT
            =================================================
            */

            if ($action === 'approve') {

                /*
                -------------------------------------------------
                Document status:
                    approved

                -------------------------------------------------
                Document request:
                    approved

                -------------------------------------------------
                Client ko:
                    no further upload required
                -------------------------------------------------
                */


                $approveDocumentSql = "
                    UPDATE client_documents

                    SET
                        status = 'approved',
                        reviewed_by = ?,
                        reviewed_at = NOW(),
                        updated_at = NOW()

                    WHERE id = ?
                      AND status = 'under_review'
                ";


                $approveDocumentStmt =
                    $pdo->prepare(
                        $approveDocumentSql
                    );


                $approveDocumentStmt->execute([

                    $staffId,
                    $documentId

                ]);


                /*
                rowCount() = PDO method.

                Why:
Check karna ke actual record update hua ya nahi.

                Agar 0 hai to kisi aur process ne record
                pehle hi change kar diya ho sakta hai.
                */

                if (
                    $approveDocumentStmt->rowCount() !== 1
                ) {

                    throw new RuntimeException(
                        'Document state changed before approval could be completed.'
                    );
                }


                /*
                =================================================
                16. UPDATE DOCUMENT REQUEST
                =================================================
                */

                $approveRequestSql = "
                    UPDATE document_requests

                    SET
                        status = 'approved',
                        updated_at = NOW()

                    WHERE id = ?
                      AND status = 'under_review'
                ";


                $approveRequestStmt =
                    $pdo->prepare(
                        $approveRequestSql
                    );


                $approveRequestStmt->execute([
                    $document['document_request_id']
                ]);


                /*
                =================================================
                17. AUDIT LOG - APPROVAL
                =================================================

                BHAI:
High-end CRM mein "kis ne kya kiya" record karna
mandatory type feature hai.

Baad mein audit_logs table ka central structure aur
strong banayenge.
                =================================================
                */

                $auditSql = "
                    INSERT INTO audit_logs (

                        user_id,
                        action,
                        entity_type,
                        entity_id,
                        description,
                        created_at

                    ) VALUES (

                        ?,
                        'document_approved',
                        'client_document',
                        ?,
                        ?,
                        NOW()

                    )
                ";


                $auditStmt = $pdo->prepare(
                    $auditSql
                );


                $auditDescription =
                    'Document approved: '
                    . $document['original_filename']
                    . ' | Client: '
                    . $document['client_name']
                    . ' | Service: '
                    . $document['service_name'];


                $auditStmt->execute([

                    $staffId,

                    $documentId,

                    $auditDescription

                ]);


                $successMessage =
                    'Document approved successfully.';
            }


            /*
            =================================================
            18. REJECT DOCUMENT
            =================================================
            */

            if ($action === 'reject') {

                /*
                -------------------------------------------------
                Document status:
                    rejected

                -------------------------------------------------
                Document request:
                    rejected

                -------------------------------------------------
                Client:
                    must provide another version
                -------------------------------------------------
                */


                $rejectDocumentSql = "
                    UPDATE client_documents

                    SET
                        status = 'rejected',
                        rejection_reason = ?,
                        reviewed_by = ?,
                        reviewed_at = NOW(),
                        updated_at = NOW()

                    WHERE id = ?
                      AND status = 'under_review'
                ";


                $rejectDocumentStmt =
                    $pdo->prepare(
                        $rejectDocumentSql
                    );


                $rejectDocumentStmt->execute([

                    $rejectionReason,

                    $staffId,

                    $documentId

                ]);


                if (
                    $rejectDocumentStmt->rowCount() !== 1
                ) {

                    throw new RuntimeException(
                        'Document state changed before rejection could be completed.'
                    );
                }


                /*
                =================================================
                19. UPDATE REQUEST BACK TO REJECTED
                =================================================

                Is status ke baad client ko upload_document.php
                se corrected version provide karne diya jayega.
                =================================================
                */

                $rejectRequestSql = "
                    UPDATE document_requests

                    SET
                        status = 'rejected',
                        updated_at = NOW()

                    WHERE id = ?
                      AND status = 'under_review'
                ";


                $rejectRequestStmt =
                    $pdo->prepare(
                        $rejectRequestSql
                    );


                $rejectRequestStmt->execute([
                    $document['document_request_id']
                ]);


                /*
                =================================================
                20. AUDIT LOG - REJECTION
                =================================================
                */

                $auditSql = "
                    INSERT INTO audit_logs (

                        user_id,
                        action,
                        entity_type,
                        entity_id,
                        description,
                        created_at

                    ) VALUES (

                        ?,
                        'document_rejected',
                        'client_document',
                        ?,
                        ?,
                        NOW()

                    )
                ";


                $auditStmt = $pdo->prepare(
                    $auditSql
                );


                $auditDescription =
                    'Document rejected: '
                    . $document['original_filename']
                    . ' | Reason: '
                    . $rejectionReason
                    . ' | Client: '
                    . $document['client_name']
                    . ' | Service: '
                    . $document['service_name'];


                $auditStmt->execute([

                    $staffId,

                    $documentId,

                    $auditDescription

                ]);


                $successMessage =
                    'Document rejected and sent back for correction.';
            }


            /*
            =================================================
            21. COMMIT
            =================================================

            commit() = PDO method.

            Approval/rejection + request status + audit
            record ko permanently save karta hai.
            =================================================
            */

            $pdo->commit();


            /*
            =================================================
            22. PRG REDIRECT
            =================================================

            POST ke baad redirect.

            Is se browser refresh par action repeat nahi hota.
            =================================================
            */

            header(
                'Location: review.php?success='
                . rawurlencode(
                    $successMessage
                )
            );

            exit;


        } catch (Throwable $exception) {

            /*
            =================================================
            ROLLBACK
            =================================================
            */

            if ($pdo->inTransaction()) {

                $pdo->rollBack();
            }


            /*
            Detailed technical error client ko nahi dikhana.

            error_log() = PHP built-in function.

            Server log mein diagnostic information save
            karta hai.
            */

            error_log(
                'SPS document review failed: '
                . $exception->getMessage()
            );


            $errors[] =
                'The document action could not be completed. Please try again.';
        }
    }
}


/*
=========================================================
23. SUCCESS MESSAGE FROM REDIRECT
=========================================================
*/

if (
    isset($_GET['success']) &&
    is_string($_GET['success'])
) {

    $successMessage =
        $_GET['success'];
}


/*
=========================================================
24. LOAD PENDING DOCUMENTS
=========================================================

Bhai ab staff ko saare "under_review" documents dikhayenge.

Newest uploaded documents first.
=========================================================
*/

$pendingSql = "
    SELECT
        cd.id,

        cd.original_filename,
        cd.version_number,
        cd.status,
        cd.uploaded_at,

        dr.title AS request_title,

        c.full_name AS client_name,

        sc.service_name

    FROM client_documents cd

    INNER JOIN document_requests dr
        ON dr.id = cd.document_request_id

    INNER JOIN clients c
        ON c.id = cd.client_id

    INNER JOIN service_cases sc
        ON sc.id = cd.service_case_id

    WHERE cd.status = 'under_review'

    ORDER BY
        cd.uploaded_at ASC,
        cd.id ASC
";


$pendingStmt = $pdo->prepare(
    $pendingSql
);


$pendingStmt->execute();


$pendingDocuments =
    $pendingStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


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
        Document Review Center
    </title>


    <style>

        /*
        =====================================================
        DOCUMENT REVIEW CENTER UI
        =====================================================

        Bhai ye functional UI hai.

        Final SPS design system mein:
        - colors
        - typography
        - sidebar
        - cards
        - tables
        - buttons

        centralized CSS mein shift honge.
        =====================================================
        */

        body {
            margin: 0;
            background: #f4f6f8;
            font-family: Arial, sans-serif;
            color: #1f2937;
        }


        .page {
            max-width: 1250px;
            margin: 40px auto;
            padding: 0 20px;
        }


        .header {
            margin-bottom: 25px;
        }


        .header h1 {
            margin-bottom: 7px;
        }


        .header p {
            color: #64748b;
        }


        .message {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
        }


        .success {
            background: #dcfce7;
            color: #166534;
        }


        .error {
            background: #fee2e2;
            color: #991b1b;
        }


        .table-wrapper {
            background: #ffffff;
            border-radius: 14px;
            overflow-x: auto;
            border: 1px solid #e5e7eb;
        }


        table {
            width: 100%;
            border-collapse: collapse;
        }


        th,
        td {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }


        th {
            background: #f8fafc;
            font-size: 13px;
            color: #475569;
        }


        td {
            font-size: 14px;
        }


        .client {
            font-weight: bold;
        }


        .service {
            color: #64748b;
            margin-top: 4px;
        }


        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            background: #fef3c7;
            color: #92400e;
            font-size: 12px;
            font-weight: bold;
        }


        .review-link {
            display: inline-block;
            padding: 8px 13px;
            border-radius: 8px;
            background: #111827;
            color: #ffffff;
            text-decoration: none;
            font-size: 13px;
        }


        .empty {
            background: #ffffff;
            border: 1px dashed #cbd5e1;
            padding: 45px;
            border-radius: 14px;
            text-align: center;
        }

    </style>

</head>


<body>


<div class="page">


    <!--
    =====================================================
    PAGE HEADER
    =====================================================
    -->

    <div class="header">

        <h1>
            Document Review Center
        </h1>

        <p>

            Review client documents waiting for staff action.

        </p>

    </div>


    <!--
    =====================================================
    SUCCESS MESSAGE
    =====================================================
    -->

    <?php if ($successMessage !== ''): ?>

        <div class="message success">

            <?= e($successMessage) ?>

        </div>

    <?php endif; ?>


    <!--
    =====================================================
    ERROR MESSAGES
    =====================================================
    -->

    <?php if (!empty($errors)): ?>

        <div class="message error">

            <?php foreach ($errors as $error): ?>

                <div>
                    <?= e($error) ?>
                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <!--
    =====================================================
    PENDING DOCUMENTS
    =====================================================
    -->

    <?php if (empty($pendingDocuments)): ?>

        <div class="empty">

            <h2>
                All caught up 🎉
            </h2>

            <p>
                There are currently no documents waiting
                for review.
            </p>

        </div>

    <?php else: ?>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Client
                        </th>

                        <th>
                            Service
                        </th>

                        <th>
                            Document
                        </th>

                        <th>
                            Version
                        </th>

                        <th>
                            Uploaded
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php foreach (
                        $pendingDocuments
                        as $pending
                    ): ?>

                        <!--
                        foreach = PHP language construct.

                        Har pending document ko ek row mein
                        display kar raha hai.
                        -->

                        <tr>


                            <td>

                                <div class="client">

                                    <?= e(
                                        $pending['client_name']
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <div>

                                    <?= e(
                                        $pending['service_name']
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <div>

                                    <?= e(
                                        $pending['request_title']
                                    ) ?>

                                </div>

                                <div class="service">

                                    <?= e(
                                        $pending['original_filename']
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                v<?= (int)
                                    $pending['version_number'] ?>

                            </td>


                            <td>

                                <?= e(
                                    $pending['uploaded_at']
                                ) ?>

                            </td>


                            <td>

                                <span class="status">

                                    Under Review

                                </span>

                            </td>


                            <td>

                                <!--
                                Detailed review screen next layer
                                mein open hogi.

                                Document ID URL mein ja raha hai,
                                lekin next screen database se
                                authorization dobara verify karegi.
                                -->

                                <a
                                    class="review-link"
                                    href="detail.php?id=<?= (int) $pending['id'] ?>"
                                >

                                    Review

                                </a>

                            </td>


                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>


</div>


</body>

</html>
```
