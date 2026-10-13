```php
<?php
/**
 * =====================================================
 * FILE: core/services/ServiceWorkflowManager.php
 *
 * PURPOSE:
 * SPS CRM ke service workflows ko control karna.
 *
 * Ye file decide karti hai:
 *
 * - Service ka current workflow step kya hai
 * - Next step kya hai
 * - Step complete ho sakta hai ya nahi
 * - Required documents complete hain ya nahi
 * - Required tasks complete hain ya nahi
 * - Case ko next step par move karna allowed hai ya nahi
 * - Workflow kis wajah se blocked hai
 *
 *
 * WORKFLOW:
 *
 * SERVICE CASE
 *      ↓
 * CURRENT STEP
 *      ↓
 * CHECK REQUIREMENTS
 *      ↓
 * ┌───────────────────┐
 * │ Requirements OK?  │
 * └─────────┬─────────┘
 *           │
 *       YES │ NO
 *           │  \
 *           ▼   └──→ BLOCKED
 *      COMPLETE
 *           ↓
 *       NEXT STEP
 *           ↓
 *      AUTOMATION
 *
 *
 * IMPORTANT:
 *
 * Workflow automatically professional/legal judgment
 * nahi karega.
 *
 * CRM sirf configured business workflow enforce karega.
 *
 * Example:
 *
 * "Document uploaded"
 * ≠
 * "Document approved"
 *
 * Aur:
 *
 * "All documents approved"
 * ≠
 * "Tax return professionally reviewed"
 *
 * Human review ke required steps explicitly workflow
 * mein rakhe ja sakte hain.
 *
 * =====================================================
 */


/**
 * =====================================================
 * CLASS: ServiceWorkflowManager
 *
 * PURPOSE:
 * Service case ke workflow steps aur transitions
 * manage karna.
 * =====================================================
 */

class ServiceWorkflowManager
{

    /*
    =====================================================
    DATABASE CONNECTION
    =====================================================
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
    GET WORKFLOW
    =====================================================

    Kisi service case ka complete workflow load karta hai.

    UI is information ko timeline/progress interface
    mein use kar sakti hai.
    */

    public function getWorkflow(
        int $caseId
    ): array {

        if ($caseId <= 0) {

            return [
                'success' => false,
                'message' => 'Invalid service case ID.'
            ];
        }


        /*
        -------------------------------------------------
        Case check.
        -------------------------------------------------
        */

        $case =
            $this->getCase(
                $caseId
            );


        if (!$case) {

            return [
                'success' => false,
                'message' => 'Service case was not found.'
            ];
        }


        /*
        -------------------------------------------------
        Workflow steps load.

        step_order workflow ka sequence maintain karta hai.
        -------------------------------------------------
        */

        $sql = "
            SELECT

                id,
                service_case_id,
                step_key,
                step_name,
                description,
                step_order,
                status,
                required,
                started_at,
                completed_at,
                completed_by,
                blocked_reason,
                created_at,
                updated_at

            FROM service_workflow_steps

            WHERE service_case_id = ?

            ORDER BY
                step_order ASC,
                id ASC
        ";


        /*
        prepare() = PDO method.
        SQL query ko prepared statement banata hai.
        */

        $stmt =
            $this->pdo->prepare(
                $sql
            );


        /*
        execute() = PDO method.
        Case ID safely query mein pass karta hai.
        */

        $stmt->execute([
            $caseId
        ]);


        $steps =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        return [

            'success' => true,

            'case' =>
                $case,

            'steps' =>
                $steps,

            'current_step' =>
                $case['current_step']

        ];
    }


    /*
    =====================================================
    CREATE WORKFLOW FROM TEMPLATE
    =====================================================

    Jab service case create ho to us service ke template
    se actual workflow steps generate kiye ja sakte hain.

    Example:

        TAX_RETURN
             ↓
        Tax Workflow Template
             ↓
        Step 1 Client Intake
        Step 2 Documents
        Step 3 Preparation
        Step 4 Review
        Step 5 Filing
        Step 6 Completion

    Ye method template ko actual case ke steps mein
    convert karta hai.
    */

    public function createWorkflow(
        int $caseId,
        string $serviceKey
    ): array {

        if ($caseId <= 0) {

            return [
                'success' => false,
                'message' => 'Invalid service case ID.'
            ];
        }


        $serviceKey =
            trim(
                strtolower(
                    $serviceKey
                )
            );


        if ($serviceKey === '') {

            return [
                'success' => false,
                'message' => 'Service key is required.'
            ];
        }


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
                'message' => 'Service case was not found.'
            ];
        }


        /*
        =================================================
        DUPLICATE WORKFLOW PROTECTION
        =================================================

        Agar workflow pehle create ho chuka hai to
        dobara create nahi karna.

        Ye bohat important hai warna case ke andar
        duplicate steps ban jayenge.
        */

        $existingSql = "
            SELECT id

            FROM service_workflow_steps

            WHERE service_case_id = ?

            LIMIT 1
        ";


        $existingStmt =
            $this->pdo->prepare(
                $existingSql
            );


        $existingStmt->execute([
            $caseId
        ]);


        if (
            $existingStmt->fetch(
                PDO::FETCH_ASSOC
            )
        ) {

            return [
                'success' => false,
                'message' => 'Workflow already exists for this service case.'
            ];
        }


        /*
        =================================================
        LOAD WORKFLOW TEMPLATE
        =================================================

        Template service configuration se aayega.

        Is architecture mein actual service definitions
        database-driven rakhi ja rahi hain.

        Iska faida:
        Nayi service add karne ke liye har baar PHP file
        modify karna zaroori nahi hoga.
        */

        $templateSql = "
            SELECT

                id,
                service_key,
                step_key,
                step_name,
                description,
                step_order,
                required

            FROM service_workflow_templates

            WHERE service_key = ?

              AND active = 1

            ORDER BY
                step_order ASC,
                id ASC
        ";


        $templateStmt =
            $this->pdo->prepare(
                $templateSql
            );


        $templateStmt->execute([
            $serviceKey
        ]);


        $templates =
            $templateStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        if (
            empty($templates)
        ) {

            return [
                'success' => false,
                'message' => 'No active workflow template exists for this service.'
            ];
        }


        /*
        =================================================
        TRANSACTION START
        =================================================

        Transaction = database ke multiple operations
        ko ek safe group mein execute karna.

        Agar beech mein error ho to rollback karke
        incomplete workflow database mein nahi chhorenge.
        */

        $this->pdo->beginTransaction();


        try {

            /*
            -------------------------------------------------
            Har template step ko actual case step mein
            convert kar rahe hain.
            -------------------------------------------------
            */

            foreach (
                $templates as $index => $template
            ) {

                /*
                ---------------------------------------------
                First step ko "current" banaya jayega.

                Baaki steps "pending" rahenge.
                ---------------------------------------------
                */

                $status =
                    $index === 0
                        ? 'current'
                        : 'pending';


                $insertSql = "
                    INSERT INTO service_workflow_steps
                    (
                        service_case_id,
                        step_key,
                        step_name,
                        description,
                        step_order,
                        status,
                        required,
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
                        ?,
                        ?,
                        NOW(),
                        NOW()
                    )
                ";


                $insertStmt =
                    $this->pdo->prepare(
                        $insertSql
                    );


                $insertStmt->execute([

                    $caseId,

                    $template['step_key'],

                    $template['step_name'],

                    $template['description']
                        ?? null,

                    (int)
                    $template['step_order'],

                    $status,

                    (int)
                    $template['required']

                ]);
            }


            /*
            -------------------------------------------------
            Case ko first workflow step par set karna.
            -------------------------------------------------
            */

            $firstStep =
                $templates[0];


            $updateCaseSql = "
                UPDATE service_cases

                SET
                    current_step = ?,
                    status = 'in_progress',
                    updated_at = NOW()

                WHERE id = ?
            ";


            $updateCaseStmt =
                $this->pdo->prepare(
                    $updateCaseSql
                );


            $updateCaseStmt->execute([

                $firstStep['step_key'],

                $caseId

            ]);


            /*
            -------------------------------------------------
            Sab kuch successful hai.

            commit() = PDO transaction method.
            Database changes permanently save karta hai.
            -------------------------------------------------
            */

            $this->pdo->commit();


            return [

                'success' => true,

                'message' =>
                    'Service workflow created successfully.',

                'case_id' =>
                    $caseId,

                'steps_created' =>
                    count($templates),

                'current_step' =>
                    $firstStep['step_key']

            ];

        } catch (
            Throwable $e
        ) {

            /*
            -------------------------------------------------
            rollback() = PDO transaction method.

            Agar koi error aaye to workflow ka incomplete
            data remove/revert kar deta hai.
            -------------------------------------------------
            */

            $this->pdo->rollBack();


            /*
            Production mein actual exception ko user ko
            directly show nahi karna.

            Detailed error secure server log mein jaana
            chahiye. UI ko simple message milega.
            */

            error_log(
                'Workflow creation failed: ' .
                $e->getMessage()
            );


            return [

                'success' => false,

                'message' =>
                    'Workflow could not be created.'

            ];
        }
    }


    /*
    =====================================================
    GET CURRENT STEP
    =====================================================

    Case ka currently active workflow step return karta hai.
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
                description,
                step_order,
                status,
                required,
                started_at,
                completed_at,
                completed_by,
                blocked_reason

            FROM service_workflow_steps

            WHERE service_case_id = ?

              AND status = 'current'

            ORDER BY
                step_order ASC

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


        return
            $step ?: null;
    }


    /*
    =====================================================
    COMPLETE CURRENT STEP
    =====================================================

    Current step ko complete karne se pehle CRM
    requirements check karega.

    Ye sabse important workflow safety points mein se
    ek hai.

    Example:

        Documents Step

        Required documents:
            Passport     Approved
            W2           Approved
            ID           Rejected

        Result:
            STEP CANNOT COMPLETE

    Matlab staff accidentally next stage par jump
    nahi kar sakta.
    */

    public function completeCurrentStep(
        int $caseId,
        int $userId,
        ?string $notes = null
    ): array {

        if (
            $caseId <= 0 ||
            $userId <= 0
        ) {

            return [
                'success' => false,
                'message' => 'Valid case and user IDs are required.'
            ];
        }


        /*
        -------------------------------------------------
        Current step retrieve.
        -------------------------------------------------
        */

        $currentStep =
            $this->getCurrentStep(
                $caseId
            );


        if (!$currentStep) {

            return [
                'success' => false,
                'message' => 'No current workflow step was found.'
            ];
        }


        /*
        =================================================
        REQUIREMENT CHECK
        =================================================

        Step complete hone se pehle required conditions
        verify karenge.
        */

        $requirements =
            $this->checkStepRequirements(
                $caseId,
                $currentStep
            );


        if (
            !$requirements['can_complete']
        ) {

            /*
            ---------------------------------------------
            Step ko blocked mark kar sakte hain.

            Lekin current workflow ko silently advance
            nahi karenge.
            ---------------------------------------------
            */

            $this->markStepBlocked(
                $currentStep['id'],
                $requirements['reason']
            );


            return [

                'success' => false,

                'message' =>
                    'Workflow step cannot be completed yet.',

                'blocked' =>
                    true,

                'reason' =>
                    $requirements['reason'],

                'missing' =>
                    $requirements['missing']

            ];
        }


        /*
        =================================================
        COMPLETE CURRENT STEP
        =================================================
        */

        $sql = "
            UPDATE service_workflow_steps

            SET
                status = 'completed',
                completed_at = NOW(),
                completed_by = ?,
                completion_notes = ?,
                updated_at = NOW()

            WHERE id = ?

              AND status = 'current'
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([

            $userId,

            $notes,

            (int)
            $currentStep['id']

        ]);


        /*
        =================================================
        FIND NEXT STEP
        =================================================
        */

        $nextStep =
            $this->getNextStep(
                $caseId,
                (int)
                $currentStep['step_order']
            );


        /*
        -------------------------------------------------
        Agar next step nahi hai to complete workflow.
        -------------------------------------------------
        */

        if (!$nextStep) {

            $this->completeCase(
                $caseId
            );


            return [

                'success' => true,

                'message' =>
                    'Final workflow step completed. Service case is complete.',

                'case_completed' =>
                    true,

                'current_step' =>
                    null

            ];
        }


        /*
        =================================================
        MOVE TO NEXT STEP
        =================================================
        */

        $moveResult =
            $this->activateStep(
                $caseId,
                (int)
                $nextStep['id']
            );


        if (
            !$moveResult['success']
        ) {

            return [

                'success' => false,

                'message' =>
                    'Current step completed, but next step could not be activated.'

            ];
        }


        return [

            'success' => true,

            'message' =>
                'Workflow step completed and next step activated.',

            'completed_step' =>
                $currentStep['step_key'],

            'next_step' =>
                $nextStep['step_key'],

            'case_completed' =>
                false

        ];
    }


    /*
    =====================================================
    CHECK STEP REQUIREMENTS
    =====================================================

    Ye method decide karta hai ke current step complete
    karne ke liye required documents/tasks ready hain
    ya nahi.
    */

    private function checkStepRequirements(
        int $caseId,
        array $step
    ): array {

        $missing = [];


        /*
        =================================================
        REQUIRED DOCUMENTS
        =================================================

        Step ke saath linked required documents check
        karte hain.

        Approved ke ilawa koi state complete nahi mani
        jayegi.
        */

        $documentSql = "
            SELECT

                document_name,
                status

            FROM document_requests

            WHERE service_case_id = ?

              AND required = 1

              AND status != 'approved'
        ";


        $documentStmt =
            $this->pdo->prepare(
                $documentSql
            );


        $documentStmt->execute([
            $caseId
        ]);


        $documents =
            $documentStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        foreach (
            $documents as $document
        ) {

            $missing[] = [

                'type' =>
                    'document',

                'name' =>
                    $document['document_name'],

                'status' =>
                    $document['status']

            ];
        }


        /*
        =================================================
        REQUIRED TASKS
        =================================================

        Pending/open tasks ko bhi blocker maana ja sakta hai.

        IMPORTANT:

        Ye generic task check hai.

        Future version mein tasks ko specific workflow
        step ke saath directly link kiya jayega.
        */

        $taskSql = "
            SELECT

                id,
                title,
                status

            FROM tasks

            WHERE service_case_id = ?

              AND status NOT IN (
                  'completed',
                  'cancelled'
              )
        ";


        $taskStmt =
            $this->pdo->prepare(
                $taskSql
            );


        $taskStmt->execute([
            $caseId
        ]);


        $tasks =
            $taskStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        foreach (
            $tasks as $task
        ) {

            $missing[] = [

                'type' =>
                    'task',

                'name' =>
                    $task['title'],

                'status' =>
                    $task['status']

            ];
        }


        /*
        =================================================
        RESULT
        =================================================
        */

        if (
            !empty($missing)
        ) {

            return [

                'can_complete' =>
                    false,

                'reason' =>
                    'Required workflow items are still incomplete.',

                'missing' =>
                    $missing

            ];
        }


        return [

            'can_complete' =>
                true,

            'reason' =>
                null,

            'missing' =>
                []

        ];
    }


    /*
    =====================================================
    GET NEXT STEP
    =====================================================
    */

    private function getNextStep(
        int $caseId,
        int $currentOrder
    ): ?array {

        $sql = "
            SELECT

                id,
                service_case_id,
                step_key,
                step_name,
                description,
                step_order,
                status,
                required

            FROM service_workflow_steps

            WHERE service_case_id = ?

              AND step_order > ?

              AND status != 'completed'

            ORDER BY
                step_order ASC

            LIMIT 1
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([

            $caseId,

            $currentOrder

        ]);


        $step =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        return
            $step ?: null;
    }


    /*
    =====================================================
    ACTIVATE STEP
    =====================================================

    Next workflow step ko current banata hai.
    */

    private function activateStep(
        int $caseId,
        int $stepId
    ): array {

        /*
        -------------------------------------------------
        Transaction use kar rahe hain.

        Pehle existing current step ensure karenge,
        phir new step activate hoga.
        -------------------------------------------------
        */

        $this->pdo->beginTransaction();


        try {

            /*
            ---------------------------------------------
            Existing current state ko pending nahi karna
            kyun ke previous step already completed hai.

            Sirf new step activate karenge.
            ---------------------------------------------
            */

            $updateStepSql = "
                UPDATE service_workflow_steps

                SET
                    status = 'current',
                    started_at = COALESCE(
                        started_at,
                        NOW()
                    ),
                    blocked_reason = NULL,
                    updated_at = NOW()

                WHERE id = ?

                  AND service_case_id = ?

                  AND status = 'pending'
            ";


            $updateStepStmt =
                $this->pdo->prepare(
                    $updateStepSql
                );


            $updateStepStmt->execute([

                $stepId,

                $caseId

            ]);


            if (
                $updateStepStmt->rowCount() === 0
            ) {

                $this->pdo->rollBack();


                return [

                    'success' => false,

                    'message' =>
                        'Next workflow step could not be activated.'

                ];
            }


            /*
            ---------------------------------------------
            Activated step ka key retrieve karte hain.
            ---------------------------------------------
            */

            $stepSql = "
                SELECT step_key

                FROM service_workflow_steps

                WHERE id = ?

                  AND service_case_id = ?

                LIMIT 1
            ";


            $stepStmt =
                $this->pdo->prepare(
                    $stepSql
                );


            $stepStmt->execute([

                $stepId,

                $caseId

            ]);


            $step =
                $stepStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$step) {

                $this->pdo->rollBack();


                return [

                    'success' => false,

                    'message' =>
                        'Activated workflow step could not be loaded.'

                ];
            }


            /*
            ---------------------------------------------
            Service case ka current_step update.
            ---------------------------------------------
            */

            $caseSql = "
                UPDATE service_cases

                SET
                    current_step = ?,
                    status = 'in_progress',
                    updated_at = NOW()

                WHERE id = ?
            ";


            $caseStmt =
                $this->pdo->prepare(
                    $caseSql
                );


            $caseStmt->execute([

                $step['step_key'],

                $caseId

            ]);


            /*
            ---------------------------------------------
            Sab successful.
            ---------------------------------------------
            */

            $this->pdo->commit();


            return [

                'success' => true,

                'step_key' =>
                    $step['step_key']

            ];

        } catch (
            Throwable $e
        ) {

            /*
            ---------------------------------------------
            Database consistency ke liye rollback.
            ---------------------------------------------
            */

            $this->pdo->rollBack();


            error_log(
                'Workflow activation failed: ' .
                $e->getMessage()
            );


            return [

                'success' => false,

                'message' =>
                    'Workflow step activation failed.'

            ];
        }
    }


    /*
    =====================================================
    MARK STEP BLOCKED
    =====================================================

    User ko clear reason dikhaya ja sakega ke workflow
    kyun ruk gaya.
    */

    private function markStepBlocked(
        int $stepId,
        string $reason
    ): void {

        $sql = "
            UPDATE service_workflow_steps

            SET
                status = 'blocked',
                blocked_reason = ?,
                updated_at = NOW()

            WHERE id = ?

              AND status = 'current'
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([

            $reason,

            $stepId

        ]);
    }


    /*
    =====================================================
    COMPLETE CASE
    =====================================================

    Jab final workflow step complete ho jaye to service
    case ko completed mark karta hai.
    */

    private function completeCase(
        int $caseId
    ): void {

        $sql = "
            UPDATE service_cases

            SET
                status = 'completed',
                current_step = NULL,
                completed_at = NOW(),
                updated_at = NOW()

            WHERE id = ?
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([
            $caseId
        ]);
    }


    /*
    =====================================================
    GET CASE
    =====================================================
    */

    private function getCase(
        int $caseId
    ): ?array {

        $sql = "
            SELECT

                id,
                client_id,
                service_key,
                service_name,
                status,
                current_step,
                assigned_to,
                created_by,
                created_at,
                updated_at

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


        $case =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        return
            $case ?: null;
    }
}
```
