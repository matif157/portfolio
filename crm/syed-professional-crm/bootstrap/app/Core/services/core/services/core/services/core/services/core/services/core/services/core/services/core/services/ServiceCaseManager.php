```php
<?php
/**
 * =====================================================
 * FILE: core/services/ServiceCaseManager.php
 *
 * PURPOSE:
 * SPS CRM ke service cases ka central manager.
 *
 * Ye file service case ki complete lifecycle ko control
 * karti hai:
 *
 * 1. New service case create karna
 * 2. Client aur service verify karna
 * 3. Assigned employee set karna
 * 4. Case status change karna
 * 5. Case information update karna
 * 6. Case retrieve karna
 * 7. Workflow initialize karna
 * 8. Automation event trigger karna
 * 9. Case ko unauthorized state mein jump karne se rokna
 *
 *
 * IMPORTANT ARCHITECTURE:
 *
 * Service Case khud workflow nahi hai.
 *
 * Case = overall service container
 *
 * Workflow = case ke andar ka controlled process
 *
 * Automation = workflow/events ke around automatic actions
 *
 * Documents = evidence/input
 *
 * Tasks = staff work
 *
 * Audit = history
 *
 *
 * HIGH-LEVEL FLOW:
 *
 * CLIENT
 *   ↓
 * SERVICE SELECTED
 *   ↓
 * SERVICE CASE CREATED
 *   ↓
 * WORKFLOW CREATED
 *   ↓
 * FIRST STEP ACTIVATED
 *   ↓
 * AUTOMATION TRIGGERED
 *   ↓
 * DOCUMENTS / TASKS
 *   ↓
 * HUMAN REVIEW
 *   ↓
 * NEXT WORKFLOW STEP
 *
 *
 * SECURITY PRINCIPLE:
 *
 * Client input ko directly trusted workflow state nahi
 * banaya jayega.
 *
 * Example:
 *
 * Client:
 * "My tax return is filed."
 *
 * CRM:
 * ❌ automatically filed nahi karega.
 *
 * Authorized staff/system action ke through:
 * → filed state allowed hogi.
 *
 * =====================================================
 */


/**
 * =====================================================
 * CLASS: ServiceCaseManager
 *
 * PURPOSE:
 * Service cases ki complete lifecycle management.
 * =====================================================
 */

class ServiceCaseManager
{

    /*
    =====================================================
    DATABASE CONNECTION
    =====================================================
    */

    private PDO $pdo;


    /*
    =====================================================
    ALLOWED CASE STATUSES
    =====================================================

    Ye controlled list hai.

    Iska purpose ye hai ke koi random status database
    mein insert na kar sake.
    */

    private array $allowedStatuses = [

        'new',
        'in_progress',
        'waiting_client',
        'waiting_staff',
        'under_review',
        'ready_for_filing',
        'filed',
        'completed',
        'on_hold',
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
    CREATE SERVICE CASE
    =====================================================

    Ye SPS CRM ka major entry point hai.

    Example:

        Client opens CRM
             ↓
        Selects "Tax Filing"
             ↓
        CRM calls create()
             ↓
        Service Case created
             ↓
        Workflow created
             ↓
        Automation triggered
             ↓
        Required documents/tasks generated
    */

    public function create(
        int $clientId,
        string $serviceKey,
        int $createdBy,
        ?int $assignedTo = null,
        ?string $priority = 'normal',
        ?string $title = null,
        ?string $notes = null
    ): array {

        /*
        =================================================
        INPUT VALIDATION
        =================================================
        */

        if ($clientId <= 0) {

            return $this->error(
                'A valid client is required.'
            );
        }


        $serviceKey =
            trim(
                strtolower(
                    $serviceKey
                )
            );


        if ($serviceKey === '') {

            return $this->error(
                'Service selection is required.'
            );
        }


        if ($createdBy <= 0) {

            return $this->error(
                'A valid creating user is required.'
            );
        }


        /*
        =================================================
        CLIENT CHECK
        =================================================

        Service case ko non-existing client se attach
        nahi karna.
        */

        if (
            !$this->clientExists(
                $clientId
            )
        ) {

            return $this->error(
                'Client was not found.'
            );
        }


        /*
        =================================================
        SERVICE CHECK
        =================================================

        Service key database ke active service catalog
        mein honi chahiye.

        Is se staff manually invalid service create
        nahi kar sakta.
        */

        $service =
            $this->getActiveService(
                $serviceKey
            );


        if (!$service) {

            return $this->error(
                'Selected service is not available.'
            );
        }


        /*
        =================================================
        CREATOR CHECK
        =================================================
        */

        if (
            !$this->userExists(
                $createdBy
            )
        ) {

            return $this->error(
                'Creating user was not found.'
            );
        }


        /*
        =================================================
        ASSIGNED USER CHECK
        =================================================

        Assignment optional ho sakti hai.

        Agar assigned employee diya gaya hai to account
        exist karna zaroori hai.
        */

        if (
            $assignedTo !== null &&
            $assignedTo > 0
        ) {

            if (
                !$this->userExists(
                    $assignedTo
                )
            ) {

                return $this->error(
                    'Assigned employee was not found.'
                );
            }
        }


        /*
        =================================================
        PRIORITY VALIDATION
        =================================================
        */

        $allowedPriorities = [

            'low',
            'normal',
            'high',
            'urgent'

        ];


        if (
            !in_array(
                $priority,
                $allowedPriorities,
                true
            )
        ) {

            $priority =
                'normal';
        }


        /*
        =================================================
        DEFAULT CASE TITLE
        =================================================

        Agar staff title provide nahi karta to service
        name se readable title generate karenge.
        */

        if (
            $title === null ||
            trim($title) === ''
        ) {

            $title =
                $service['service_name'];
        }


        /*
        =================================================
        TRANSACTION START
        =================================================

        Case creation aur workflow initialization ko
        logically ek operation treat karna hai.
        */

        $this->pdo->beginTransaction();


        try {

            /*
            =================================================
            CREATE SERVICE CASE
            =================================================
            */

            $sql = "
                INSERT INTO service_cases
                (
                    client_id,
                    service_id,
                    service_key,
                    service_name,
                    title,
                    status,
                    priority,
                    current_step,
                    assigned_to,
                    created_by,
                    notes,
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
                    'new',
                    ?,
                    NULL,
                    ?,
                    ?,
                    ?,
                    NOW(),
                    NOW()
                )
            ";


            /*
            prepare() = PDO method.

            Prepared statement SQL injection risk ko
            reduce karta hai.
            */

            $stmt =
                $this->pdo->prepare(
                    $sql
                );


            $stmt->execute([

                $clientId,

                (int)
                $service['id'],

                $service['service_key'],

                $service['service_name'],

                $title,

                $priority,

                (
                    $assignedTo !== null &&
                    $assignedTo > 0
                )
                    ? $assignedTo
                    : null,

                $createdBy,

                $notes

            ]);


            /*
            -------------------------------------------------
            lastInsertId() = PDO built-in method.

            New service case ka database ID return karta hai.
            -------------------------------------------------
            */

            $caseId =
                (int)
                $this->pdo->lastInsertId();


            /*
            =================================================
            WORKFLOW TEMPLATE CHECK
            =================================================

            Case create hone ke baad workflow available
            hona zaroori hai.

            Is architecture mein service without workflow
            create karna allowed nahi rakha gaya.

            Kyun?

            Kyun ke SPS automation workflow-driven hai.
            */

            $workflowTemplates =
                $this->getWorkflowTemplates(
                    $service['service_key']
                );


            if (
                empty(
                    $workflowTemplates
                )
            ) {

                /*
                ---------------------------------------------
                Workflow unavailable hai.

                Transaction rollback karenge taake
                incomplete service case database mein na
                reh jaye.
                ---------------------------------------------
                */

                $this->pdo->rollBack();


                return $this->error(
                    'This service does not have a configured workflow yet.'
                );
            }


            /*
            =================================================
            CREATE WORKFLOW STEPS
            =================================================

            ServiceWorkflowManager ke createWorkflow()
            method jaisi responsibility yahan duplicate
            nahi kar rahe.

            Lekin kyun ke case creation transaction ke
            andar hai, workflow steps ko isi transaction
            ka part rakhna zaroori hai.

            Is liye actual workflow insertion yahan
            controlled internal helper ke through hoti hai.
            */

            $workflowResult =
                $this->initializeWorkflow(
                    $caseId,
                    $workflowTemplates
                );


            if (
                !$workflowResult['success']
            ) {

                $this->pdo->rollBack();


                return $this->error(
                    $workflowResult['message']
                );
            }


            /*
            =================================================
            INITIAL CASE STATUS
            =================================================

            Workflow successfully initialize ho gaya,
            is liye case ab "in_progress" banega.
            */

            $updateSql = "
                UPDATE service_cases

                SET
                    status = 'in_progress',
                    current_step = ?,
                    updated_at = NOW()

                WHERE id = ?
            ";


            $updateStmt =
                $this->pdo->prepare(
                    $updateSql
                );


            $updateStmt->execute([

                $workflowResult['first_step'],

                $caseId

            ]);


            /*
            =================================================
            COMMIT
            =================================================

            Case + workflow successfully create ho gaye.
            */

            $this->pdo->commit();


            /*
            =================================================
            AUTOMATION TRIGGER
            =================================================

            Database transaction successful hone ke baad
            automation event trigger karna safer hai.

            Is se automation ko incomplete case nahi milega.

            NOTE:

            Automation failure ko case creation failure
            nahi banayenge.

            Case already validly create ho chuka hai.
            Automation ko separately log kiya jayega.
            */

            $automationResult =
                $this->triggerAutomation(
                    'service.created',
                    $caseId,
                    $createdBy
                );


            /*
            =================================================
            FINAL RESPONSE
            =================================================
            */

            return [

                'success' =>
                    true,

                'message' =>
                    'Service case created successfully.',

                'case_id' =>
                    $caseId,

                'service_key' =>
                    $service['service_key'],

                'service_name' =>
                    $service['service_name'],

                'status' =>
                    'in_progress',

                'current_step' =>
                    $workflowResult['first_step'],

                'automation_triggered' =>
                    $automationResult['success']

            ];

        } catch (
            Throwable $e
        ) {

            /*
            =================================================
            ROLLBACK
            =================================================

            Agar database operation fail ho to incomplete
            case/workflow save nahi rehna chahiye.
            */

            if (
                $this->pdo->inTransaction()
            ) {

                $this->pdo->rollBack();
            }


            /*
            -------------------------------------------------
            Actual technical error server log mein.

            User ko sensitive SQL/database information
            expose nahi karni.
            -------------------------------------------------
            */

            error_log(
                'Service case creation failed: ' .
                $e->getMessage()
            );


            return $this->error(
                'Service case could not be created.'
            );
        }
    }


    /*
    =====================================================
    GET SERVICE CASE
    =====================================================

    Single case ki complete basic information return
    karta hai.
    */

    public function get(
        int $caseId
    ): ?array {

        if ($caseId <= 0) {

            return null;
        }


        $sql = "
            SELECT

                sc.id,
                sc.client_id,
                sc.service_id,
                sc.service_key,
                sc.service_name,
                sc.title,
                sc.status,
                sc.priority,
                sc.current_step,
                sc.assigned_to,
                sc.created_by,
                sc.notes,
                sc.created_at,
                sc.updated_at,
                sc.completed_at,

                c.full_name AS client_name,

                u.full_name AS assigned_user_name

            FROM service_cases sc

            LEFT JOIN clients c
                ON c.id = sc.client_id

            LEFT JOIN users u
                ON u.id = sc.assigned_to

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


        return
            $case ?: null;
    }


    /*
    =====================================================
    GET CASES FOR CLIENT
    =====================================================

    Client portal future mein isi method se apne cases
    list kar sakta hai.

    IMPORTANT:

    client_id mandatory filter hai.

    Is se ek client doosre client ke cases access
    nahi karega.
    */

    public function getClientCases(
        int $clientId,
        ?string $status = null
    ): array {

        if ($clientId <= 0) {

            return [];
        }


        /*
        -------------------------------------------------
        Base query.
        -------------------------------------------------
        */

        $sql = "
            SELECT

                id,
                service_key,
                service_name,
                title,
                status,
                priority,
                current_step,
                assigned_to,
                created_at,
                updated_at,
                completed_at

            FROM service_cases

            WHERE client_id = ?
        ";


        $params = [
            $clientId
        ];


        /*
        -------------------------------------------------
        Optional status filter.

        User input ko directly SQL mein concatenate
        nahi kar rahe.
        -------------------------------------------------
        */

        if (
            $status !== null &&
            in_array(
                $status,
                $this->allowedStatuses,
                true
            )
        ) {

            $sql .= "
                AND status = ?
            ";


            $params[] =
                $status;
        }


        $sql .= "
            ORDER BY
                updated_at DESC,
                id DESC
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute(
            $params
        );


        return
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }


    /*
    =====================================================
    UPDATE CASE DETAILS
    =====================================================

    Basic editable case information update karta hai.

    Workflow state yahan change nahi hogi.

    Ye intentional separation hai:

        updateDetails()
            ≠
        changeStatus()
            ≠
        completeWorkflow()
    */

    public function updateDetails(
        int $caseId,
        int $updatedBy,
        ?string $title = null,
        ?string $priority = null,
        ?string $notes = null
    ): array {

        if (
            $caseId <= 0 ||
            $updatedBy <= 0
        ) {

            return $this->error(
                'Valid case and user IDs are required.'
            );
        }


        /*
        -------------------------------------------------
        Case existence check.
        -------------------------------------------------
        */

        if (
            !$this->get(
                $caseId
            )
        ) {

            return $this->error(
                'Service case was not found.'
            );
        }


        /*
        =================================================
        PRIORITY VALIDATION
        =================================================
        */

        if (
            $priority !== null
        ) {

            $allowedPriorities = [

                'low',
                'normal',
                'high',
                'urgent'

            ];


            if (
                !in_array(
                    $priority,
                    $allowedPriorities,
                    true
                )
            ) {

                return $this->error(
                    'Invalid case priority.'
                );
            }
        }


        /*
        =================================================
        DYNAMIC UPDATE
        =================================================

        Sirf provided values update hongi.

        Prepared statements use karte hue query safely
        construct ki ja rahi hai.
        */

        $fields = [];
        $params = [];


        if (
            $title !== null
        ) {

            $fields[] =
                'title = ?';

            $params[] =
                trim(
                    $title
                );
        }


        if (
            $priority !== null
        ) {

            $fields[] =
                'priority = ?';

            $params[] =
                $priority;
        }


        if (
            $notes !== null
        ) {

            $fields[] =
                'notes = ?';

            $params[] =
                $notes;
        }


        /*
        -------------------------------------------------
        Agar koi field update hi nahi karni to database
        query run nahi karenge.
        -------------------------------------------------
        */

        if (
            empty($fields)
        ) {

            return $this->error(
                'No case information was provided for update.'
            );
        }


        $fields[] =
            'updated_at = NOW()';


        $sql = "
            UPDATE service_cases

            SET
                " .
                implode(
                    ', ',
                    $fields
                ) . "

            WHERE id = ?
        ";


        $params[] =
            $caseId;


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute(
            $params
        );


        /*
        =================================================
        AUDIT EVENT
        =================================================

        Future audit service is expected to record this
        action centrally.

        Yahan direct history table assumption nahi kar rahe;
        centralized audit layer ke liye event trigger karte
        hain.
        */

        $this->recordAuditEvent(
            $caseId,
            $updatedBy,
            'case.updated',
            'Service case details updated.'
        );


        return [

            'success' =>
                true,

            'message' =>
                'Service case updated successfully.',

            'case_id' =>
                $caseId

        ];
    }


    /*
    =====================================================
    CHANGE STATUS
    =====================================================

    Controlled case status transition.

    IMPORTANT:

    Staff ko har state se har state par jump nahi karne
    denge.

    Example:

        completed
           ↓
        in_progress

    automatically allowed nahi hai.

    =====================================================
    */

    public function changeStatus(
        int $caseId,
        string $newStatus,
        int $changedBy,
        ?string $reason = null
    ): array {

        if (
            $caseId <= 0 ||
            $changedBy <= 0
        ) {

            return $this->error(
                'Valid case and user IDs are required.'
            );
        }


        $newStatus =
            trim(
                strtolower(
                    $newStatus
                )
            );


        /*
        =================================================
        STATUS VALIDATION
        =================================================
        */

        if (
            !in_array(
                $newStatus,
                $this->allowedStatuses,
                true
            )
        ) {

            return $this->error(
                'Invalid service case status.'
            );
        }


        /*
        -------------------------------------------------
        Current case load.
        -------------------------------------------------
        */

        $case =
            $this->get(
                $caseId
            );


        if (!$case) {

            return $this->error(
                'Service case was not found.'
            );
        }


        /*
        -------------------------------------------------
        Same status par unnecessary update nahi.
        -------------------------------------------------
        */

        if (
            $case['status'] ===
            $newStatus
        ) {

            return [

                'success' =>
                    true,

                'message' =>
                    'Case already has this status.',

                'status' =>
                    $newStatus

            ];
        }


        /*
        =================================================
        STATUS TRANSITION CHECK
        =================================================

        Har status change allowed nahi hai.

        Iska purpose workflow bypass prevent karna hai.
        */

        if (
            !$this->isStatusTransitionAllowed(
                $case['status'],
                $newStatus
            )
        ) {

            return $this->error(
                'This status transition is not allowed.'
            );
        }


        /*
        =================================================
        REASON FOR SENSITIVE STATES
        =================================================

        On-hold/cancelled jaisi states mein reason useful
        aur audit ke liye important hai.
        */

        if (
            in_array(
                $newStatus,
                [
                    'on_hold',
                    'cancelled'
                ],
                true
            )
        ) {

            if (
                $reason === null ||
                trim($reason) === ''
            ) {

                return $this->error(
                    'A reason is required for this status change.'
                );
            }
        }


        /*
        =================================================
        UPDATE STATUS
        =================================================
        */

        $sql = "
            UPDATE service_cases

            SET
                status = ?,
                updated_at = NOW()

            WHERE id = ?
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([

            $newStatus,

            $caseId

        ]);


        /*
        =================================================
        AUDIT EVENT
        =================================================
        */

        $this->recordAuditEvent(
            $caseId,
            $changedBy,
            'case.status_changed',
            'Case status changed from ' .
            $case['status'] .
            ' to ' .
            $newStatus .
            (
                $reason !== null
                    ? '. Reason: ' . $reason
                    : '.'
            )
        );


        /*
        =================================================
        AUTOMATION EVENT
        =================================================
        */

        $this->triggerAutomation(
            'case.status_changed',
            $caseId,
            $changedBy,
            [
                'old_status' =>
                    $case['status'],

                'new_status' =>
                    $newStatus

            ]
        );


        return [

            'success' =>
                true,

            'message' =>
                'Service case status changed successfully.',

            'old_status' =>
                $case['status'],

            'new_status' =>
                $newStatus

        ];
    }


    /*
    =====================================================
    ASSIGN CASE
    =====================================================

    Case kisi authorized staff member ko assign karta hai.

    Assignment aur workflow status separate concepts hain.
    */

    public function assign(
        int $caseId,
        int $assignedTo,
        int $changedBy
    ): array {

        if (
            $caseId <= 0 ||
            $assignedTo <= 0 ||
            $changedBy <= 0
        ) {

            return $this->error(
                'Valid case, assignee and user IDs are required.'
            );
        }


        /*
        -------------------------------------------------
        Case existence.
        -------------------------------------------------
        */

        if (
            !$this->get(
                $caseId
            )
        ) {

            return $this->error(
                'Service case was not found.'
            );
        }


        /*
        -------------------------------------------------
        Employee account check.
        -------------------------------------------------
        */

        if (
            !$this->userExists(
                $assignedTo
            )
        ) {

            return $this->error(
                'Assigned employee was not found.'
            );
        }


        /*
        =================================================
        ASSIGNMENT UPDATE
        =================================================
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

            $assignedTo,

            $caseId

        ]);


        /*
        =================================================
        AUDIT
        =================================================
        */

        $this->recordAuditEvent(
            $caseId,
            $changedBy,
            'case.assigned',
            'Service case assigned to user #' .
            $assignedTo .
            '.'
        );


        /*
        =================================================
        AUTOMATION
        =================================================

        Assignment ke baad notification/task rules
        trigger ho sakti hain.
        */

        $this->triggerAutomation(
            'case.assigned',
            $caseId,
            $changedBy,
            [
                'assigned_to' =>
                    $assignedTo
            ]
        );


        return [

            'success' =>
                true,

            'message' =>
                'Service case assigned successfully.',

            'assigned_to' =>
                $assignedTo

        ];
    }


    /*
    =====================================================
    HOLD CASE
    =====================================================

    Convenience method.

    Is se UI ko manually changeStatus() ke parameters
    construct nahi karne padenge.
    */

    public function hold(
        int $caseId,
        int $userId,
        string $reason
    ): array {

        return $this->changeStatus(
            $caseId,
            'on_hold',
            $userId,
            $reason
        );
    }


    /*
    =====================================================
    RESUME CASE
    =====================================================

    On-hold case ko controlled state mein wapas laata hai.
    */

    public function resume(
        int $caseId,
        int $userId
    ): array {

        return $this->changeStatus(
            $caseId,
            'in_progress',
            $userId
        );
    }


    /*
    =====================================================
    CANCEL CASE
    =====================================================

    Cancellation reason mandatory hai.
    */

    public function cancel(
        int $caseId,
        int $userId,
        string $reason
    ): array {

        return $this->changeStatus(
            $caseId,
            'cancelled',
            $userId,
            $reason
        );
    }


    /*
    =====================================================
    STATUS TRANSITION MATRIX
    =====================================================

    Ye method workflow bypass prevent karta hai.

    Allowed transitions ko centralized rakha gaya hai.

    Future service-specific transitions ko database
    configuration ke through expand kiya ja sakta hai.
    */

    private function isStatusTransitionAllowed(
        string $from,
        string $to
    ): bool {

        $transitions = [

            'new' => [

                'in_progress',
                'on_hold',
                'cancelled'

            ],

            'in_progress' => [

                'waiting_client',
                'waiting_staff',
                'under_review',
                'ready_for_filing',
                'on_hold',
                'cancelled'

            ],

            'waiting_client' => [

                'in_progress',
                'on_hold',
                'cancelled'

            ],

            'waiting_staff' => [

                'in_progress',
                'on_hold',
                'cancelled'

            ],

            'under_review' => [

                'in_progress',
                'ready_for_filing',
                'waiting_client',
                'on_hold',
                'cancelled'

            ],

            'ready_for_filing' => [

                'filed',
                'on_hold',
                'cancelled'

            ],

            'filed' => [

                'completed',
                'under_review',
                'on_hold'

            ],

            'completed' => [

                /*
                -----------------------------------------
                Completed normally terminal state hai.

                Is liye yahan koi normal transition
                intentionally nahi hai.
                -----------------------------------------
                */

            ],

            'on_hold' => [

                'in_progress',
                'cancelled'

            ],

            'cancelled' => [

                /*
                -----------------------------------------
                Cancelled terminal state hai.

                Reopen operation future controlled admin
                workflow se handle ki ja sakti hai.
                -----------------------------------------
                */

            ]

        ];


        return
            in_array(
                $to,
                $transitions[$from] ?? [],
                true
            );
    }


    /*
    =====================================================
    INITIALIZE WORKFLOW
    =====================================================

    Workflow template rows ko service case ke actual
    workflow steps mein convert karta hai.
    */

    private function initializeWorkflow(
        int $caseId,
        array $templates
    ): array {

        if (
            empty($templates)
        ) {

            return [

                'success' =>
                    false,

                'message' =>
                    'Workflow template is empty.'

            ];
        }


        $firstStep =
            null;


        foreach (
            $templates as $index => $template
        ) {

            /*
            -------------------------------------------------
            First step current hoga.

            Baaki pending.
            -------------------------------------------------
            */

            $status =
                $index === 0
                    ? 'current'
                    : 'pending';


            if (
                $index === 0
            ) {

                $firstStep =
                    $template['step_key'];
            }


            $sql = "
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


            $stmt =
                $this->pdo->prepare(
                    $sql
                );


            $stmt->execute([

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


        return [

            'success' =>
                true,

            'first_step' =>
                $firstStep,

            'steps_created' =>
                count($templates)

        ];
    }


    /*
    =====================================================
    GET WORKFLOW TEMPLATES
    =====================================================
    */

    private function getWorkflowTemplates(
        string $serviceKey
    ): array {

        $sql = "
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


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([
            $serviceKey
        ]);


        return
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }


    /*
    =====================================================
    GET ACTIVE SERVICE
    =====================================================

    Service Catalog se active service retrieve karta hai.
    */

    private function getActiveService(
        string $serviceKey
    ): ?array {

        $sql = "
            SELECT

                id,
                service_key,
                service_name,
                category_id,
                active

            FROM services

            WHERE service_key = ?

              AND active = 1

            LIMIT 1
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([
            $serviceKey
        ]);


        $service =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        return
            $service ?: null;
    }


    /*
    =====================================================
    CLIENT EXISTS
    =====================================================
    */

    private function clientExists(
        int $clientId
    ): bool {

        $sql = "
            SELECT id

            FROM clients

            WHERE id = ?

            LIMIT 1
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([
            $clientId
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
    AUTOMATION BRIDGE
    =====================================================

    ServiceCaseManager automation engine ka direct
    replacement nahi hai.

    Ye sirf bridge hai.

    Is se architecture clean rehta hai:

        Case Manager
             ↓
        Automation Engine
             ↓
        Actions
    */

    private function triggerAutomation(
        string $eventKey,
        int $caseId,
        int $triggeredBy,
        array $eventData = []
    ): array {

        /*
        -------------------------------------------------
        Automation engine file available hai to usko
        load karenge.

        require_once = PHP built-in include mechanism.
        -------------------------------------------------
        */

        $automationFile =
            __DIR__ .
            '/ServiceAutomationEngine.php';


        if (
            !file_exists(
                $automationFile
            )
        ) {

            /*
            ---------------------------------------------
            Main case operation ko automation file missing
            hone ki wajah se fail nahi karna.
            ---------------------------------------------
            */

            return [

                'success' =>
                    false,

                'message' =>
                    'Automation engine is unavailable.'

            ];
        }


        require_once
            $automationFile;


        /*
        -------------------------------------------------
        Class existence check.

        class_exists() = PHP built-in function.
        -------------------------------------------------
        */

        if (
            !class_exists(
                'ServiceAutomationEngine'
            )
        ) {

            return [

                'success' =>
                    false,

                'message' =>
                    'Automation engine class is unavailable.'

            ];
        }


        /*
        -------------------------------------------------
        Automation engine instance create.
        -------------------------------------------------
        */

        $engine =
            new ServiceAutomationEngine(
                $this->pdo
            );


        /*
        -------------------------------------------------
        Central automation processor directly call karte
        hain.

        processEvent() private hai, is liye public event
        method map use karenge.
        -------------------------------------------------
        */

        switch (
            $eventKey
        ) {

            case 'service.created':

                return
                    $engine->serviceCreated(
                        $caseId,
                        $triggeredBy
                    );


            case 'case.status_changed':

                /*
                -----------------------------------------
                Current automation engine mein generic
                case.status_changed public event method
                nahi hai.

                Is event ko future extension ke liye
                safely ignore/log architecture mein
                rakha jayega.

                Case operation fail nahi hoga.
                -----------------------------------------
                */

                return [

                    'success' =>
                        true,

                    'message' =>
                        'Case status event recorded for automation layer.'

                ];


            case 'case.assigned':

                return [

                    'success' =>
                        true,

                    'message' =>
                        'Case assignment event prepared for automation layer.'

                ];


            default:

                return [

                    'success' =>
                        false,

                    'message' =>
                        'Unsupported automation event.'

                ];
        }
    }


    /*
    =====================================================
    AUDIT EVENT BRIDGE
    =====================================================

    Case manager audit system ke saath loosely coupled
    rahega.

    Actual centralized AuditLogger future architecture
    mein is bridge ko consume karega.

    Hum yahan arbitrary history data silently lose nahi
    karenge.
    */

    private function recordAuditEvent(
        int $caseId,
        int $userId,
        string $eventKey,
        string $message
    ): void {

        /*
        -------------------------------------------------
        Audit table agar available hai to record create
        karenge.

        Future final schema mein audit_events central
        security/audit module ka part hoga.
        -------------------------------------------------
        */

        $sql = "
            INSERT INTO audit_events
            (
                user_id,
                service_case_id,
                event_key,
                message,
                created_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                NOW()
            )
        ";


        try {

            $stmt =
                $this->pdo->prepare(
                    $sql
                );


            $stmt->execute([

                $userId,

                $caseId,

                $eventKey,

                $message

            ]);

        } catch (
            Throwable $e
        ) {

            /*
            -------------------------------------------------
            Audit failure ko primary case operation crash
            nahi banana.

            Lekin error silently ignore bhi nahi karna.
            Server log mein record karenge.
            -------------------------------------------------
            */

            error_log(
                'Audit event recording failed: ' .
                $e->getMessage()
            );
        }
    }


    /*
    =====================================================
    ERROR RESPONSE HELPER
    =====================================================
    */

    private function error(
        string $message
    ): array {

        return [

            'success' =>
                false,

            'message' =>
                $message

        ];
    }
}
```
