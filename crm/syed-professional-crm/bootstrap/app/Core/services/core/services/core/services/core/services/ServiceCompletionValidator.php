 <?php
/**
 * =====================================================
 * FILE: core/services/ServiceCompletionValidator.php
 *
 * PURPOSE:
 * Ye file check karti hai ke SPS ka service case
 * waqai complete karne ke qabil hai ya nahi.
 *
 * WORKFLOW:
 *
 * Complete Request
 *        ↓
 * Case Check
 *        ↓
 * Workflow Check
 *        ↓
 * Documents Check
 *        ↓
 * Professional Review Check
 *        ↓
 * Client Approval Check
 *        ↓
 * Filing / Submission Check
 *        ↓
 * Blockers Check
 *        ↓
 * ALLOWED / BLOCKED
 *
 * IMPORTANT:
 *
 * Ye class professional/tax/legal decision nahi karti.
 *
 * Ye sirf CRM ke predefined completion rules verify
 * karti hai.
 *
 * Example:
 *
 * Agar Tax Filing case mein:
 *
 * - required document missing hai
 * - professional review pending hai
 * - filing record missing hai
 *
 * to CRM case ko complete nahi hone dega.
 *
 * SECURITY:
 *
 * User interface par button hide karna security nahi hai.
 *
 * Actual validation server-side honi chahiye.
 *
 * Isliye final completion se pehle ye class dobara
 * database se conditions verify karegi.
 *
 * =====================================================
 */


/**
 * =====================================================
 * CLASS: ServiceCompletionValidator
 *
 * PURPOSE:
 * Service case ke completion requirements validate
 * karna.
 * =====================================================
 */

class ServiceCompletionValidator
{

    /*
    =====================================================
    DATABASE CONNECTION
    =====================================================

    PDO = PHP ka built-in database interface.

    Is connection ke through hum database se fresh
    information verify karenge.
    */

    private PDO $pdo;


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
    VALIDATE CASE
    =====================================================

    Ye main public method hai.

    Is method ko complete-case action se pehle call
    kiya jayega.

    Return example:

    [
        'allowed' => false,
        'message' => 'Case cannot be completed.',
        'checks' => [...]
    ]
    */

    public function validate(
        int $caseId
    ): array {

        /*
        -------------------------------------------------
        Invalid case ID ko start mein reject karna.
        -------------------------------------------------
        */

        if ($caseId <= 0) {

            return $this->blockedResult(
                'Invalid service case ID.'
            );
        }


        /*
        -------------------------------------------------
        Case ki fresh database information load karein.
        -------------------------------------------------
        */

        $case =
            $this->getCase(
                $caseId
            );


        if (!$case) {

            return $this->blockedResult(
                'Service case was not found.'
            );
        }


        /*
        -------------------------------------------------
        Already completed case ko dobara complete nahi
        karna.
        -------------------------------------------------
        */

        if (
            $case['status'] === 'completed'
        ) {

            return $this->blockedResult(
                'This service case is already completed.'
            );
        }


        /*
        -------------------------------------------------
        Cancelled case complete nahi ho sakta.
        -------------------------------------------------
        */

        if (
            $case['status'] === 'cancelled'
        ) {

            return $this->blockedResult(
                'Cancelled service cases cannot be completed.'
            );
        }


        /*
        =================================================
        SERVICE-SPECIFIC RULES
        =================================================

        Har service ki completion requirements same nahi
        hotin.

        Tax aur immigration ke rules specially strict
        rakhe jayenge.

        Generic service ke liye basic workflow checks
        enough ho sakte hain.
        */

        $checks = [];


        /*
        -------------------------------------------------
        CHECK 1:
        Workflow steps complete hain?
        -------------------------------------------------
        */

        $workflowCheck =
            $this->checkWorkflow(
                $caseId
            );

        $checks['workflow'] =
            $workflowCheck;


        /*
        -------------------------------------------------
        CHECK 2:
        Required documents complete hain?
        -------------------------------------------------
        */

        $documentCheck =
            $this->checkRequiredDocuments(
                $caseId
            );

        $checks['documents'] =
            $documentCheck;


        /*
        -------------------------------------------------
        CHECK 3:
        Professional review.

        Tax aur immigration cases ke liye ye especially
        important hai.

        CRM professional judgment ko automate nahi karega.
        -------------------------------------------------
        */

        $professionalCheck =
            $this->checkProfessionalReview(
                $caseId,
                $case
            );

        $checks['professional_review'] =
            $professionalCheck;


        /*
        -------------------------------------------------
        CHECK 4:
        Client approval.

        Sirf un services par apply hoga jahan template
        client approval require karta hai.
        -------------------------------------------------
        */

        $clientApprovalCheck =
            $this->checkClientApproval(
                $caseId,
                $case
            );

        $checks['client_approval'] =
            $clientApprovalCheck;


        /*
        -------------------------------------------------
        CHECK 5:
        Filing / submission evidence.

        Tax/immigration workflows mein official action
        record hona important hai.

        Example:

        - filing submitted
        - confirmation received
        - application submitted
        - receipt uploaded
        -------------------------------------------------
        */

        $filingCheck =
            $this->checkFilingRecord(
                $caseId,
                $case
            );

        $checks['filing'] =
            $filingCheck;


        /*
        =================================================
        FAILED CHECKS COLLECT
        =================================================

        array filtering ke through sirf failed checks
        identify karte hain.

        array_filter() = PHP built-in function.
        */

        $failedChecks =
            array_filter(
                $checks,
                function (
                    array $check
                ): bool {

                    return
                        !(
                            $check['passed']
                            ?? false
                        );
                }
            );


        /*
        -------------------------------------------------
        Agar koi bhi mandatory check fail hua to case
        complete nahi ho sakta.
        -------------------------------------------------
        */

        if (
            !empty($failedChecks)
        ) {

            return [

                'allowed' => false,

                'message' =>
                    'Service case cannot be completed yet.',

                'case_id' =>
                    $caseId,

                'service_key' =>
                    $case['service_key'],

                'checks' =>
                    $checks,

                'failed_checks' =>
                    array_keys(
                        $failedChecks
                    )

            ];
        }


        /*
        -------------------------------------------------
        Sab checks pass.
        -------------------------------------------------
        */

        return [

            'allowed' => true,

            'message' =>
                'Service case passed completion validation.',

            'case_id' =>
                $caseId,

            'service_key' =>
                $case['service_key'],

            'checks' =>
                $checks,

            'failed_checks' =>
                []

        ];
    }


