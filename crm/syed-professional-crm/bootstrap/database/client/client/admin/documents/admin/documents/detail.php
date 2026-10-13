<?php
/**
 * =====================================================
 * FILE: admin/documents/detail.php
 * PURPOSE: Staff ke liye complete document review screen.
 *
 * WORKFLOW:
 * Review Queue
 *      ↓
 * Document ID Receive
 *      ↓
 * Staff Authentication
 *      ↓
 * Staff Role Verification
 *      ↓
 * Document Database Verification
 *      ↓
 * Client + Service Information
 *      ↓
 * Current Document
 *      ↓
 * Version History
 *      ↓
 * Review Decision
 *
 * IMPORTANT:
 * Private document files ko direct public URL se serve
 * nahi karna. Secure download controller future mein
 * authorization verify karke file provide karega.
 * =====================================================
 */


/*
=========================================================
1. CRM BOOTSTRAP
=========================================================

require_once = PHP built-in include mechanism.

Central configuration, database connection aur session
load karne ke liye use ho raha hai.
=========================================================
*/

require_once '../../config/bootstrap.php';


/*
=========================================================
2. STAFF LOGIN CHECK
=========================================================

Bhai document review sensitive operation hai.

Isliye sirf logged-in staff member allowed hai.
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


$staffId = (int) $_SESSION['user_id'];


/*
=========================================================
3. DATABASE CHECK
=========================================================
*/

if (
    !isset($pdo) ||
    !($pdo instanceof PDO)
) {

    throw new RuntimeException(
        'Database connection is not available.'
    );
}


/*
=========================================================
4. STAFF ROLE CHECK
=========================================================

Login hona enough nahi.

Sirf authorized staff document review kar sakta hai.
=========================================================
*/

$staffSql = "
    SELECT
        u.id,
        u.full_name,
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


if (!$currentStaff) {

    session_destroy();

    header(
        'Location: ../../index.php'
    );

    exit;
}


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
5. CSRF TOKEN
=========================================================

random_bytes() = PHP built-in cryptographic function.

Is token ko POST actions protect karne ke liye use
kiya ja raha hai.
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
6. SAFE HTML OUTPUT FUNCTION
=========================================================

htmlspecialchars() = PHP built-in security function.

Database data ko HTML ke andar safely print karta hai
aur XSS risk reduce karta hai.
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
7. DOCUMENT ID
=========================================================

filter_input() = PHP built-in function.

URL se document ID safely read kar rahe hain.
=========================================================
*/

$documentId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);


if (
    !$documentId ||
    $documentId <= 0
) {

    http_response_code(400);

    echo 'Invalid document ID.';

    exit;
}


/*
=========================================================
8. LOAD COMPLETE DOCUMENT
=========================================================

Bhai yahan sirf filename nahi la rahe.

Staff ko review ke liye:

- client
- service
- document request
- document
- uploader
- current status
- reviewer
- upload source
- timestamps

sab context chahiye.
=========================================================
*/

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

        cd.rejection_reason,

        cd.uploaded_at,
        cd.reviewed_at,
        cd.updated_at,

        dr.title AS request_title,
        dr.description AS request_description,
        dr.status AS request_status,

        c.full_name AS client_name,
        c.email AS client_email,
        c.phone AS client_phone,

        sc.service_name,
        sc.status AS case_status,

        uploader.full_name AS uploader_name,

        reviewer.full_name AS reviewer_name

    FROM client_documents cd

    INNER JOIN document_requests dr
        ON dr.id = cd.document_request_id

    INNER JOIN clients c
        ON c.id = cd.client_id

    INNER JOIN service_cases sc
        ON sc.id = cd.service_case_id

    LEFT JOIN users uploader
        ON uploader.id = cd.uploaded_by

    LEFT JOIN users reviewer
        ON reviewer.id = cd.reviewed_by

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


/*
=========================================================
9. DOCUMENT EXISTENCE CHECK
=========================================================
*/

if (!$document) {

    http_response_code(404);

    echo 'Document not found.';

    exit;
}


/*
=========================================================
10. LOAD VERSION HISTORY
=========================================================

Bhai high-end CRM mein previous document versions
delete nahi karte.

Example:

Version 1 → uploaded
Version 2 → rejected
Version 3 → corrected
Version 4 → approved

Is se complete history preserve rehti hai.
=========================================================
*/

$versionSql = "
    SELECT

        cd.id,
        cd.original_filename,
        cd.version_number,
        cd.status,
        cd.rejection_reason,
        cd.uploaded_at,
        cd.reviewed_at,

        uploader.full_name AS uploader_name,

        reviewer.full_name AS reviewer_name

    FROM client_documents cd

    LEFT JOIN users uploader
        ON uploader.id = cd.uploaded_by

    LEFT JOIN users reviewer
        ON reviewer.id = cd.reviewed_by

    WHERE cd.document_request_id = ?

    ORDER BY
        cd.version_number DESC,
        cd.id DESC
