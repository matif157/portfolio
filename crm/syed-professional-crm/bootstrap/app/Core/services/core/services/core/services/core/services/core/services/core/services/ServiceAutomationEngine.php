```php
<?php
/**
 * =====================================================
 * FILE: core/services/ServiceAutomationEngine.php
 *
 * PURPOSE:
 * SPS CRM ka central automation engine.
 *
 * Ye engine predefined business rules ke according
 * automatic administrative actions trigger karta hai.
 *
 * IMPORTANT:
 *
 * Automation ka matlab ye nahi ke CRM professional
 * judgment khud karega.
 *
 * CRM sirf predefined workflow actions automate karega,
 * jaise:
 *
 * - task create karna
 * - document request create karna
 * - reminder schedule karna
 * - notification create karna
 * - workflow step move karna
 * - internal alert create karna
 * - client communication queue karna
 *
 * Professional approval / tax decision / immigration
 * decision authorized human staff ke paas rahega.
 *
 *
 * WORKFLOW:
 *
 * EVENT
 *   ↓
 * RULES CHECK
 *   ↓
 * CONDITIONS CHECK
 *   ↓
 * ACTIONS EXECUTE
 *   ↓
 * AUTOMATION LOG
 *
 *
 * Example:
 *
 * Document approved
 *       ↓
 * Check automation rules
 *       ↓
 * Create next task
 *       ↓
 * Notify assigned employee
 *       ↓
 * Log automation
 *
 *
 * =====================================================
 */


/**
 * =====================================================
 * CLASS: ServiceAutomationEngine
 *
 * PURPOSE:
 * CRM events ko predefined automation rules ke through
 * process karna.
 * =====================================================
 */

class ServiceAutomationEngine
{

    /*
    =====================================================
    DATABASE CONNECTION
    =====================================================

    PDO = PHP ka built-in database interface.

    Is connection ke through automation rules aur
    related CRM records access honge.
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
    EVENT: SERVICE CREATED
    =====================================================

    Jab naya service case create hota hai to ye event
    trigger hoga.

    Example:

    Client selects:
        Tax Filing

    CRM:
        Service Case Created

    Phir:
        - workflow start
        - initial tasks
        - required documents
        - notifications
    */

    public function serviceCreated(
        int $caseId,
        int $triggeredBy
    ): array {

        return $this->processEvent(
            'service.created',
            $caseId,
            $triggeredBy
        );
    }


    /*
    =====================================================
    EVENT: DOCUMENT UPLOADED
    =====================================================

    Client ya staff document upload kare to automation
    engine ko event diya jayega.

    IMPORTANT:

    Uploaded document ko approved nahi samjha jayega.

    Automation sirf next administrative action trigger
    kar sakti hai.
    */

    public function documentUploaded(
        int $caseId,
        int $documentRequestId,
        int $triggeredBy
    ): array {

        return $this->processEvent(
            'document.uploaded',
            $caseId,
            $triggeredBy,
            [
                'document_request_id' =>
                    $documentRequestId
            ]
        );
    }


    /*
    =====================================================
    EVENT: DOCUMENT APPROVED
    =====================================================

    Jab authorized reviewer document approve karega
    tab ye event trigger hoga.

    Example:

    Passport approved
        ↓
    CRM checks remaining documents
        ↓
    Agar all required docs complete:
        create next review task
    */

    public function documentApproved(
        int $caseId,
        int $documentRequestId,
        int $triggeredBy
    ): array {

        return $this->processEvent(
            'document.approved',
            $caseId,
            $triggeredBy,
            [
                'document_request_id' =>
                    $documentRequestId
            ]
        );
    }


    /*
    =====================================================
    EVENT: DOCUMENT REJECTED
    =====================================================

    Rejected document ke baad CRM automatically:
    
    - client follow-up task
    - staff notification
    - re-request workflow
    
    trigger kar sakta hai.
    */

    public function documentRejected(
        int $caseId,
        int $documentRequestId,
        int $triggeredBy
    ): array {

        return $this->processEvent(
            'document.rejected',
            $caseId,
            $triggeredBy,
            [
                'document_request_id' =>
                    $documentRequestId
            ]
        );
    }


    /*
    =====================================================
    EVENT: WORKFLOW STEP COMPLETED
    =====================================================

    Jab ek workflow step complete ho jaye to next
    workflow action automatically process ho sakta hai.
    */

    public function workflowStepCompleted(
        int $caseId,
        int $stepId,
        int $triggeredBy
    ): array {

        return $this->processEvent(
            'workflow.step.completed',
            $caseId,
            $triggeredBy,
            [
                'step_id' =>
                    $stepId
            ]
        );
    }


    /*
    =====================================================
    EVENT: PAYMENT RECEIVED
    =====================================================

    Payment receive hone ke baad CRM:
    
    - payment activity log
    - invoice status update
    - task creation
    - staff notification
    
    jaise administrative actions kar sakta hai.
    */

    public function paymentReceived(
        int $caseId,
        int $paymentId,
        int $triggeredBy
    ): array {

        return $this->processEvent(
            'payment.received',
            $caseId,
            $triggeredBy,
            [
                'payment_id' =>
                    $paymentId
            ]
        );
    }


    /*
    =====================================================
    EVENT: CASE OVERDUE
    =====================================================

    Scheduler/cron future mein overdue cases ke liye
    is event ko trigger karega.
    */

    public function caseOverdue(
        int $caseId
    ): array {

        return $this->processEvent(
            'case.overdue',
            $caseId,
            0
        );
    }


    /*
    =====================================================
    EVENT PROCESSOR
    =====================================================

    Ye central method hai.

    Har automation event isi method se guzarta hai.
    */

    private function processEvent(
        string $eventKey,
        int $caseId,
        int $triggeredBy,
        array $eventData = []
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


        if (
            trim($eventKey) === ''
        ) {

            return $this->error(
                'Automation event is required.'
            );
        }


        /*
        =================================================
        CASE CHECK
        =================================================

        Automation kisi non-existing case par run
        nahi honi chahiye.
        */

        $case =
            $this->getCase(
                $caseId
            );


        if (!$case) {

            return $this->error(
                'Service case was not found.'
            );
        }


        /*
        =================================================
        LOAD ACTIVE AUTOMATION RULES
        =================================================

        Database mein active rules stored honge.

        Example:

        event_key:
            document.approved

        action_type:
            create_task

        active:
            1
        */

        $rules =
            $this->getActiveRules(
                $eventKey,
                $case['service_key']
            );


        /*
        -------------------------------------------------
        Agar koi rule configured nahi hai to event ko
        failure nahi samjhenge.

        Iska matlab sirf itna hai ke is event ke liye
        koi automation configured nahi.
        -------------------------------------------------
        */

        if (
            empty($rules)
        ) {

            $this->logAutomation(
                $caseId,
                $eventKey,
                null,
                'no_rules',
                'No active automation rules were configured.',
                $triggeredBy
            );


            return [

                'success' => true,

                'message' =>
                    'Event received but no automation rules were configured.',

                'rules_processed' =>
                    0

            ];
        }


        /*
        =================================================
        PROCESS RULES
        =================================================
        */

        $results = [];

        $processed = 0;


        /*
        -------------------------------------------------
        foreach() = PHP built-in language construct.

        Har automation rule ko one-by-one process karta hai.
        -------------------------------------------------
        */

        foreach (
            $rules as $rule
        ) {

            /*
            ---------------------------------------------
            Rule conditions check.
            ---------------------------------------------
            */

            $conditionsPassed =
                $this->conditionsPass(
                    $rule,
                    $case,
                    $eventData
                );


            if (!$conditionsPassed) {

                $this->logAutomation(
                    $caseId,
                    $eventKey,
                    (int) $rule['id'],
                    'condition_failed',
                    'Automation rule conditions were not satisfied.',
                    $triggeredBy
                );


                $results[] = [

                    'rule_id' =>
                        (int)
                        $rule['id'],

                    'status' =>
                        'condition_failed'

                ];


                continue;
            }


            /*
            ---------------------------------------------
            Conditions pass ho gayin.

            Ab action execute hoga.
            ---------------------------------------------
            */

            $actionResult =
                $this->executeAction(
                    $rule,
                    $case,
                    $eventData,
                    $triggeredBy
                );


            $processed++;


            $results[] = [

                'rule_id' =>
                    (int)
                    $rule['id'],

                'status' =>
                    $actionResult['success']
                        ? 'executed'
                        : 'failed',

                'message' =>
                    $actionResult['message']

            ];
        }


        /*
        =================================================
        FINAL RESULT
        =================================================
        */

        return [

            'success' => true,

            'message' =>
                'Automation event processed.',

            'event' =>
                $eventKey,

            'case_id' =>
                $caseId,

            'rules_found' =>
                count($rules),

            'rules_processed' =>
                $processed,

            'results' =>
                $results

        ];
    }


    /*
    =====================================================
    GET ACTIVE RULES
    =====================================================

    Service-specific rule ko priority ke according load
    karenge.

    Example:

    Tax Filing
        ↓
    Tax-specific rule

    Agar tax-specific rule nahi:
        ↓
    Global rule
    */

    private function getActiveRules(
        string $eventKey,
        string $serviceKey
    ): array {

        $sql = "
            SELECT

                id,
                rule_name,
                event_key,
                service_key,
                condition_json,
                action_type,
                action_config_json,
                priority

            FROM automation_rules

            WHERE active = 1

              AND event_key = ?

              AND (
                    service_key = ?
                    OR service_key IS NULL
                    OR service_key = ''
                  )

            ORDER BY
                priority DESC,
                id ASC
        ";


        /*
        -------------------------------------------------
        prepare() = PDO method.

        SQL ko prepared statement mein convert karta hai.

        execute() = values safely pass karta hai.
        -------------------------------------------------
        */

        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([

            $eventKey,

            $serviceKey

        ]);


        return
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }


    /*
    =====================================================
    CHECK CONDITIONS
    =====================================================

    Automation rule ke conditions ko evaluate karta hai.

    IMPORTANT:

    Ye controlled condition system hai.

    Hum arbitrary PHP code database se execute nahi
    karenge.

    Yani database mein koi banda PHP code store karke
    CRM se execute nahi karwa sakta.
    */

    private function conditionsPass(
        array $rule,
        array $case,
        array $eventData
    ): bool {

        $json =
            $rule['condition_json']
            ?? null;


        /*
        -------------------------------------------------
        Agar condition empty hai to rule automatically
        eligible hai.
        -------------------------------------------------
        */

        if (
            empty($json)
        ) {

            return true;
        }


        /*
        -------------------------------------------------
        json_decode() = PHP built-in function.

        JSON configuration ko PHP array mein convert karta hai.
        -------------------------------------------------
        */

        $conditions =
            json_decode(
                $json,
                true
            );


        if (
            !is_array($conditions)
        ) {

            /*
            ---------------------------------------------
            Invalid rule configuration ko execute nahi
            karna.

            Security aur reliability ke liye fail closed.
            ---------------------------------------------
            */

            return false;
        }


        /*
        -------------------------------------------------
        AND logic:

        Abhi default behavior ye hai ke tamam configured
        conditions pass honi chahiye.
        -------------------------------------------------
        */

        foreach (
            $conditions as $condition
        ) {

            if (
                !is_array($condition)
            ) {

                return false;
            }


            $field =
                $condition['field']
                ?? '';


            $operator =
                $condition['operator']
                ?? '';


            $expected =
                $condition['value']
                ?? null;


            /*
            ---------------------------------------------
            Current value case/event data se safely retrieve
            karte hain.
            ---------------------------------------------
            */

            $actual =
                $this->getConditionValue(
                    $field,
                    $case,
                    $eventData
                );


            /*
            ---------------------------------------------
            Sirf allowed operators support honge.

            Arbitrary expression execute nahi hoga.
            ---------------------------------------------
            */

            $passed =
                $this->compareValues(
                    $actual,
                    $operator,
                    $expected
                );


            if (!$passed) {

                return false;
            }
        }


        return true;
    }


    /*
    =====================================================
    GET CONDITION VALUE
    =====================================================

    Rule configuration ke field ko actual CRM value
    se match karta hai.
    */

    private function getConditionValue(
        string $field,
        array $case,
        array $eventData
    ): mixed {

        /*
        -------------------------------------------------
        Allowed case fields.
        -------------------------------------------------
        */

        $allowedCaseFields = [

            'case.status',
            'case.service_key',
            'case.assigned_to',
            'case.client_id'

        ];


        if (
            in_array(
                $field,
                $allowedCaseFields,
                true
            )
        ) {

            $caseField =
                str_replace(
                    'case.',
                    '',
                    $field
                );


            return
                $case[$caseField]
                ?? null;
        }


        /*
        -------------------------------------------------
        Event fields.
        -------------------------------------------------
        */

        if (
            str_starts_with(
                $field,
                'event.'
            )
        ) {

            $eventField =
                str_replace(
                    'event.',
                    '',
                    $field
                );


            return
                $eventData[$eventField]
                ?? null;
        }


        /*
        -------------------------------------------------
        Unknown field.

        Unknown fields ko null return karenge.

        Ye secure "fail closed" behavior hai.
        -------------------------------------------------
        */

        return null;
    }


    /*
    =====================================================
    COMPARE VALUES
    =====================================================

    Supported comparison operators:

        =
        !=
        >
        <
        >=
        <=
        contains
    */

    private function compareValues(
        mixed $actual,
        string $operator,
        mixed $expected
    ): bool {

        switch (
            $operator
        ) {

            case '=':

                return
                    $actual == $expected;


            case '!=':

                return
                    $actual != $expected;


            case '>':

                return
                    $actual > $expected;


            case '<':

                return
                    $actual < $expected;


            case '>=':

                return
                    $actual >= $expected;


            case '<=':

                return
                    $actual <= $expected;


            case 'contains':

                /*
                -----------------------------------------
                String comparison.

                strtolower() = PHP built-in function.

                Dono values ko lowercase kar ke
                case-insensitive matching hoti hai.
                -----------------------------------------
                */

                return
                    str_contains(
                        strtolower(
                            (string)
                            $actual
                        ),
                        strtolower(
                            (string)
                            $expected
                        )
                    );


            default:

                /*
                -----------------------------------------
                Unknown operator ko allow nahi karna.
                -----------------------------------------
                */

                return false;
        }
    }


    /*
    =====================================================
    EXECUTE ACTION
    =====================================================

    Rule ke action_type ke according appropriate action
    execute hota hai.

    IMPORTANT:

    Hum database se arbitrary PHP function call nahi
    karenge.

    Sirf predefined actions allowed hain.
    =====================================================
    */

    private function executeAction(
        array $rule,
        array $case,
        array $eventData,
        int $triggeredBy
    ): array {

        $actionType =
            $rule['action_type']
            ?? '';


        /*
        -------------------------------------------------
        Action configuration JSON load.
        -------------------------------------------------
        */

        $config = [];


        if (
            !empty(
                $rule['action_config_json']
            )
        ) {

            $decoded =
                json_decode(
                    $rule['action_config_json'],
                    true
                );


            if (
                is_array($decoded)
            ) {

                $config =
                    $decoded;
            }
        }


        /*
        =================================================
        ALLOWED ACTIONS
        =================================================
        */

        switch (
            $actionType
        ) {

            case 'create_task':

                return
                    $this->createTask(
                        $case,
                        $config,
                        $triggeredBy
                    );


            case 'create_notification':

                return
                    $this->createNotification(
                        $case,
                        $config,
                        $triggeredBy
                    );


            case 'update_case_status':

                return
                    $this->updateCaseStatus(
                        $case,
                        $config,
                        $triggeredBy
                    );


            case 'create_document_request':

                return
                    $this->createDocumentRequest(
                        $case,
                        $config,
                        $triggeredBy
                    );


            default:

                /*
                -----------------------------------------
                Unknown action ko silently execute nahi
                karna.

                -----------------------------------------
                */

                $this->logAutomation(
                    (int)
                    $case['id'],

                    $rule['event_key'],

                    (int)
                    $rule['id'],

                    'failed',

                    'Unknown automation action type.',

                    $triggeredBy
                );


                return $this->error(
                    'Unknown automation action type.'
                );
        }
    }


    /*
    =====================================================
    ACTION: CREATE TASK
    =====================================================
    */

    private function createTask(
        array $case,
        array $config,
        int $triggeredBy
    ): array {

        $title =
            trim(
                (string)
                (
                    $config['title']
                    ?? 'Automated Service Task'
                )
            );


        $description =
            trim(
                (string)
                (
                    $config['description']
                    ?? ''
                )
            );


        $assignedTo =
            isset(
                $config['assigned_to']
            )
                ? (int)
                  $config['assigned_to']
                : (
                    (int)
                    (
                        $case['assigned_to']
                        ?? 0
                    )
                );


        $priority =
            $config['priority']
            ?? 'normal';


        /*
        -------------------------------------------------
        Task insert.
        -------------------------------------------------
        */

        $sql = "
            INSERT INTO tasks
            (
                service_case_id,
                title,
                description,
                assigned_to,
                priority,
                status,
                created_by,
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
                'pending',
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

            (int)
            $case['id'],

            $title,

            $description,

            $assignedTo > 0
                ? $assignedTo
                : null,

            $priority,

            $triggeredBy > 0
                ? $triggeredBy
                : null

        ]);


        $taskId =
            (int)
            $this->pdo->lastInsertId();


        return [

            'success' => true,

            'message' =>
                'Automation task created.',

            'task_id' =>
                $taskId

        ];
    }


    /*
    =====================================================
    ACTION: CREATE NOTIFICATION
    =====================================================
    */

    private function createNotification(
        array $case,
        array $config,
        int $triggeredBy
    ): array {

        $userId =
            isset(
                $config['user_id']
            )
                ? (int)
                  $config['user_id']
                : (
                    (int)
                    (
                        $case['assigned_to']
                        ?? 0
                    )
                );


        $title =
            trim(
                (string)
                (
                    $config['title']
                    ?? 'Service Case Update'
                )
            );


        $message =
            trim(
                (string)
                (
                    $config['message']
                    ?? 'Your service case requires attention.'
                )
            );


        if ($userId <= 0) {

            return $this->error(
                'Notification recipient is not configured.'
            );
        }


        /*
        -------------------------------------------------
        Notification database mein create hoti hai.

        Actual email/SMS sending ko separate notification
        worker handle karega.

        Is se main CRM request slow nahi hogi.
        -------------------------------------------------
        */

        $sql = "
            INSERT INTO notifications
            (
                user_id,
                service_case_id,
                title,
                message,
                notification_type,
                status,
                created_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                'automation',
                'pending',
                NOW()
            )
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([

            $userId,

            (int)
            $case['id'],

            $title,

            $message

        ]);


        return [

            'success' => true,

            'message' =>
                'Automation notification created.',

            'notification_id' =>
                (int)
                $this->pdo->lastInsertId()

        ];
    }


    /*
    =====================================================
    ACTION: UPDATE CASE STATUS
    =====================================================

    Status automation sirf allowed statuses use karegi.

    Arbitrary status database mein nahi jane denge.
    =====================================================
    */

    private function updateCaseStatus(
        array $case,
        array $config,
        int $triggeredBy
    ): array {

        $newStatus =
            trim(
                (string)
                (
                    $config['status']
                    ?? ''
                )
            );


        $allowedStatuses = [

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


        if (
            !in_array(
                $newStatus,
                $allowedStatuses,
                true
            )
        ) {

            return $this->error(
                'Invalid automated case status.'
            );
        }


        /*
        -------------------------------------------------
        Current status same hai to unnecessary database
        update nahi karna.
        -------------------------------------------------
        */

        if (
            $case['status'] ===
            $newStatus
        ) {

            return [

                'success' => true,

                'message' =>
                    'Case already has the requested status.'

            ];
        }


        /*
        -------------------------------------------------
        Case status update.
        -------------------------------------------------
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

            (int)
            $case['id']

        ]);


        return [

            'success' => true,

            'message' =>
                'Service case status updated automatically.',

            'status' =>
                $newStatus

        ];
    }


    /*
    =====================================================
    ACTION: CREATE DOCUMENT REQUEST
    =====================================================

    Automation kisi workflow event ke baad required
    document request create kar sakti hai.

    Example:

    Tax service started
        ↓
    Automation
        ↓
    Request W-2
    =====================================================
    */

    private function createDocumentRequest(
        array $case,
        array $config,
        int $triggeredBy
    ): array {

        $documentKey =
            trim(
                strtolower(
                    (string)
                    (
                        $config['document_key']
                        ?? ''
                    )
                )
            );


        $documentName =
            trim(
                (string)
                (
                    $config['document_name']
                    ?? ''
                )
            );


        $required =
            !isset(
                $config['required']
            )
            ||
            (bool)
            $config['required'];


        if (
            $documentKey === '' ||
            $documentName === ''
        ) {

            return $this->error(
                'Automated document configuration is incomplete.'
            );
        }


        /*
        -------------------------------------------------
        Duplicate active request check.
        -------------------------------------------------
        */

        $sql = "
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


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([

            (int)
            $case['id'],

            $documentKey

        ]);


        if (
            $stmt->fetch(
                PDO::FETCH_ASSOC
            )
        ) {

            return [

                'success' => true,

                'message' =>
                    'Document request already exists.'

            ];
        }


        /*
        -------------------------------------------------
        New document request create.
        -------------------------------------------------
        */

        $insertSql = "
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


        $insertStmt =
            $this->pdo->prepare(
                $insertSql
            );


        $insertStmt->execute([

            (int)
            $case['id'],

            $documentKey,

            $documentName,

            $config['description']
                ?? null,

            $required
                ? 1
                : 0,

            $config['due_date']
                ?? null

        ]);


        return [

            'success' => true,

            'message' =>
                'Document request created by automation.',

            'document_request_id' =>
                (int)
                $this->pdo->lastInsertId()

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


    /*
    =====================================================
    LOG AUTOMATION
    =====================================================

    Har automation attempt ka record maintain karna
    future audit/debugging ke liye important hai.

    Example:

        Rule #14
        ↓
        document.approved
        ↓
        create_task
        ↓
        executed
    */

    private function logAutomation(
        int $caseId,
        string $eventKey,
        ?int $ruleId,
        string $status,
        string $message,
        int $triggeredBy
    ): void {

        $sql = "
            INSERT INTO automation_logs
            (
                service_case_id,
                rule_id,
                event_key,
                status,
                message,
                triggered_by,
                created_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                NOW()
            )
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([

            $caseId,

            $ruleId,

            $eventKey,

            $status,

            $message,

            $triggeredBy > 0
                ? $triggeredBy
                : null

        ]);
    }


    /*
    =====================================================
    ERROR RESPONSE
    =====================================================
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