    /*
    =====================================================
    GET CASE
    =====================================================
    */

    private function getCase(
        int $caseId
    ): ?array {

        /*
        -------------------------------------------------
        Case + service information retrieve kar rahe hain.
        -------------------------------------------------
        */

        $sql = "
            SELECT
                sc.id,
                sc.client_id,
                sc.service_key,
                sc.service_name,
                sc.status,
                sc.current_step,
                sc.assigned_to,
                sc.created_by,
                sc.created_at,
                sc.updated_at

            FROM service_cases sc

            WHERE sc.id = ?

            LIMIT 1
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([
            $caseId
        ]);


        $case =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        return $case ?: null;
    }


    /*
    =====================================================
    CHECK WORKFLOW
    =====================================================

    Verify karta hai ke koi pending/active workflow
    step remaining to nahi.
    */

    private function checkWorkflow(
        int $caseId
    ): array {

        /*
        -------------------------------------------------
        Total steps count.
        -------------------------------------------------
        */

        $sql = "
            SELECT
                COUNT(*) AS total_steps,

                SUM(
                    CASE
                        WHEN status = 'completed'
                        THEN 1
                        ELSE 0
                    END
                ) AS completed_steps,

                SUM(
                    CASE
                        WHEN status IN ('pending', 'active')
                        THEN 1
                        ELSE 0
                    END
                ) AS remaining_steps

            FROM workflow_steps

            WHERE service_case_id = ?
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([
            $caseId
        ]);


        $data =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        $total =
            (int)
            (
                $data['total_steps']
                ?? 0
            );


        $completed =
            (int)
            (
                $data['completed_steps']
                ?? 0
            );


        $remaining =
            (int)
            (
                $data['remaining_steps']
                ?? 0
            );


        /*
        -------------------------------------------------
        Zero-step workflow automatically fail karega.

        Service case without workflow ko complete karna
        dangerous hai.
        -------------------------------------------------
        */

        if ($total === 0) {

            return [

                'passed' => false,

                'message' =>
                    'No workflow steps exist for this case.',

                'total_steps' =>
                    0,

                'completed_steps' =>
                    0,

                'remaining_steps' =>
                    0

            ];
        }


        /*
        -------------------------------------------------
        Agar remaining steps zero hain to workflow complete.
        -------------------------------------------------
        */

        $passed =
            $remaining === 0;


        return [

            'passed' =>
                $passed,

            'message' =>
                $passed
                    ? 'All workflow steps are completed.'
                    : 'Some workflow steps are still incomplete.',

            'total_steps' =>
                $total,

            'completed_steps' =>
                $completed,

            'remaining_steps' =>
                $remaining

        ];
    }