";


$versionStmt = $pdo->prepare(
    $versionSql
);


$versionStmt->execute([
    $document['document_request_id']
]);


$versions = $versionStmt->fetchAll(
    PDO::FETCH_ASSOC
);


/*
=========================================================
11. LOAD AUDIT HISTORY
=========================================================

Staff ko document ke important actions ka timeline
dikhaya ja sakta hai.
=========================================================
*/

$auditSql = "
    SELECT

        al.action,
        al.description,
        al.created_at,

        u.full_name AS performed_by

    FROM audit_logs al

    LEFT JOIN users u
        ON u.id = al.user_id

    WHERE
        al.entity_type = 'client_document'
        AND al.entity_id = ?

    ORDER BY
        al.created_at DESC
";


$auditStmt = $pdo->prepare(
    $auditSql
);


$auditStmt->execute([
    $documentId
]);


$auditHistory = $auditStmt->fetchAll(
    PDO::FETCH_ASSOC
);


/*
=========================================================
12. DISPLAY HELPERS
=========================================================
*/

$statusClass = strtolower(
    (string) $document['status']
);


$isReviewable =
    $document['status'] === 'under_review';


/*
=========================================================
13. FILE SIZE DISPLAY
=========================================================

Bhai database mein file size bytes mein ho sakta hai.

Yahan readable form bana rahe hain.
=========================================================
*/

function readableFileSize($bytes): string
{
    $bytes = (float) $bytes;

    if ($bytes <= 0) {
        return '0 B';
    }

    $units = [
        'B',
        'KB',
        'MB',
        'GB'
    ];

    $power = floor(
        log($bytes, 1024)
    );

    $power = min(
        $power,
        count($units) - 1
    );

    return number_format(
        $bytes / pow(1024, $power),
        2
    ) . ' ' . $units[$power];
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
        Review Document -
        <?= e($document['original_filename']) ?>
    </title>


    <style>

        /*
        =====================================================
        TEMPORARY REVIEW UI
        =====================================================

        Final centralized SPS stylesheet mein ye styles
        move kiye jayenge.
        =====================================================
        */

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            background: #f4f6f8;
            color: #1f2937;
            font-family: Arial, sans-serif;
        }


        .page {
            max-width: 1300px;
            margin: 35px auto;
            padding: 0 20px;
        }


        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }


        .topbar h1 {
            margin: 0;
        }


        .back {
            text-decoration: none;
            color: #475569;
        }


        .grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }


        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 20px;
        }


        .card h2 {
            margin-top: 0;
            font-size: 18px;
        }


        .document-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 8px;
        }


        .muted {
            color: #64748b;
        }


        .info-grid {
            display: grid;
            grid-template-columns: repeat(
                2,
                minmax(0, 1fr)
            );
            gap: 15px;
        }


        .info-item {
            padding: 13px;
            background: #f8fafc;
            border-radius: 9px;
        }


        .info-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 5px;
        }


        .info-value {
            font-weight: 600;
            word-break: break-word;
        }


        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
            background: #fef3c7;
            color: #92400e;
        }


        .approved {
            background: #dcfce7;
            color: #166534;
        }


        .rejected {
            background: #fee2e2;
            color: #991b1b;
        }


        .under_review {
            background: #fef3c7;
            color: #92400e;
        }


        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }


        button {
            border: 0;
            border-radius: 9px;
            padding: 11px 16px;
            cursor: pointer;
            font-weight: bold;
        }


        .approve {
            background: #166534;
            color: #ffffff;
        }


        .reject {
            background: #991b1b;
            color: #ffffff;
        }


        textarea {
            width: 100%;
            min-height: 120px;
            resize: vertical;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            margin: 10px 0;
        }


        table {
            width: 100%;
            border-collapse: collapse;
        }


        th,
        td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 13px;
        }


        th {
            color: #64748b;
        }


        .timeline-item {
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }


        .timeline-item:last-child {
            border-bottom: 0;
        }


        .danger-box {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #881337;
            border-radius: 10px;
            padding: 14px;
        }


        @media (max-width: 900px) {

            .grid {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }
        }

    </style>

</head>


<body>


