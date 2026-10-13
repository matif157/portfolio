```php
<?php
/**
 * =====================================================
 * FILE: client/upload_document.php
 *
 * PURPOSE:
 * Client ke uploaded document ko securely receive,
 * validate aur database mein register karna.
 *
 * WORKFLOW:
 *
 * Client opens upload page
 *      ↓
 * Login check
 *      ↓
 * Document request verify
 *      ↓
 * Confirm request belongs to logged-in client
 *      ↓
 * Show upload form
 *      ↓
 * POST request receive
 *      ↓
 * Validate uploaded file
 *      ↓
 * Detect real MIME type
 *      ↓
 * Generate secure random filename
 *      ↓
 * Save file into private storage
 *      ↓
 * Insert document metadata
 *      ↓
 * Update document request
 *      ↓
 * Redirect to document checklist
 *
 * BHAI:
 * Browser se aane wale filename, extension ya MIME type
 * par blindly trust nahi karna.
 *
 * Security validation server side hogi.
 * =====================================================
 */


/*
=========================================================
1. LOAD CRM BOOTSTRAP
=========================================================

require_once = PHP built-in include mechanism.

Why:
Central bootstrap file database connection, sessions aur
common CRM configuration load karegi.

Bhai har page par alag database connection banana
avoid karna hai.
=========================================================
*/

require_once '../config/bootstrap.php';


/*
=========================================================
2. SESSION AUTHENTICATION
=========================================================

isset() = PHP built-in function.

Why:
Check karna ke client logged in hai ya nahi.

Protected upload page ko unauthenticated visitor access
nahi kar sakta.
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

    /*
    exit() = PHP built-in function.

    Redirect ke baad script ko immediately stop karta hai.
    */
    exit;
}


/*
=========================================================
3. DATABASE CONNECTION CHECK
=========================================================

Bhai upload ke waqt database aur filesystem dono
coordinate karne hain.

Agar PDO available nahi hai to silently continue
nahi karna.
=========================================================
*/

if (!isset($pdo) || !($pdo instanceof PDO)) {

    throw new RuntimeException(
        'Database connection is not available.'
    );
}


/*
=========================================================
4. CLIENT ID
=========================================================

intval() = PHP built-in function.

Session value ko integer mein convert karte hain.

Is value ko prepared statements ke through database
queries mein use karenge.
=========================================================
*/

$clientId = (int) $_SESSION['client_id'];


/*
=========================================================
5. REQUEST ID
=========================================================

URL example:

upload_document.php?request=125

Bhai sirf request ID mil jana enough nahi hai.

Baad mein database se verify karenge ke ye request
logged-in client ki hi hai.
=========================================================
*/

$requestId = isset($_GET['request'])
    ? (int) $_GET['request']
    : 0;


/*
=========================================================
6. BASIC REQUEST VALIDATION
=========================================================
*/

if ($requestId <= 0) {

    http_response_code(400);

    echo 'Invalid document request.';
    exit;
}


/*
=========================================================
7. LOAD DOCUMENT REQUEST
=========================================================

Bhai yahan sabse important authorization check hai.

Query mein:

dr.id = ?
AND dr.client_id = ?

dono conditions hain.

Iska matlab:

Client A agar URL manually change karke Client B ki
request ID enter kare, to record return hi nahi hoga.

Ye Insecure Direct Object Reference (IDOR) se protection
ka important part hai.
=========================================================
*/

$requestSql = "
    SELECT
        dr.id,
        dr.client_id,
        dr.service_case_id,
        dr.document_requirement_id,
        dr.document_type_id,
        dr.title,
        dr.instructions,
        dr.status,
        dr.due_at,

        dt.document_name,
        dt.category,
        dt.client_upload_allowed

    FROM document_requests dr

    INNER JOIN document_types dt
        ON dt.id = dr.document_type_id

    WHERE dr.id = ?
      AND dr.client_id = ?

    LIMIT 1
";


/*
prepare() = PDO method.

Why:
SQL query ko safely parameterize karne ke liye.
*/

$requestStmt = $pdo->prepare($requestSql);


/*
execute() = PDO method.

Why:
Actual request ID aur client ID query parameters mein
safely pass karne ke liye.
*/

$requestStmt->execute([
    $requestId,
    $clientId
]);


/*
fetch() = PDO method.

Why:
Ek document request retrieve karne ke liye.
*/

$documentRequest = $requestStmt->fetch(
    PDO::FETCH_ASSOC
);


/*
=========================================================
8. REQUEST NOT FOUND
=========================================================

Agar request nahi mili:

- request invalid ho sakti hai
- request kisi aur client ki ho sakti hai
- request delete/cancel ho sakti hai

Client ko sensitive database information nahi deni.
=========================================================
*/

if (!$documentRequest) {

    http_response_code(404);

    echo 'Document request not found.';
    exit;
}


/*
=========================================================
9. CLIENT UPLOAD PERMISSION
=========================================================

Master document type decide karta hai ke client upload
kar sakta hai ya nahi.

Example:

Passport → client upload allowed
Internal Review Sheet → client upload NOT allowed

Bhai har document ke liye client upload enable karna
safe assumption nahi hai.
=========================================================
*/

if ((int) $documentRequest['client_upload_allowed'] !== 1) {

    http_response_code(403);

    echo 'Client upload is not allowed for this document.';
    exit;
}


/*
=========================================================
10. CHECK REQUEST STATUS
=========================================================

Approved request par dobara upload allow nahi karna.

Rejected request par new version upload allowed hai.

Requested / rejected / partially_received:
    upload allowed

Approved:
    upload blocked

Under review:
    upload blocked

Bhai ye duplicate ya accidental submissions ko
control karega.
=========================================================
*/

$allowedRequestStatuses = [
    'requested',
    'rejected',
    'partially_received'
];


if (
    !in_array(
        $documentRequest['status'],
        $allowedRequestStatuses,
        true
    )
) {

    http_response_code(409);

    echo 'This document request is not currently accepting uploads.';
    exit;
}


/*
=========================================================
11. UPLOAD CONFIGURATION
=========================================================

Bhai ye limits central configuration mein baad mein
move hongi.

Filhaal:

PDF + JPG + JPEG + PNG

allowed hain.

Maximum file size:

10 MB

Important:
Browser-side validation sirf convenience hai.

Real security server-side validation se hogi.
=========================================================
*/

$maxFileSize = 10 * 1024 * 1024;


/*
Allowed extensions.

Extension alone security proof nahi hai.
Neeche MIME validation bhi hogi.
*/

$allowedExtensions = [
    'pdf',
    'jpg',
    'jpeg',
    'png'
];


/*
Real MIME types.

finfo_file() se server actual file content ka MIME
detect karega.
*/

$allowedMimeTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png'
];


/*
=========================================================
12. FORM STATE
=========================================================

GET request:
    upload form show

POST request:
    upload process

$_SERVER = PHP predefined superglobal array.

REQUEST_METHOD PHP server se current HTTP method deta hai.
=========================================================
*/

$isPost = (
    $_SERVER['REQUEST_METHOD'] ?? 'GET'
) === 'POST';


/*
Default values.
*/

$errors = [];

$successMessage = '';


/*
=========================================================
13. HANDLE POST UPLOAD
=========================================================

Bhai HTML output se pehle POST processing rakhi hai.

Why?

Agar successful upload ho jaye to redirect karna hai.

Redirect ke liye header() ko HTML output se pehle call
hona zaroori hota hai.
=========================================================
*/

if ($isPost) {


    /*
    =====================================================
    13-A. CSRF PLACEHOLDER
    =====================================================

    Bhai final security layer mein CSRF protection central
    middleware se enforce karenge.

    Is stage par form token ki architecture reserved hai.

    IMPORTANT:
    Production deployment se pehle CSRF middleware mandatory
    hoga.
    =====================================================
    */

    /*
    Future:
    verify_csrf_token($_POST['csrf_token'] ?? '');
    */


    /*
    =====================================================
    13-B. CHECK FILE INPUT
    =====================================================
    */

    if (
        !isset($_FILES['document']) ||
        !is_array($_FILES['document'])
    ) {

        $errors[] =
            'Please select a document to upload.';

    } else {

        /*
        =================================================
        13-C. EXTRACT UPLOAD DATA
        =================================================

        $_FILES = PHP predefined superglobal array.

        Browser se uploaded file ki temporary information
        yahan milti hai.
        =================================================
        */

        $uploadedFile = $_FILES['document'];


        /*
        =================================================
        13-D. CHECK UPLOAD ERROR
        =================================================

        PHP upload system error code deta hai.

        UPLOAD_ERR_OK = PHP predefined constant.

        Iska matlab upload successfully receive hua.
        =================================================
        */

        if (
            $uploadedFile['error'] !== UPLOAD_ERR_OK
        ) {

            $errors[] =
                'The document could not be uploaded. Please try again.';

        }


        /*
        =================================================
        13-E. FILE SIZE CHECK
        =================================================

        filesize information browser par trust nahi karni.

        $_FILES['size'] server-provided upload size hai.

        Maximum 10 MB.
        =================================================
        */

        if (
            empty($errors) &&
            (
                !isset($uploadedFile['size']) ||
                $uploadedFile['size'] <= 0
            )
        ) {

            $errors[] =
                'The uploaded document is empty.';
        }


        if (
            empty($errors) &&
            $uploadedFile['size'] > $maxFileSize
        ) {

            $errors[] =
                'The document is too large. Maximum size is 10 MB.';
        }


        /*
        =================================================
        13-F. TEMPORARY FILE CHECK
        =================================================

        is_uploaded_file() = PHP built-in security function.

        Why:
Confirm karta hai ke file actually PHP HTTP upload
mechanism ke through aayi hai.

Is se arbitrary server path ko upload file samajhne
ka risk kam hota hai.
        =================================================
        */

        if (
            empty($errors) &&
            !is_uploaded_file(
                $uploadedFile['tmp_name']
            )
        ) {

            $errors[] =
                'Invalid upload source.';
        }


        /*
        =================================================
        13-G. ORIGINAL FILENAME
        =================================================

        basename() = PHP built-in function.

        Original filename sirf display/audit purpose ke liye
        preserve karenge.

        Is filename ko storage path mein directly use
        NAHI karenge.

        Example dangerous idea:

        uploads/../../something.php

        Isliye actual stored filename random hoga.
        =================================================
        */

        $originalFilename = basename(
            (string) $uploadedFile['name']
        );


        /*
        =================================================
        13-H. EXTENSION CHECK
        =================================================

        pathinfo() = PHP built-in function.

        Why:
Filename se extension extract karne ke liye.

        strtolower() = PHP built-in string function.

        Why:
JPG, Jpg, jpg ko same treat karne ke liye.
        =================================================
        */

        $extension = strtolower(
            pathinfo(
                $originalFilename,
                PATHINFO_EXTENSION
            )
        );


        if (
            empty($errors) &&
            !in_array(
                $extension,
                $allowedExtensions,
                true
            )
        ) {

            $errors[] =
                'This file type is not allowed. Please upload PDF, JPG, JPEG or PNG.';
        }


        /*
        =================================================
        13-I. REAL MIME TYPE DETECTION
        =================================================

        finfo_open() = PHP built-in Fileinfo function.

        Why:
File extension fake ho sakti hai.

        Example:
malicious.php renamed to malicious.pdf

Extension check alone is not enough.

Fileinfo actual file content ka detected MIME type
provide karta hai.
        =================================================
        */

        if (
            empty($errors)
        ) {

            $fileInfo = finfo_open(
                FILEINFO_MIME_TYPE
            );


            if ($fileInfo === false) {

                $errors[] =
                    'The server could not verify the uploaded file.';

            } else {

                /*
                finfo_file() = PHP built-in Fileinfo function.

                Actual temporary file ka MIME detect karta hai.
                */

                $detectedMime = finfo_file(
                    $fileInfo,
                    $uploadedFile['tmp_name']
                );


                /*
                finfo_close() = PHP built-in function.

                Fileinfo resource close karna.
                */

                finfo_close($fileInfo);


                if (
                    !in_array(
                        $detectedMime,
                        $allowedMimeTypes,
                        true
                    )
                ) {

                    $errors[] =
                        'The uploaded document format could not be verified.';
                }
            }
        }


        /*
        =================================================
        13-J. EXTENSION + MIME CONSISTENCY
        =================================================

        Bhai PDF extension ke saath JPEG MIME accept nahi
        karna.

        Ye additional sanity check hai.
        =================================================
        */

        if (
            empty($errors)
        ) {

            $validExtensionMimePairs = [

                'pdf' => [
                    'application/pdf'
                ],

                'jpg' => [
                    'image/jpeg'
                ],

                'jpeg' => [
                    'image/jpeg'
                ],

                'png' => [
                    'image/png'
                ]

            ];


            if (
                !isset(
                    $validExtensionMimePairs[$extension]
                ) ||
                !in_array(
                    $detectedMime,
                    $validExtensionMimePairs[$extension],
                    true
                )
            ) {

                $errors[] =
                    'The file extension does not match its actual file type.';
            }
        }


        /*
        =================================================
        13-K. IMAGE VALIDATION
        =================================================

        Agar image hai to getimagesize() additional
        verification karega.

        getimagesize() = PHP built-in function.

        Why:
Image dimensions/structure ko inspect karta hai.

        PDF ke liye ye check apply nahi hoga.
        =================================================
        */

        if (
            empty($errors) &&
            in_array(
                $extension,
                ['jpg', 'jpeg', 'png'],
                true
            )
        ) {

            $imageInfo = @getimagesize(
                $uploadedFile['tmp_name']
            );


            if ($imageInfo === false) {

                $errors[] =
                    'The uploaded image could not be verified.';
            }
        }


        /*
        =================================================
        13-L. GENERATE SECURE STORAGE NAME
        =================================================

        random_bytes() = PHP built-in cryptographic function.

        Why:
Unpredictable random value generate karne ke liye.

        bin2hex() = PHP built-in function.

        Random bytes ko safe hexadecimal string mein
        convert karta hai.

        Original filename ko storage filename mein
        use nahi karenge.
        =================================================
        */

        if (empty($errors)) {

            try {

                $randomName = bin2hex(
                    random_bytes(24)
                );

            } catch (Throwable $exception) {

                $errors[] =
                    'A secure filename could not be generated.';
            }
        }


        /*
        =================================================
        13-M. PRIVATE STORAGE DIRECTORY
        =================================================

        BHAI:

        Actual production architecture mein storage path
        web root ke bahar hona chahiye.

        Example:

        C:/xampp/private_storage/sps_documents/

        NOT:

        C:/xampp/htdocs/uploads/

        Agar storage configuration available hai to
        usko prefer karenge.
        =================================================
        */

        if (empty($errors)) {

            $privateStorageRoot =
                defined('PRIVATE_DOCUMENT_STORAGE')
                    ? PRIVATE_DOCUMENT_STORAGE
                    : dirname(
                        __DIR__,
                        2
                    ) . DIRECTORY_SEPARATOR
                    . 'private_storage'
                    . DIRECTORY_SEPARATOR
                    . 'documents';


            /*
            mkdir() = PHP built-in filesystem function.

            Recursive TRUE:
            parent directories bhi zarurat par create ho sakti hain.
            */

            if (
                !is_dir($privateStorageRoot)
            ) {

                if (
                    !mkdir(
                        $privateStorageRoot,
                        0750,
                        true
                    )
                ) {

                    $errors[] =
                        'Secure document storage is not available.';
                }
            }
        }


        /*
        =================================================
        13-N. FINAL STORAGE PATH
        =================================================
        */

        if (empty($errors)) {

            /*
            Storage filename random hai.

            Original extension retain kar rahe hain taake
            controlled file serving easy ho.
            */

            $storedFilename =
                $randomName . '.' . $extension;


            $storagePath =
                $privateStorageRoot
                . DIRECTORY_SEPARATOR
                . $storedFilename;
        }


        /*
        =================================================
        13-O. MOVE UPLOADED FILE
        =================================================

        move_uploaded_file() = PHP built-in function.

        Why:
PHP temporary upload location se file ko final private
storage location mein move karta hai.
        =================================================
        */

        if (empty($errors)) {

            if (
                !move_uploaded_file(
                    $uploadedFile['tmp_name'],
                    $storagePath
                )
            ) {

                $errors[] =
                    'The document could not be securely stored.';
            }
        }


        /*
        =================================================
        13-P. DATABASE TRANSACTION
        =================================================

        BHAI yahan important concept hai.

        Do operations hain:

        1. File filesystem mein save
        2. Database record create

        Database transaction sirf database operations ko
        rollback kar sakti hai.

        Agar DB insert fail ho gaya to physical file ko
        manually remove karna padega.

        Isliye $fileWasStored flag rakhenge.
        =================================================
        */

        $fileWasStored =
            empty($errors) &&
            isset($storagePath) &&
            is_file($storagePath);


        if (empty($errors)) {

            try {

                /*
                beginTransaction() = PDO method.

                Database changes ko atomic operation mein
                group karta hai.
                */

                $pdo->beginTransaction();


                /*
                =================================================
                13-Q. DOCUMENT VERSION NUMBER
                =================================================

                Same request ke previous uploads count karenge.

                Rejected document ke baad new upload:

                v1
                v2
                v3

                ban sakta hai.
                =================================================
                */

                $versionSql = "
                    SELECT
                        COALESCE(
                            MAX(version_number),
                            0
                        ) + 1 AS next_version

                    FROM client_documents

                    WHERE document_request_id = ?
                ";

                $versionStmt =
                    $pdo->prepare($versionSql);

                $versionStmt->execute([
                    $requestId
                ]);

                $nextVersion =
                    (int) $versionStmt->fetchColumn();


                /*
                =================================================
                13-R. FILE HASH
                =================================================

                hash_file() = PHP built-in function.

                Why:
Uploaded file ka SHA-256 fingerprint generate karna.

                Iska use:
- integrity
- duplicate detection
- audit

ke liye ho sakta hai.
                =================================================
                */

                $fileHash = hash_file(
                    'sha256',
                    $storagePath
                );


                if ($fileHash === false) {

                    throw new RuntimeException(
                        'Could not calculate document fingerprint.'
                    );
                }


                /*
                =================================================
                13-S. INSERT DOCUMENT
                =================================================

                Sirf metadata database mein store ho raha hai.

                Actual PDF/image private filesystem mein hai.
                =================================================
                */

                $documentInsertSql = "
                    INSERT INTO client_documents (

                        client_id,
                        service_case_id,
                        document_request_id,
                        document_type_id,

                        original_filename,
                        stored_filename,
                        storage_path,

                        mime_type,
                        file_extension,
                        file_size,
                        file_hash,

                        version_number,
                        status,
                        upload_source,

                        uploaded_by

                    ) VALUES (

                        ?,
                        ?,
                        ?,
                        ?,

                        ?,
                        ?,
                        ?,

                        ?,
                        ?,
                        ?,
                        ?,

                        ?,
                        'under_review',
                        'client',

                        NULL

                    )
                ";


                $documentInsertStmt =
                    $pdo->prepare(
                        $documentInsertSql
                    );


                $documentInsertStmt->execute([

                    $clientId,

                    $documentRequest['service_case_id'],

                    $requestId,

                    $documentRequest['document_type_id'],

                    $originalFilename,

                    $storedFilename,

                    $storagePath,

                    $detectedMime,

                    $extension,

                    (int) $uploadedFile['size'],

                    $fileHash,

                    $nextVersion

                ]);


                /*
                =================================================
                13-T. GET NEW DOCUMENT ID
                =================================================

                lastInsertId() = PDO method.

                Newly inserted document ka database ID return
                karta hai.
                =================================================
                */

                $documentId =
                    (int) $pdo->lastInsertId();


                /*
                =================================================
                13-U. UPDATE DOCUMENT REQUEST
                =================================================

                Ab request ko "under_review" kar rahe hain.

                Meaning:

                Client ne required document provide kar diya.
                Ab staff review karega.
                =================================================
                */

                $requestUpdateSql = "
                    UPDATE document_requests

                    SET
                        status = 'under_review',
                        received_at = COALESCE(
                            received_at,
                            NOW()
                        ),
                        updated_at = NOW()

                    WHERE id = ?
                      AND client_id = ?
                ";


                $requestUpdateStmt =
                    $pdo->prepare(
                        $requestUpdateSql
                    );


                $requestUpdateStmt->execute([

                    $requestId,
                    $clientId

                ]);


                /*
                =================================================
                13-V. COMMIT
                =================================================

                commit() = PDO method.

                Database changes permanently save karta hai.
                =================================================
                */

                $pdo->commit();


                /*
                =================================================
                13-W. SUCCESS REDIRECT
                =================================================

                PRG pattern:

                POST
                  ↓
                Redirect
                  ↓
                GET

                Is se browser refresh karne par same file
                dobara upload hone ka risk kam hota hai.
                =================================================
                */

                header(
                    'Location: documents.php?service='
                    . (int) $documentRequest['service_case_id']
                    . '&uploaded=1'
                );

                exit;


            } catch (Throwable $exception) {

                /*
                =================================================
                ROLLBACK
                =================================================

                rollback() = PDO method.

                Agar database operation fail ho to database
                changes reverse kar deta hai.
                =================================================
                */

                if ($pdo->inTransaction()) {

                    $pdo->rollBack();
                }


                /*
                =================================================
                DELETE ORPHAN FILE
                =================================================

                Agar database insert fail hua lekin physical
                file save ho gayi thi to orphan file nahi
                chhorni.

                unlink() = PHP built-in filesystem function.

                Why:
Stored file ko remove karna.
                =================================================
                */

                if (
                    $fileWasStored &&
                    isset($storagePath) &&
                    is_file($storagePath)
                ) {

                    @unlink($storagePath);
                }


                /*
                Production mein raw exception client ko
                show nahi karna.

                Detailed exception internal logging system
                mein jayegi.
                */

                error_log(
                    'SPS document upload failed: '
                    . $exception->getMessage()
                );


                $errors[] =
                    'The document could not be saved. Please try again.';
            }
        }
    }
}


/*
=========================================================
14. FRIENDLY SUCCESS MESSAGE
=========================================================

GET redirect ke baad uploaded=1 mil sakta hai.

Client ko technical message ke bajaye friendly message
show karenge.
=========================================================
*/

if (
    isset($_GET['uploaded']) &&
    $_GET['uploaded'] === '1'
) {

    $successMessage =
        'Your document has been uploaded successfully. Our team will review it.';
}


/*
=========================================================
15. SAFE OUTPUT HELPER
=========================================================

htmlspecialchars() = PHP built-in security function.

Why:
HTML output mein user/database text ko safely display
karna.
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
        Upload Document
    </title>

    <style>

        /*
        =====================================================
        SIMPLE UPLOAD UI
        =====================================================

        Bhai final SPS design system mein ye CSS central
        stylesheet mein move hogi.

        Filhaal page ko independently test kar sakte hain.
        =====================================================
        */

        body {
            margin: 0;
            background: #f5f7fa;
            font-family: Arial, sans-serif;
            color: #1f2937;
        }

        .page {
            max-width: 700px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 28px;
        }

        h1 {
            margin-top: 0;
        }

        .document-name {
            color: #64748b;
            margin-bottom: 24px;
        }

        .message {
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error-list {
            margin: 0;
            padding-left: 20px;
        }

        .instructions {
            background: #f8fafc;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        input[type="file"] {
            width: 100%;
            margin: 15px 0 20px;
        }

        button {
            border: 0;
            border-radius: 9px;
            padding: 12px 20px;
            background: #111827;
            color: #ffffff;
            cursor: pointer;
        }

        .back {
            display: inline-block;
            margin-top: 18px;
            color: #475569;
            text-decoration: none;
        }

    </style>

</head>

<body>

<div class="page">

    <div class="card">

        <h1>
            Upload Document
        </h1>


        <!--
        =====================================================
        DOCUMENT NAME
        =====================================================
        -->

        <div class="document-name">

            <?= e($documentRequest['title']) ?>

            —
            <?= e($documentRequest['document_name']) ?>

        </div>


        <!--
        =====================================================
        ERROR MESSAGES
        =====================================================
        -->

        <?php if (!empty($errors)): ?>

            <div class="message error">

                <strong>
                    We couldn't upload your document.
                </strong>

                <ul class="error-list">

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= e($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


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
        CLIENT INSTRUCTIONS
        =====================================================
        -->

        <?php if (
            !empty($documentRequest['instructions'])
        ): ?>

            <div class="instructions">

                <?= nl2br(
                    e($documentRequest['instructions'])
                ) ?>

            </div>

        <?php endif; ?>


        <!--
        =====================================================
        UPLOAD FORM
        =====================================================

        enctype="multipart/form-data"

        BHAI:
File upload ke liye ye HTML form attribute mandatory hai.

Normal form encoding file bytes ko server tak nahi
bhej sakti.
        =====================================================
        -->

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <label for="document">

                Select your document

            </label>


            <!--
            accept attribute sirf client-side convenience hai.

            IMPORTANT:
Server-side MIME validation phir bhi mandatory hai.
            -->

            <input
                type="file"
                id="document"
                name="document"
                accept=".pdf,.jpg,.jpeg,.png"
                required
            >


            <p>

                Accepted formats:
                PDF, JPG, JPEG, PNG

                <br>

                Maximum size:
                10 MB

            </p>


            <button type="submit">

                Upload Document

            </button>

        </form>


        <!--
        =====================================================
        BACK TO DOCUMENTS
        =====================================================
        -->

        <a
            class="back"
            href="documents.php?service=<?= (int) $documentRequest['service_case_id'] ?>"
        >

            ← Back to Documents

        </a>

    </div>

</div>

</body>

</html>
```