    /*
    =====================================================
    CHECK REQUIRED DOCUMENTS
    =====================================================

    Required client documents verify karta hai.

    IMPORTANT:

    Documents ko delete karne ke bajaye document system
    mein status maintain kiya jayega.

    Is se audit trail preserve rahega.
    */

    private function checkRequiredDocuments(
        int $caseId
    ): array {

        /*
        -------------------------------------------------
        Required documents mein se incomplete documents
        count kar rahe hain.

        NOTE:

        document_requests table hamare final database
        architecture mein define hogi.
        -------------------------------------------------
        */

        $sql = "
            SELECT
                COUNT(*) AS required_documents,

                SUM(
                    CASE
                        WHEN status = 'received'
                        THEN 1
                        ELSE 0
                    END
                ) AS received_documents

            FROM document_requests

            WHERE service_case_id = ?

              AND required = 1
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([
            $caseId
        ]);


        $data =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        $required =
            (int)
            (
                $data['required_documents']
                ?? 0
            );


        $received =
            (int)
            (
                $data['received_documents']
                ?? 0
            );


        $missing =
            max(
                $required - $received,
                0
            );


        /*
        -------------------------------------------------
        Agar koi required document request hi nahi bani
        to hum usay automatically failure nahi bana rahe.

        Kyun?

        Har service ko documents ki zaroorat nahi hoti.

        Service template decide karega ke documents
        required hain ya nahi.

        Ye logic next service rules engine mein
        aur strong hoga.
        -------------------------------------------------
        */

        if ($required === 0) {

            return [

                'passed' => true,

                'message' =>
                    'No required documents are pending.',

                'required_documents' =>
                    0,

                'received_documents' =>
                    0,

                'missing_documents' =>
                    0

            ];
        }


        return [

            'passed' =>
                $missing === 0,

            'message' =>
                $missing === 0
                    ? 'All required documents are received.'
                    : 'Some required documents are still missing.',

            'required_documents' =>
                $required,

            'received_documents' =>
                $received,

            'missing_documents' =>
                $missing

        ];
    }


    /*
    =====================================================
    CHECK PROFESSIONAL REVIEW
    =====================================================

    Tax aur immigration cases ke liye professional review
    mandatory ho sakta hai.

    IMPORTANT:

    CRM khud professional review "pass" nahi karega.

    Authorized human reviewer ko database mein review
    record create karna hoga.
    */

    private function checkProfessionalReview(
        int $caseId,
        array $case
    ): array {

        /*
        -------------------------------------------------
        Tax aur immigration services identify karna.

        strtolower() = PHP built-in function.

        Purpose:
        Service key ke capitalization differences handle
        karna.
        -------------------------------------------------
        */

        $serviceKey =
            strtolower(
                (string)
                $case['service_key']
            );


        /*
        -------------------------------------------------
        Sensitive professional-service categories.

        Future mein exact service configuration database
        se aayegi.
        -------------------------------------------------
        */

        $requiresReview =
            str_contains(
                $serviceKey,
                'tax'
            )
            ||
            str_contains(
                $serviceKey,
                'immigration'
            );


        /*
        -------------------------------------------------
        Generic services ke liye professional review
        currently mandatory nahi.
        -------------------------------------------------
        */

        if (!$requiresReview) {

            return [

                'passed' => true,

                'required' => false,

                'message' =>
                    'Professional review is not required by the current rule.'

            ];
        }


        /*
        -------------------------------------------------
        Professional review record search.
        -------------------------------------------------
        */

        $sql = "
            SELECT
                id,
                status,
                reviewer_id,
                reviewed_at

            FROM service_reviews

            WHERE service_case_id = ?

              AND review_type = 'professional'

              AND status = 'approved'

            ORDER BY reviewed_at DESC

            LIMIT 1
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([
            $caseId
        ]);


        $review =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$review) {

            return [

                'passed' => false,

                'required' => true,

                'message' =>
                    'Professional review approval is still required.'

            ];
        }


        /*
        -------------------------------------------------
        Approved professional review found.
        -------------------------------------------------
        */

