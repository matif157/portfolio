```php
<?php
/**
 * =====================================================
 * FILE: core/services/ServiceCaseManager.php
 *
 * PURPOSE:
 * SPS CRM ke actual service cases ko manage karna.
 *
 * Ye class client ke running service case ka:
 *
 * - status
 * - assigned staff
 * - current workflow step
 * - progress
 * - pause/resume
 * - cancellation
 * - completion
 * - reopening
 *
 * manage karegi.
 *
 * WORKFLOW:
 *
 * Service Case
 *      ↓
 * Start
 *      ↓
 * Active
 *      ↓
 * Workflow Steps
 *      ↓
 * Pause / Resume / Reopen
 *      ↓
 * Complete
 *
 * IMPORTANT:
 *
 * Ye class professional tax ya immigration decision
 * nahi karti.
 *
 * Ye sirf administrative case lifecycle manage karti hai.
 *
 * PROFESSIONAL CONTROL:
 *
 * Tax:
 * - professional review
 * - tax judgment
 * - final approval
 *
 * Immigration:
 * - professional assessment
 * - legal/professional review
 * - authorized submission
 *
 * in sab ko authorized human user control karega.
 *
 * SECURITY:
 *
 * - Prepared statements
 * - Transaction handling
 * - Valid status transitions
 * - Invalid case IDs reject
 * - Arbitrary status changes prevent
 *
 * =====================================================
 */


/*
=========================================================
CLASS DEFINITION
=========================================================

Ye custom PHP class hai.

ServiceCaseManager ko use karne wala koi bhi CRM module
centralized case lifecycle logic use kar sakta hai.
=========================================================
*/

class ServiceCaseManager
{

    /*
    =====================================================
    DATABASE CONNECTION
    =====================================================

    PDO = PHP built-in database interface.

    Is property mein database connection store hoga.
    */

    private PDO $pdo;


    /*
    =====================================================
    ALLOWED STATUS VALUES
    =====================================================

    Ye controlled statuses hain.

    User directly database mein koi bhi random status
    inject nahi kar sakta through this manager.

    Example invalid:

        completeddddd
        hacked
        anything

    Sirf defined lifecycle statuses allow honge.
    */

    private array $allowedStatuses = [

        'open',
        'active',
        'paused',
        'completed',
        'cancelled'

    ];


    /*
    =====================================================
    CONSTRUCTOR
    =====================================================

    PDO connection receive karta hai.
    */

    public function __construct(
        PDO $pdo
    ) {

        $this->pdo = $pdo;
    }


    /*
    =====================================================
    GET CASE
    =====================================================

    Kisi specific service case ki complete basic
    information retrieve karta hai.
    */

    public function getCase(
        int $caseId
    ): ?array {

        /*
        -------------------------------------------------
        Invalid ID reject.
        -------------------------------------------------
        */

        if ($caseId <= 0) {
            return null;
        }


        /*
        -------------------------------------------------
        Prepared SELECT query.

        Client information aur service case information
        ek saath retrieve ki ja rahi hai.
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
                sc.updated_at,
                c.full_name AS client_name

            FROM service_cases sc

            LEFT JOIN clients c
                ON c.id = sc.client_id

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


        /*
        -------------------------------------------------
        fetch() = PDO method.

        Ek matching database row return karta hai.
        -------------------------------------------------
        */

        $case =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$case) {
            return null;
        }


        return $case;
    }


    /*
    =====================================================
    START CASE
    =====================================================

    New/open case ko active karta hai.

    Ye operation tab use hoga jab service officially
    processing mein enter kare.
    */

    public function startCase(
        int $caseId
    ): array {

        return $this->changeStatus(
            $caseId,
            'active',
            [
                'open'
            ]
        );
    }


    /*
    =====================================================
    PAUSE CASE
    =====================================================

    Case temporarily pause karta hai.

    Example:

    Client ne required documents provide nahi kiye.

    Case ko cancel karne ki zaroorat nahi.
    Sirf pause kiya ja sakta hai.
    */

    public function pauseCase(
        int $caseId
    ): array {

        return $this->changeStatus(
            $caseId,
            'paused',
            [
                'active'
            ]
        );
    }


    /*
    =====================================================
    RESUME CASE
    =====================================================

    Paused case ko dobara active karta hai.
    */

    public function resumeCase(
        int $caseId
    ): array {

        return $this->changeStatus(
            $caseId,
            'active',
            [
                'paused'
            ]
        );
    }


    /*
    =====================================================
    CANCEL CASE
    =====================================================

    Active/open/paused case cancel ho sakta hai.

    IMPORTANT:

    Completed case ko normally cancel nahi karna.

    Completed record audit/history ke liye preserve
    rehna chahiye.
    */

    public function cancelCase(
        int $caseId
    ): array {

        return $this->changeStatus(
            $caseId,
            'cancelled',
            [
                'open',
                'active',
                'paused'
            ]
        );
    }


    /*
    =====================================================
    COMPLETE CASE
    =====================================================

    Service ko completed mark karta hai.

    IMPORTANT:

    Is method ko blindly call nahi karna chahiye.

    Future version mein hum verify karenge:

    - required steps complete
    - required documents handled
    - professional approval complete
    - client approval complete
    - filing/official action recorded

    Abhi basic lifecycle implementation hai.
    */

    public function completeCase(
        int $caseId
    ): array {

        /*
        -------------------------------------------------
        Case existence check.
        -------------------------------------------------
        */

        $case =
            $this->getCase(
                $caseId
            );


        if (!$case) {

            return [

                'success' => false,

                'message' =>
                    'Service case was not found.'

            ];
        }


        /*
        -------------------------------------------------
        Cancelled case ko complete nahi karna.
        -------------------------------------------------
        */

        if (
            $case['status'] ===
            'cancelled'
        ) {

            return [

                'success' => false,

                'message' =>
                    'Cancelled case cannot be completed.'

            ];
        }


        /*
        -------------------------------------------------
        Future professional approval checks yahan
        integrate honge.

        Abhi basic status transition perform karte hain.
        -------------------------------------------------
        */

        return $this->changeStatus(
            $caseId,
            'completed',
            [
                'active'
            ]
        );
    }


    /*
    =====================================================
    REOPEN CASE
    =====================================================

    Completed ya cancelled case ko authorized workflow
    ke through reopen karna possible hoga.

    Ye normal staff action nahi hona chahiye.

    Future permission layer is method ko restrict karegi.
    */

    public function reopenCase(
        int $caseId
    ): array {

        /*
        -------------------------------------------------
        Case load.
        -------------------------------------------------
        */

        $case =
            $this->getCase(
                $caseId
            );


        if (!$case) {

            return [

                'success' => false,

                'message' =>
                    'Service case was not found.'

            ];
        }


        /*
        -------------------------------------------------
        Sirf completed/cancelled case reopen allowed.
        -------------------------------------------------
        */

        if (
            !in_array(
                $case['status'],
                [
                    'completed',
                    'cancelled'
                ],
                true
            )
        ) {

            return [

                'success' => false,

                'message' =>
                    'Only completed or cancelled cases can be reopened.'

            ];
        }


        /*
        -------------------------------------------------
        Case ko active state mein wapas le aayenge.

        Current workflow step preserve rahega.
        -------------------------------------------------
        */

        return $this->changeStatus(
            $caseId,
            'active',
            [
                'completed',
                'cancelled'
            ]
        );
    }


    /*
    =====================================================
    ASSIGN CASE
    =====================================================

    Case kisi staff member ko assign karta hai.

    IMPORTANT:

    Future Role & Permission Manager verify karega ke
    selected user ko ye service handle karne ki permission
    hai ya nahi.
    */

    public function assignCase(
        int $caseId,
        int $staffId
    ): array {

        if (
            $caseId <= 0 ||
            $staffId <= 0
        ) {

            return [

                'success' => false,

                'message' =>
                    'Valid case and staff IDs are required.'

            ];
        }


        /*
        -------------------------------------------------
        Case existence.
        -------------------------------------------------
        */

        if (
            !$this->getCase($caseId)
        ) {

            return [

                'success' => false,

                'message' =>
                    'Service case was not found.'

            ];
        }


        /*
        -------------------------------------------------
        Staff user existence check.

        Hum blindly kisi numeric ID ko assignment nahi
        kar rahe.
        -------------------------------------------------
        */

        $staffSql = "
            SELECT id
            FROM users
            WHERE id = ?
            LIMIT 1
        ";


        $staffStmt =
            $this->pdo->prepare(
                $staffSql
            );


        $staffStmt->execute([
            $staffId
        ]);


        if (
            !$staffStmt->fetch(
                PDO::FETCH_ASSOC
            )
        ) {

            return [

                'success' => false,

                'message' =>
                    'Assigned staff user was not found.'

            ];
        }


        /*
        -------------------------------------------------
        Assignment update.

        Prepared statement use kiya gaya hai.
        -------------------------------------------------
        */

        $sql = "
            UPDATE service_cases

            SET
                assigned_to = ?,
                updated_at = NOW()

            WHERE id = ?
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([

            $staffId,

            $caseId

        ]);


        return [

            'success' => true,

            'message' =>
                'Service case assigned successfully.',

            'case_id' =>
                $caseId,

            'assigned_to' =>
                $staffId

        ];
    }


    /*
    =====================================================
    GET CURRENT WORKFLOW STEP
    =====================================================

    Case ka currently active step return karta hai.
    */

    public function getCurrentStep(
        int $caseId
    ): ?array {

        if ($caseId <= 0) {
            return null;
        }


        $sql = "
            SELECT
                id,
                service_case_id,
                step_key,
                step_name,
                step_order,
                actor_type,
                status,
                required,
                started_at,
                completed_at

            FROM workflow_steps

            WHERE service_case_id = ?

              AND status = 'active'

            ORDER BY step_order ASC

            LIMIT 1
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([
            $caseId
        ]);


        $step =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        return $step ?: null;
    }


    /*
    =====================================================
    GET PROGRESS
    =====================================================

    Workflow progress percentage calculate karta hai.

    Example:

        3 completed
        8 total

        = 37.5%

    UI isko progress bar mein show kar sakti hai.
    */

    public function getProgress(
        int $caseId
    ): array {

        if ($caseId <= 0) {

            return [

                'total' => 0,

                'completed' => 0,

                'percentage' => 0

            ];
        }


        /*
        -------------------------------------------------
        Total workflow steps.
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
                ) AS completed_steps

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


        /*
        -------------------------------------------------
        Division by zero avoid karna.

        Agar workflow steps 0 hain to progress = 0.
        -------------------------------------------------
        */

        $percentage =
            $total > 0
            ? round(
                (
                    $completed /
                    $total
                ) * 100,
                2
            )
            : 0;


        return [

            'total' =>
                $total,

            'completed' =>
                $completed,

            'remaining' =>
                max(
                    $total - $completed,
                    0
                ),

            'percentage' =>
                $percentage

        ];
    }


    /*
    =====================================================
    COMPLETE CURRENT STEP
    =====================================================

    Current active workflow step ko complete karta hai
    aur next pending step ko activate karta hai.

    Ye workflow automation ka bohat important bridge hai.
    */

    public function completeCurrentStep(
        int $caseId
    ): array {

        /*
        -------------------------------------------------
        Current active step load.
        -------------------------------------------------
        */

        $currentStep =
            $this->getCurrentStep(
                $caseId
            );


        if (!$currentStep) {

            return [

                'success' => false,

                'message' =>
                    'No active workflow step was found.'

            ];
        }


        /*
        -------------------------------------------------
        Transaction start.

        Current step complete aur next step active
        dono operations ek transaction mein honge.

        Agar second operation fail ho gaya to first
        operation bhi rollback ho jayega.
        -------------------------------------------------
        */

        try {

            $this->pdo->beginTransaction();


            /*
            -------------------------------------------------
            Current step complete.
            -------------------------------------------------
            */

            $completeSql = "
                UPDATE workflow_steps

                SET
                    status = 'completed',
                    completed_at = NOW(),
                    updated_at = NOW()

                WHERE id = ?

                  AND status = 'active'
            ";


            $completeStmt =
                $this->pdo->prepare(
                    $completeSql
                );


            $completeStmt->execute([

                (int)
                $currentStep['id']

            ]);


            /*
            -------------------------------------------------
            Next pending step find karein.
            -------------------------------------------------
            */

            $nextSql = "
                SELECT
                    id,
                    step_key,
                    step_name,
                    step_order

                FROM workflow_steps

                WHERE service_case_id = ?

                  AND status = 'pending'

                ORDER BY step_order ASC

                LIMIT 1
            ";


            $nextStmt =
                $this->pdo->prepare(
                    $nextSql
                );


            $nextStmt->execute([
                $caseId
            ]);


            $nextStep =
                $nextStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            /*
            -------------------------------------------------
            Agar next step available hai to activate.
            -------------------------------------------------
            */

            if ($nextStep) {

                $activateSql = "
                    UPDATE workflow_steps

                    SET
                        status = 'active',
                        started_at = NOW(),
                        updated_at = NOW()

                    WHERE id = ?

                      AND status = 'pending'
                ";


                $activateStmt =
                    $this->pdo->prepare(
                        $activateSql
                    );


                $activateStmt->execute([

                    (int)
                    $nextStep['id']

                ]);


                /*
                -------------------------------------------------
                Service case ka current_step update.

                Step order save karna UI/dashboard ke liye
                useful hoga.
                -------------------------------------------------
                */

                $caseSql = "
                    UPDATE service_cases

                    SET
                        current_step = ?,
                        updated_at = NOW()

                    WHERE id = ?
                ";


                $caseStmt =
                    $this->pdo->prepare(
                        $caseSql
                    );


                $caseStmt->execute([

                    (int)
                    $nextStep['step_order'],

                    $caseId

                ]);

            } else {

                /*
                -------------------------------------------------
                Koi next step nahi.

                Iska matlab workflow ke saare steps
                complete ho gaye.

                Case ko automatically completed mark
                karna abhi intentionally avoid kar rahe hain.

                Kyun?

                Tax/immigration mein final completion se
                pehle professional/client approval rules
                verify karne hain.

                Ye future validation engine karega.
                -------------------------------------------------
                */

                $this->pdo->commit();


                return [

                    'success' => true,

                    'message' =>
                        'Final workflow step completed. Case requires completion validation.',

                    'case_id' =>
                        $caseId,

                    'workflow_complete' =>
                        true,

                    'next_step' =>
                        null

                ];
            }


            /*
            -------------------------------------------------
            All changes successful.
            -------------------------------------------------
            */

            $this->pdo->commit();


            return [

                'success' => true,

                'message' =>
                    'Current workflow step completed.',

                'case_id' =>
                    $caseId,

                'completed_step' =>
                    $currentStep['step_name'],

                'next_step' =>
                    $nextStep['step_name'] ?? null

            ];

        } catch (
            Throwable $exception
        ) {

            /*
            -------------------------------------------------
            Failure par transaction rollback.
            -------------------------------------------------
            */

            if (
                $this->pdo->inTransaction()
            ) {

                $this->pdo->rollBack();
            }


            return [

                'success' => false,

                'message' =>
                    'Unable to complete workflow step.',

                'error_code' =>
                    'WORKFLOW_STEP_UPDATE_FAILED'

            ];
        }
    }


    /*
    =====================================================
    CHANGE STATUS
    =====================================================

    Central status transition method.

    Har status change isi method se guzrega.

    Is se system mein random status manipulation
    reduce hoti hai.
    */

    private function changeStatus(
        int $caseId,
        string $newStatus,
        array $allowedCurrentStatuses
    ): array {

        /*
        -------------------------------------------------
        Basic validation.
        -------------------------------------------------
        */

        if ($caseId <= 0) {

            return [

                'success' => false,

                'message' =>
                    'Invalid service case ID.'

            ];
        }


        if (
            !in_array(
                $newStatus,
                $this->allowedStatuses,
                true
            )
        ) {

            return [

                'success' => false,

                'message' =>
                    'Invalid service case status.'

            ];
        }


        /*
        -------------------------------------------------
        Current case load.
        -------------------------------------------------
        */

        $case =
            $this->getCase(
                $caseId
            );


        if (!$case) {

            return [

                'success' => false,

                'message' =>
                    'Service case was not found.'

            ];
        }


        /*
        -------------------------------------------------
        Verify current status.

        Example:

        completed → paused

        allowed nahi.

        -------------------------------------------------
        */

        if (
            !in_array(
                $case['status'],
                $allowedCurrentStatuses,
                true
            )
        ) {

            return [

                'success' => false,

                'message' =>
                    'This status transition is not allowed.'

            ];
        }


        /*
        -------------------------------------------------
        Database update.
        -------------------------------------------------
        */

        $sql = "
            UPDATE service_cases

            SET
                status = ?,
                updated_at = NOW()

            WHERE id = ?

              AND status = ?
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([

            $newStatus,

            $caseId,

            $case['status']

        ]);


        /*
        -------------------------------------------------
        rowCount() = PDO statement method.

        Ye batata hai kitni rows actually update hui.
        -------------------------------------------------
        */

        if (
            $stmt->rowCount() !== 1
        ) {

            return [

                'success' => false,

                'message' =>
                    'Case status was not changed.'

            ];
        }


        return [

            'success' => true,

            'message' =>
                'Service case status updated.',

            'case_id' =>
                $caseId,

            'old_status' =>
                $case['status'],

            'new_status' =>
                $newStatus

        ];
    }
}
```