<div class="page">


    <!--
    =====================================================
    TOP NAVIGATION
    =====================================================
    -->

    <div class="topbar">

        <div>

            <a
                class="back"
                href="review.php"
            >
                ← Back to Review Queue
            </a>

            <h1>
                Document Review
            </h1>

        </div>

    </div>


    <div class="grid">


        <!--
        =================================================
        LEFT SIDE
        =================================================
        -->

        <main>


            <!--
            =============================================
            DOCUMENT INFORMATION
            =============================================
            -->

            <section class="card">

                <h2>
                    Document
                </h2>


                <div class="document-name">

                    <?= e(
                        $document['original_filename']
                    ) ?>

                </div>


                <p class="muted">

                    <?= e(
                        $document['request_title']
                    ) ?>

                </p>


                <div class="info-grid">


                    <div class="info-item">

                        <div class="info-label">
                            Status
                        </div>

                        <div class="info-value">

                            <span
                                class="status <?= e($statusClass) ?>"
                            >

                                <?= e(
                                    ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $document['status']
                                        )
                                    )
                                ) ?>

                            </span>

                        </div>

                    </div>


                    <div class="info-item">

                        <div class="info-label">
                            Version
                        </div>

                        <div class="info-value">

                            v<?= (int)
                                $document['version_number'] ?>

                        </div>

                    </div>


                    <div class="info-item">

                        <div class="info-label">
                            File Type
                        </div>

                        <div class="info-value">

                            <?= e(
                                $document['mime_type']
                            ) ?>

                        </div>

                    </div>


                    <div class="info-item">

                        <div class="info-label">
                            File Size
                        </div>

                        <div class="info-value">

                            <?= e(
                                readableFileSize(
                                    $document['file_size']
                                )
                            ) ?>

                        </div>

                    </div>


                    <div class="info-item">

                        <div class="info-label">
                            Uploaded By
                        </div>

                        <div class="info-value">

                            <?= e(
                                $document['uploader_name']
                                ?? 'Client'
                            ) ?>

                        </div>

                    </div>


                    <div class="info-item">

                        <div class="info-label">
                            Uploaded At
                        </div>

                        <div class="info-value">

                            <?= e(
                                $document['uploaded_at']
                            ) ?>

                        </div>

                    </div>


                </div>


                <?php if (
                    !empty(
                        $document['rejection_reason']
                    )
                ): ?>

                    <br>

                    <div class="danger-box">

                        <strong>
                            Previous Rejection Reason
                        </strong>

                        <p>
                            <?= nl2br(
                                e(
                                    $document[
                                        'rejection_reason'
                                    ]
                                )
                            ) ?>
                        </p>

                    </div>

                <?php endif; ?>


            </section>


            <!--
            =============================================
            CLIENT + SERVICE CONTEXT
            =============================================
            -->

            <section class="card">

                <h2>
                    Client & Service
                </h2>


                <div class="info-grid">


                    <div class="info-item">

                        <div class="info-label">
                            Client
                        </div>

                        <div class="info-value">

                            <?= e(
                                $document['client_name']
                            ) ?>

                        </div>

                    </div>


                    <div class="info-item">

                        <div class="info-label">
                            Email
                        </div>

                        <div class="info-value">

                            <?= e(
                                $document['client_email']
                            ) ?>

                        </div>

                    </div>


                    <div class="info-item">

                        <div class="info-label">
                            Phone
                        </div>

                        <div class="info-value">

                            <?= e(
                                $document['client_phone']
                            ) ?>

                        </div>

                    </div>


                    <div class="info-item">

                        <div class="info-label">
                            Service
                        </div>

                        <div class="info-value">

                            <?= e(
                                $document['service_name']
                            ) ?>

                        </div>

                    </div>


                    <div class="info-item">

                        <div class="info-label">
                            Case Status
                        </div>

                        <div class="info-value">

                            <?= e(
                                $document['case_status']
                            ) ?>

                        </div>

                    </div>


                    <div class="info-item">

                        <div class="info-label">
                            Request Status
                        </div>

                        <div class="info-value">

                            <?= e(
                                $document['request_status']
                            ) ?>

                        </div>

                    </div>


                </div>

            </section>


            <!--
            =============================================
            VERSION HISTORY
            =============================================
            -->

            <section class="card">

                <h2>
                    Version History
                </h2>


                <table>

                    <thead>

                        <tr>

                            <th>
                                Version
                            </th>

                            <th>
                                File
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Uploaded
                            </th>

                            <th>
                                Reviewed By
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach (
                            $versions
                            as $version
                        ): ?>

                            <tr>

                                <td>
                                    v<?= (int)
                                        $version['version_number'] ?>
                                </td>

                                <td>

                                    <?= e(
                                        $version['original_filename']
                                    ) ?>

                                </td>

                                <td>

                                    <span
                                        class="status <?= e(
                                            strtolower(
                                                $version['status']
                                            )
                                        ) ?>"
                                    >

                                        <?= e(
                                            ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $version['status']
                                                )
                                            )
                                        ) ?>

                                    </span>

                                </td>

                                <td>

                                    <?= e(
                                        $version['uploaded_at']
                                    ) ?>

                                </td>

                                <td>

                                    <?= e(
                                        $version['reviewer_name']
                                        ?? '—'
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </section>


            <!--
            =============================================
            AUDIT TIMELINE
            =============================================
            -->

            <section class="card">

                <h2>
                    Document Activity
                </h2>


                <?php if (
                    empty($auditHistory)
                ): ?>

                    <p class="muted">
                        No audit activity recorded yet.
                    </p>

                <?php else: ?>

                    <?php foreach (
                        $auditHistory
                        as $activity
                    ): ?>

                        <div class="timeline-item">

                            <strong>

                                <?= e(
                                    ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $activity['action']
                                        )
                                    )
                                ) ?>

                            </strong>


                            <div>

                                <?= e(
                                    $activity['description']
                                ) ?>

                            </div>


                            <small class="muted">

                                <?= e(
                                    $activity['performed_by']
                                    ?? 'System'
                                ) ?>

                                ·

                                <?= e(
                                    $activity['created_at']
                                ) ?>

                            </small>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


        </main>


        <!--
        =================================================
        RIGHT SIDE
        =================================================
        -->

        <aside>


            <!--
            =============================================
            REVIEW DECISION
            =============================================
            -->

            <section class="card">

                <h2>
                    Review Decision
                </h2>


                <?php if ($isReviewable): ?>


                    <!--
                    =====================================
                    APPROVE FORM
                    =====================================
                    -->

                    <form
                        method="POST"
                        action="review.php"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($csrfToken) ?>"
                        >


                        <input
                            type="hidden"
                            name="document_id"
                            value="<?= (int) $documentId ?>"
                        >


                        <input
                            type="hidden"
                            name="action"
                            value="approve"
                        >


                        <p class="muted">

                            Agar document complete aur
                            correct hai to approve karein.

                        </p>


                        <button
                            type="submit"
                            class="approve"
                            onclick="return confirm('Are you sure you want to approve this document?');"
                        >

                            Approve Document

                        </button>

                    </form>


                    <hr>


                    <!--
                    =====================================
                    REJECT FORM
                    =====================================

                    Reject karte waqt reason mandatory hai.
                    =====================================
                    -->

                    <form
                        method="POST"
                        action="review.php"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($csrfToken) ?>"
                        >


                        <input
                            type="hidden"
                            name="document_id"
                            value="<?= (int) $documentId ?>"
                        >


                        <input
                            type="hidden"
                            name="action"
                            value="reject"
                        >


                        <label>

                            <strong>
                                Rejection Reason
                            </strong>

                        </label>


                        <textarea
                            name="rejection_reason"
                            maxlength="2000"
                            required
                            placeholder="Explain clearly what the client needs to correct..."
                        ></textarea>


                        <button
                            type="submit"
                            class="reject"
                            onclick="return confirm('Reject this document and request a corrected version?');"
                        >

                            Reject Document

                        </button>

                    </form>


                <?php else: ?>


                    <p class="muted">

                        This document is no longer waiting
                        for review.

                    </p>


                    <?php if (
                        !empty(
                            $document['reviewer_name']
                        )
                    ): ?>

                        <p>

                            Reviewed by:
                            <strong>

                                <?= e(
                                    $document['reviewer_name']
                                ) ?>

                            </strong>

                        </p>

                    <?php endif; ?>


                    <?php if (
                        !empty(
                            $document['reviewed_at']
                        )
                    ): ?>

                        <p>

                            Reviewed at:
                            <strong>

                                <?= e(
                                    $document['reviewed_at']
                                ) ?>

                            </strong>

                        </p>

                    <?php endif; ?>


                <?php endif; ?>


            </section>


            <!--
            =============================================
            FILE SECURITY NOTICE
            =============================================

            Bhai intentionally yahan direct:

            <a href="/uploads/file.pdf">

            nahi banaya.

            Private client documents ko public uploads folder
            se expose karna security risk ho sakta hai.

            Final secure download controller:
                download.php?id=123

            database authorization verify karega aur phir
            controlled file response dega.
            =============================================
            -->

            <section class="card">

                <h2>
                    File Access
                </h2>

                <p class="muted">

                    Document storage is protected.
                    File preview/download should use the
                    authorized document controller.

                </p>

                <p>

                    <strong>
                        Stored filename:
                    </strong>

                    <br>

                    <?= e(
                        $document['stored_filename']
                    ) ?>

                </p>

            </section>


        </aside>


    </div>


</div>


</body>

</html>