        return [

            'passed' => true,

            'required' => true,

            'message' =>
                'Professional review has been approved.',

            'reviewer_id' =>
                (int)
                $review['reviewer_id'],

            'reviewed_at' =>
                $review['reviewed_at']

        ];
    }


    /*
    =====================================================
    CHECK CLIENT APPROVAL
    =====================================================

    Kuch services mein client approval required hoga.

    Example:

    Client ko final tax information review karni ho.

    CRM approval record ke baghair completion allow
    nahi karega.
    */

    private function checkClientApproval(
        int $caseId,
        array $case
    ): array {

        /*
        -------------------------------------------------
        Service-specific requirement future template
        engine se aayegi.

        Filhal tax/immigration cases ke liye client
        approval ko enabled nahi kar rahe jab tak service
        configuration explicitly require na kare.
        -------------------------------------------------
        */

        $requiresApproval =
            false;


        if (!$requiresApproval) {

            return [

                'passed' => true,

                'required' => false,

                'message' =>
                    'Client approval is not required by the current rule.'

            ];
        }


        /*
        -------------------------------------------------
        Approval record check.
        -------------------------------------------------
        */

        $sql = "
            SELECT
                id,
                approved_by_client,
                approved_at

            FROM client_approvals

            WHERE service_case_id = ?

              AND approval_type = 'completion'

              AND approved_by_client = 1

            ORDER BY approved_at DESC

            LIMIT 1
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([
            $caseId
        ]);


        $approval =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$approval) {

            return [

                'passed' => false,

                'required' => true,

                'message' =>
                    'Client approval is still required.'

            ];
        }


        return [

            'passed' => true,

            'required' => true,

            'message' =>
                'Client approval has been recorded.',

            'approved_at' =>
                $approval['approved_at']

        ];
    }


    /*
    =====================================================
    CHECK FILING / SUBMISSION
    =====================================================

    Ye check verify karta hai ke service ka required
    official action record hua ya nahi.

    IMPORTANT:

    CRM khud filing ko professional judgment ke baghair
    execute nahi karega.

    Hum sirf record verify karenge.
    =====================================================
    */

    private function checkFilingRecord(
        int $caseId,
        array $case
    ): array {

        $serviceKey =
            strtolower(
                (string)
                $case['service_key']
            );


        /*
        -------------------------------------------------
        Tax / immigration services ko official filing
        record ki zaroorat ho sakti hai.

        -------------------------------------------------
        */

        $requiresFiling =
            str_contains(
                $serviceKey,
                'tax'
            )
            ||
            str_contains(
                $serviceKey,
                'immigration'
            );


        /*
        -------------------------------------------------
        Generic services ke liye filing record required
        nahi.
        -------------------------------------------------
        */

        if (!$requiresFiling) {

            return [

                'passed' => true,

                'required' => false,

                'message' =>
                    'Filing or submission record is not required.'

            ];
        }


        /*
        -------------------------------------------------
        Filing record verify karein.

        filing_records table future central filing
        module ka part hogi.
        -------------------------------------------------
        */

        $sql = "
            SELECT
                id,
                status,
                confirmation_number,
                submitted_at

            FROM filing_records

            WHERE service_case_id = ?

              AND status IN (
                  'submitted',
                  'accepted',
                  'completed'
              )

            ORDER BY submitted_at DESC

            LIMIT 1
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([
            $caseId
        ]);


        $filing =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$filing) {

            return [

                'passed' => false,

                'required' => true,

                'message' =>
                    'Required filing/submission record was not found.'

            ];
        }


        /*
        -------------------------------------------------
        Filing record found.

        Confirmation number ko sirf internal result
        mein return kar rahe hain.

        UI layer ko sensitive information expose karne
        se pehle permission check karna hoga.
        -------------------------------------------------
        */

        return [

            'passed' => true,

            'required' => true,

            'message' =>
                'Required filing/submission record exists.',

            'filing_status' =>
                $filing['status'],

            'submitted_at' =>
                $filing['submitted_at']

        ];
    }


    /*
    =====================================================
    BLOCKED RESULT HELPER
    =====================================================

    Repeated error response ko ek central method mein
    rakha gaya hai.

    Is se response format consistent rahega.
    */

    private function blockedResult(
        string $message
    ): array {

        return [

            'allowed' => false,

            'message' =>
                $message,

            'checks' =>
                [],

            'failed_checks' =>
                []

        ];
    }
}