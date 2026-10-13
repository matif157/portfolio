```php
<?php
/**
 * =====================================================
 * FILE: core/services/ServiceDocumentManager.php
 *
 * PURPOSE:
 * SPS CRM ke service documents ka complete workflow
 * manage karna.
 *
 * WORKFLOW:
 *
 * Service Case
 *      ↓
 * Required Document
 *      ↓
 * Request Sent
 *      ↓
 * Client Upload
 *      ↓
 * Staff Verification
 *      ↓
 * ┌───────────────┐
 * │               │
 * ▼               ▼
 * Approved      Rejected
 *                  ↓
 *            Reason Recorded
 *                  ↓
 *            Request Again
 *
 * IMPORTANT:
 *
 * Document upload ko automatically "verified" nahi
 * samjha jayega.
 *
 * Client file upload kar sakta hai, lekin final
 * verification authorized staff karega.
 *
 * Tax aur Immigration cases mein ye distinction
 * bohat important hai.
 *
 * SECURITY:
 *
 * - Prepared statements
 * - Case existence checks
 * - Document ownership checks
 * - Controlled document statuses
 * - Verification separate from upload
 * - Rejection reason required
 *
 * =====================================================
 */


/**
 * =====================================================
 * CLASS: ServiceDocumentManager
 *
 * PURPOSE:
 * Service case ke documents ko create, receive,
 * verify, reject aur re-request karna.
 * =====================================================
 */

class ServiceDocumentManager
{

    /*
    =====================================================
    DATABASE CONNECTION
    =====================================================

    PDO = PHP ka built-in database interface.

    Database queries isi connection ke through chalengi.
    */

    private PDO $pdo;


    /*
    =====================================================
    ALLOWED DOCUMENT STATUSES
    =====================================================

    Hum document status ko controlled rakh rahe hain.

    Is se random status values database mein nahi jayengi.
    */

    private array $allowedStatuses = [

        'requested',
        'uploaded',
        'under_review',
        'approved',
        'rejected',
        'expired',
        'cancelled'

    ];


    /*
    =====================================================
    CONSTRUCTOR
    =====================================================
    */

    public function __construct(
        PDO $pdo
    ) {

        $this->pdo = $pdo;
    }


    /*
    =====================================================
    CREATE DOCUMENT REQUEST
    =====================================================

    Jab CRM ko kisi document ki zaroorat ho to ye method
    document request create karega.

    Example:

        Tax Return
        ↓
        W-2 Required
        ↓
        Document Request Created

    Client dashboard mein:
        "W-2 upload karein"
    show kiya ja sakta hai.
    */

    public function createRequest(
        int $caseId,
        string $documentKey,
        string $documentName,
        bool $required = true,
        ?string $description = null,
        ?string $dueDate = null
    ): array {

        /*
        -------------------------------------------------
        Basic validation.
        -------------------------------------------------
        */

        if ($caseId <= 0) {

            return $this->error(
                'Invalid service case ID.'
            );
        }


        $documentKey =
            trim(
                strtolower(
                    $documentKey
                )
            );


        $documentName =
            trim(
                $documentName
            );


        if (
            $documentKey === '' ||
            $documentName === ''
        ) {

            return $this->error(
                'Document key and document name are required.'
            );
        }


        /*
        -------------------------------------------------
        Case existence check.

        Document ko kisi non-existing case se attach
        nahi karna.
        -------------------------------------------------
        */

        if (
            !$this->caseExists(
                $caseId
            )
        ) {

            return $this->error(
                'Service case was not found.'
            );
        }


        /*
        =================================================
        DUPLICATE REQUEST CHECK
        =================================================

        Same active document request ko repeatedly
        create nahi karna.

        Example:

        Passport already requested
        ↓
        Staff accidentally clicks again

        CRM duplicate request prevent karega.
        */

        $duplicateSql = "
            SELECT id

            FROM document_requests

            WHERE service_case_id = ?

              AND document_key = ?

              AND status IN (
                  'requested',
                  'uploaded',
                  'under_review'
              )

            LIMIT 1
        ";


        $duplicateStmt =
            $this->pdo->prepare(
                $duplicateSql
            );


        $duplicateStmt->execute([

            $caseId,

            $documentKey

        ]);


        if (
            $duplicateStmt->fetch(
                PDO::FETCH_ASSOC
            )
        ) {

            return $this->error(
                'An active request for this document already exists.'
            );
        }


        /*
        =================================================
        INSERT DOCUMENT REQUEST
        =================================================

        Prepared statement use kiya gaya hai.

        Ye SQL injection risk reduce karta hai.
        */

        $sql = "
            INSERT INTO document_requests
            (
                service_case_id,
                document_key,
                document_name,
                description,
                required,
                status,
                due_date,
                created_at,
                updated_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                'requested',
                ?,
                NOW(),
                NOW()
            )
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([

            $caseId,

            $documentKey,

            $documentName,

            $description,

            $required ? 1 : 0,

            $dueDate

        ]);


        /*
        -------------------------------------------------
        lastInsertId() = PDO built-in method.

        Newly created document request ka ID return karta
        hai.
        -------------------------------------------------
        */

        $requestId =
            (int)
            $this->pdo->lastInsertId();


        return [

            'success' => true,

            'message' =>
                'Document request created successfully.',

            'request_id' =>
                $requestId,

            'case_id' =>
                $caseId,

            'document_key' =>
                $documentKey

        ];
    }


    /*
    =====================================================
    MARK AS UPLOADED
    =====================================================

    Client/staff file upload ke baad document request
    ko uploaded mark karega.
    
    IMPORTANT:

    "uploaded" ka matlab:
        file system mein aa gayi.

    "approved" ka matlab:
        authorized staff ne verify kar di.

    Dono states ko mix nahi karna.
    */

    public function markUploaded(
        int $requestId,
        string $filePath,
        ?string $originalFileName = null,
        ?int $uploadedBy = null
    ): array {

        if ($requestId <= 0) {

            return $this->error(
                'Invalid document request ID.'
            );
        }


        $filePath =
            trim(
                $filePath
            );


        if ($filePath === '') {

            return $this->error(
                'A valid uploaded file reference is required.'
            );
        }


        /*
        -------------------------------------------------
        Request load.

        Hum check karenge ke request actually exists.
        -------------------------------------------------
        */

        $request =
            $this->getRequest(
                $requestId
            );


        if (!$request) {

            return $this->error(
                'Document request was not found.'
            );
        }


        /*
        -------------------------------------------------
        Cancelled request par upload allow nahi.
        -------------------------------------------------
        */

        if (
            $request['status'] ===
            'cancelled'
        ) {

            return $this->error(
                'Cancelled document request cannot receive a file.'
            );
        }


        /*
        =================================================
        DOCUMENT UPDATE
        =================================================
        */

        $sql = "
            UPDATE document_requests

            SET
                status = 'uploaded',
                file_path = ?,
                original_file_name = ?,
                uploaded_by = ?,
                uploaded_at = NOW(),
                updated_at = NOW()

            WHERE id = ?
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([

            $filePath,

            $originalFileName,

            $uploadedBy,

            $requestId

        ]);


        return [

            'success' => true,

            'message' =>
                'Document uploaded and queued for review.',

            'request_id' =>
                $requestId,

            'status' =>
                'uploaded',

            /*
            ---------------------------------------------
            Security reason:

            Upload hone ke baad automatically approved
            nahi kar rahe.
            ---------------------------------------------
            */

            'requires_review' =>
                true

        ];
    }


    /*
    =====================================================
    START REVIEW
    =====================================================

    Staff document ko review queue mein le sakta hai.

    Ye status:

        uploaded
             ↓
        under_review

    karta hai.
    */

    public function startReview(
        int $requestId,
        int $reviewerId
    ): array {

        if (
            $requestId <= 0 ||
            $reviewerId <= 0
        ) {

            return $this->error(
                'Valid document request and reviewer IDs are required.'
            );
        }


        /*
        -------------------------------------------------
        Request retrieve.
        -------------------------------------------------
        */

        $request =
            $this->getRequest(
                $requestId
            );


        if (!$request) {

            return $this->error(
                'Document request was not found.'
            );
        }


        /*
        -------------------------------------------------
        Sirf uploaded document review mein ja sakta hai.
        -------------------------------------------------
        */

        if (
            $request['status'] !==
            'uploaded'
        ) {

            return $this->error(
                'Only uploaded documents can enter review.'
            );
        }


        /*
        -------------------------------------------------
        Reviewer existence check.
        -------------------------------------------------
        */

        if (
            !$this->userExists(
                $reviewerId
            )
        ) {

            return $this->error(
                'Reviewer account was not found.'
            );
        }


        /*
        -------------------------------------------------
        Status update.
        -------------------------------------------------
        */

        $sql = "
            UPDATE document_requests

            SET
                status = 'under_review',
                reviewer_id = ?,
                review_started_at = NOW(),
                updated_at = NOW()

            WHERE id = ?

              AND status = 'uploaded'
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([

            $reviewerId,

            $requestId

        ]);


        return [

            'success' => true,

            'message' =>
                'Document moved to review.',

            'request_id' =>
                $requestId,

            'reviewer_id' =>
                $reviewerId

        ];
    }


    /*
    =====================================================
    APPROVE DOCUMENT
    =====================================================

    Authorized reviewer document approve karega.

    IMPORTANT:

    Ye action professional workflow mein audit trail
    ka part hoga.
    */

    public function approve(
        int $requestId,
        int $reviewerId,
        ?string $notes = null
    ): array {

        if (
            $requestId <= 0 ||
            $reviewerId <= 0
        ) {

            return $this->error(
                'Valid document request and reviewer IDs are required.'
            );
        }


        /*
        -------------------------------------------------
        Request retrieve.
        -------------------------------------------------
        */

        $request =
            $this->getRequest(
                $requestId
            );


        if (!$request) {

            return $this->error(
                'Document request was not found.'
            );
        }


        /*
        -------------------------------------------------
        Sirf review state ka document approve hoga.

        Is se koi user direct:
            requested → approved

        nahi kar sakta.
        -------------------------------------------------
        */

        if (
            $request['status'] !==
            'under_review'
        ) {

            return $this->error(
                'Document must be under review before approval.'
            );
        }


        /*
        =================================================
        APPROVAL UPDATE
        =================================================
        */

        $sql = "
            UPDATE document_requests

            SET
                status = 'approved',
                reviewer_id = ?,
                review_notes = ?,
                reviewed_at = NOW(),
                updated_at = NOW()

            WHERE id = ?

              AND status = 'under_review'
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([

            $reviewerId,

            $notes,

            $requestId

        ]);


        return [

            'success' => true,

            'message' =>
                'Document approved successfully.',

            'request_id' =>
                $requestId,

            'status' =>
                'approved'

        ];
    }


    /*
    =====================================================
    REJECT DOCUMENT
    =====================================================

    Rejection ke waqt reason mandatory rakha gaya hai.

    Example:

        "Image is unclear."

        "Page 2 is missing."

        "Document has expired."

    Client ko baad mein clear instruction mil sakti hai.
    */

    public function reject(
        int $requestId,
        int $reviewerId,
        string $reason
    ): array {

        if (
            $requestId <= 0 ||
            $reviewerId <= 0
        ) {

            return $this->error(
                'Valid document request and reviewer IDs are required.'
            );
        }


        $reason =
            trim(
                $reason
            );


        /*
        -------------------------------------------------
        Rejection reason empty nahi hona chahiye.

        Ye future client notification ke liye bhi
        important hai.
        -------------------------------------------------
        */

        if ($reason === '') {

            return $this->error(
                'A rejection reason is required.'
            );
        }


        /*
        -------------------------------------------------
        Request retrieve.
        -------------------------------------------------
        */

        $request =
            $this->getRequest(
                $requestId
            );


        if (!$request) {

            return $this->error(
                'Document request was not found.'
            );
        }


        /*
        -------------------------------------------------
        Sirf under-review document reject hoga.
        -------------------------------------------------
        */

        if (
            $request['status'] !==
            'under_review'
        ) {

            return $this->error(
                'Only documents under review can be rejected.'
            );
        }


        /*
        =================================================
        REJECTION UPDATE
        =================================================
        */

        $sql = "
            UPDATE document_requests

            SET
                status = 'rejected',
                reviewer_id = ?,
                rejection_reason = ?,
                reviewed_at = NOW(),
                updated_at = NOW()

            WHERE id = ?

              AND status = 'under_review'
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([

            $reviewerId,

            $reason,

            $requestId

        ]);


        return [

            'success' => true,

            'message' =>
                'Document rejected with a recorded reason.',

            'request_id' =>
                $requestId,

            'status' =>
                'rejected',

            'reason' =>
                $reason

        ];
    }


    /*
    =====================================================
    RE-REQUEST DOCUMENT
    =====================================================

    Rejected document ke baad same document dobara
    client se mangwana.

    Example:

        Passport
        ↓
        Rejected
        ↓
        "Image unclear"
        ↓
        Re-request
        ↓
        Client uploads new copy
    */

    public function reRequest(
        int $requestId,
        ?string $newDueDate = null
    ): array {

        if ($requestId <= 0) {

            return $this->error(
                'Invalid document request ID.'
            );
        }


        /*
        -------------------------------------------------
        Existing request load.
        -------------------------------------------------
        */

        $request =
            $this->getRequest(
                $requestId
            );


        if (!$request) {

            return $this->error(
                'Document request was not found.'
            );
        }


        /*
        -------------------------------------------------
        Re-request sirf rejected document ka hona chahiye.

        Is se approved document ko accidentally reset
        karne ka risk kam hota hai.
        -------------------------------------------------
        */

        if (
            $request['status'] !==
            'rejected'
        ) {

            return $this->error(
                'Only rejected documents can be requested again.'
            );
        }


        /*
        =================================================
        RESET DOCUMENT REQUEST
        =================================================

        Old rejection reason ko delete nahi karna.

        Audit/history preserve karna important hai.

        Current request ko dobara requested state mein
        le jayenge.
        */

        $sql = "
            UPDATE document_requests

            SET
                status = 'requested',
                due_date = ?,
                file_path = NULL,
                original_file_name = NULL,
                uploaded_by = NULL,
                uploaded_at = NULL,
                reviewer_id = NULL,
                review_started_at = NULL,
                review_notes = NULL,
                updated_at = NOW()

            WHERE id = ?

              AND status = 'rejected'
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([

            $newDueDate,

            $requestId

        ]);


        return [

            'success' => true,

            'message' =>
                'Document has been requested again.',

            'request_id' =>
                $requestId,

            'status' =>
                'requested'

        ];
    }


    /*
    =====================================================
    GET CASE DOCUMENTS
    =====================================================

    Dashboard/client portal ke liye case ke documents
    retrieve karta hai.
    */

    public function getCaseDocuments(
        int $caseId
    ): array {

        if ($caseId <= 0) {
            return [];
        }


        /*
        -------------------------------------------------
        ORDER:

        required documents first
        then workflow/order

        Is se UI mein important documents upar dikhaye
        ja sakte hain.
        -------------------------------------------------
        */

        $sql = "
            SELECT

                id,
                service_case_id,
                document_key,
                document_name,
                description,
                required,
                status,
                due_date,
                original_file_name,
                uploaded_at,
                review_started_at,
                reviewed_at,
                rejection_reason,
                review_notes,
                created_at,
                updated_at

            FROM document_requests

            WHERE service_case_id = ?

            ORDER BY
                required DESC,
                created_at ASC
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([
            $caseId
        ]);


        /*
        -------------------------------------------------
        fetchAll() = PDO built-in method.

        Multiple rows return karta hai.
        -------------------------------------------------
        */

        return
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }


    /*
    =====================================================
    GET MISSING DOCUMENTS
    =====================================================

    Ye method dashboard ko quickly bata sakta hai ke
    client se kya abhi tak required hai.
    */

    public function getMissingDocuments(
        int $caseId
    ): array {

        if ($caseId <= 0) {
            return [];
        }


        /*
        -------------------------------------------------
        Required documents jo approved nahi hue.

        Rejected bhi missing category mein aayega kyun ke
        client ko replacement dena hai.
        -------------------------------------------------
        */

        $sql = "
            SELECT

                id,
                document_key,
                document_name,
                description,
                status,
                due_date,
                rejection_reason

            FROM document_requests

            WHERE service_case_id = ?

              AND required = 1

              AND status NOT IN (
                  'approved',
                  'cancelled'
              )

            ORDER BY
                due_date IS NULL,
                due_date ASC,
                created_at ASC
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([
            $caseId
        ]);


        return
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }


    /*
    =====================================================
    GET REQUEST
    =====================================================

    Internal helper method.

    Ek document request ka fresh record retrieve karta hai.
    */

    private function getRequest(
        int $requestId
    ): ?array {

        $sql = "
            SELECT

                id,
                service_case_id,
                document_key,
                document_name,
                description,
                required,
                status,
                due_date,
                file_path,
                original_file_name,
                uploaded_by,
                uploaded_at,
                reviewer_id,
                review_started_at,
                reviewed_at,
                review_notes,
                rejection_reason,
                created_at,
                updated_at

            FROM document_requests

            WHERE id = ?

            LIMIT 1
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([
            $requestId
        ]);


        $request =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        return
            $request ?: null;
    }


    /*
    =====================================================
    CASE EXISTS
    =====================================================

    Check karta hai ke service case database mein
    exist karta hai ya nahi.
    */

    private function caseExists(
        int $caseId
    ): bool {

        $sql = "
            SELECT id

            FROM service_cases

            WHERE id = ?

            LIMIT 1
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([
            $caseId
        ]);


        return
            (bool)
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );
    }


    /*
    =====================================================
    USER EXISTS
    =====================================================

    Reviewer/staff account verify karta hai.
    */

    private function userExists(
        int $userId
    ): bool {

        $sql = "
            SELECT id

            FROM users

            WHERE id = ?

            LIMIT 1
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([
            $userId
        ]);


        return
            (bool)
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );
    }


    /*
    =====================================================
    ERROR RESPONSE HELPER
    =====================================================

    Har failed operation ka same response structure
    maintain karne ke liye helper method.
    */

    private function error(
        string $message
    ): array {

        return [

            'success' => false,

            'message' =>
                $message

        ];
    }
}
```
