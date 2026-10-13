```php
<?php
/**
 * =====================================================
 * FILE: core/automation/AutomationEngine.php
 *
 * PURPOSE:
 * SPS CRM ka central automation engine.
 *
 * Ye class repetitive CRM actions ko ek central jagah
 * se control karne ke liye banayi gayi hai.
 *
 * WORKFLOW:
 *
 * Event
 *    ↓
 * Automation Engine
 *    ↓
 * Matching Rules
 *    ↓
 * Conditions
 *    ↓
 * Actions
 *    ↓
 * Task / Notification / Document Request / etc.
 *
 * EXAMPLE:
 *
 * Document Approved
 *       ↓
 * Check Workflow
 *       ↓
 * Complete Current Step
 *       ↓
 * Activate Next Step
 *       ↓
 * Create Task
 *       ↓
 * Notify Assigned Staff
 *
 * IMPORTANT:
 *
 * Professional judgment automatically perform nahi hoga.
 *
 * CRM sirf administrative/repetitive workflow actions
 * automate karega.
 *
 * =====================================================
 */


/*
=========================================================
1. CLASS DEFINITION
=========================================================

PHP class = custom reusable structure.

Is class ka purpose automation logic ko pages se
separate rakhna hai.

Iska faida:

clients.php
invoices.php
documents.php
projects.php

har file apni automation logic independently likhne
ke bajaye central engine ko call kar sakti hai.
=========================================================
*/

class AutomationEngine
{

    /*
    =====================================================
    DATABASE CONNECTION
    =====================================================

    PDO object database queries ke liye use hoga.

    PDO = PHP built-in database interface.

    Prepared statements ke through SQL injection risk
    reduce hota hai.
    */

    private PDO $pdo;


    /*
    =====================================================
    CONSTRUCTOR
    =====================================================

    __construct() = PHP class ka built-in lifecycle method.

    Jab class ka object banega:

        new AutomationEngine($pdo)

    to database connection automatically store ho jayega.
    */

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }


    /*
    =====================================================
    EVENT DISPATCHER
    =====================================================

    Ye automation engine ka main entry point hai.

    Example:

        $engine->dispatch(
            'document.approved',
            [
                'document_id' => 25,
                'case_id' => 8
            ]
        );

    Engine event ka naam dekhega aur relevant
    automation actions execute karega.
    */

    public function dispatch(
        string $eventName,
        array $payload = []
    ): array
    {

        /*
        -------------------------------------------------
        Event name empty nahi hona chahiye.
        -------------------------------------------------
        */

        $eventName = trim($eventName);


        if ($eventName === '') {

            return [
                'success' => false,
                'message' => 'Automation event is required.',
                'executed' => []
            ];
        }


        /*
        -------------------------------------------------
        Event execution list.

        Har successful automation ko yahan record
        karenge.

        Is se caller ko pata chalega ke engine ne kya
        perform kiya.
        -------------------------------------------------
        */

        $executed = [];


        /*
        -------------------------------------------------
        Registered rules load karein.

        Future production version mein ye rules database
        se aa sakte hain:

            automation_rules
            automation_conditions
            automation_actions

        Abhi method ko isolated rakha gaya hai.
        -------------------------------------------------
        */

        $rules = $this->getRulesForEvent(
            $eventName
        );


        /*
        -------------------------------------------------
        Har rule ko individually process karein.
        -------------------------------------------------
        */

        foreach ($rules as $rule) {

            /*
            -------------------------------------------------
            Rule enabled hai ya nahi?
            -------------------------------------------------
            */

            if (
                isset($rule['enabled']) &&
                !$rule['enabled']
            ) {
                continue;
            }


            /*
            -------------------------------------------------
            Conditions check karein.

            Agar condition match nahi karti to action
            execute nahi hoga.

            Example:

                invoice.overdue

            Lekin:

                balance <= 0

            to reminder nahi bhejna.
            -------------------------------------------------
            */

            if (
                !$this->conditionsMatch(
                    $rule,
                    $payload
                )
            ) {
                continue;
            }


            /*
            -------------------------------------------------
            Actions execute karein.
            -------------------------------------------------
            */

            $actions =
                $rule['actions'] ?? [];


            foreach ($actions as $action) {

                $result =
                    $this->executeAction(
                        $action,
                        $payload
                    );


                $executed[] = [
                    'rule' => $rule['name'] ?? 'Unnamed Rule',
                    'action' => $action['type'] ?? 'unknown',
                    'result' => $result
                ];
            }
        }


        /*
        -------------------------------------------------
        Final engine result.
        -------------------------------------------------
        */

        return [
            'success' => true,
            'event' => $eventName,
            'executed' => $executed
        ];
    }


    /*
    =====================================================
    GET RULES FOR EVENT
    =====================================================

    IMPORTANT BHAI:

    Ye intentionally ek separate method hai.

    Future database-driven automation system mein isi
    method ko automation_rules table se connect karenge.

    Is architecture ki wajah se baaki CRM modules ko
    change karne ki zaroorat nahi hogi.
    */

    private function getRulesForEvent(
        string $eventName
    ): array
    {

        /*
        -------------------------------------------------
        Starter automation registry.

        Ye database replacement nahi hai.

        Ye sirf architecture ko working foundation deta hai.
        -------------------------------------------------
        */

        $rules = [

            /*
            =============================================
            DOCUMENT APPROVED
            =============================================
            */

            'document.approved' => [

                [
                    'name' => 'Complete document workflow step',

                    'enabled' => true,

                    'conditions' => [],

                    'actions' => [

                        [
                            'type' =>
                                'complete_workflow_step'
                        ]

                    ]
                ]

            ],


            /*
            =============================================
            DOCUMENT REJECTED
            =============================================
            */

            'document.rejected' => [

                [
                    'name' => 'Create document correction task',

                    'enabled' => true,

                    'conditions' => [],

                    'actions' => [

                        [
                            'type' =>
                                'create_correction_task'
                        ]

                    ]
                ]

            ],


            /*
            =============================================
            INVOICE OVERDUE
            =============================================
            */

            'invoice.overdue' => [

                [
                    'name' => 'Notify responsible staff',

                    'enabled' => true,

                    'conditions' => [

                        [
                            'field' => 'balance',
                            'operator' => 'greater_than',
                            'value' => 0
                        ]

                    ],

                    'actions' => [

                        [
                            'type' =>
                                'create_payment_followup'
                        ]

                    ]
                ]

            ],


            /*
            =============================================
            SERVICE COMPLETED
            =============================================
            */

            'service.completed' => [

                [
                    'name' => 'Create client follow-up',

                    'enabled' => true,

                    'conditions' => [],

                    'actions' => [

                        [
                            'type' =>
                                'create_followup_task'
                        ]

                    ]
                ]

            ]

        ];


        return $rules[$eventName] ?? [];
    }


    /*
    =====================================================
    CONDITION ENGINE
    =====================================================

    Ye method automation ke IF part ko handle karta hai.

    Example:

        IF balance > 0
        THEN create reminder

    Multiple conditions future mein support ki ja sakti
    hain.
    */

    private function conditionsMatch(
        array $rule,
        array $payload
    ): bool
    {

        $conditions =
            $rule['conditions'] ?? [];


        /*
        -------------------------------------------------
        Agar conditions nahi hain to rule directly match.
        -------------------------------------------------
        */

        if (empty($conditions)) {
            return true;
        }


        /*
        -------------------------------------------------
        Har condition check karein.
        -------------------------------------------------
        */

        foreach ($conditions as $condition) {

            $field =
                $condition['field'] ?? '';


            $operator =
                $condition['operator'] ?? '';


            $expected =
                $condition['value'] ?? null;


            $actual =
                $payload[$field] ?? null;


            /*
            -------------------------------------------------
            Basic operators.

            Future version mein:

                contains
                starts_with
                in
                not_in
                date_before
                date_after

            bhi add kiye ja sakte hain.
            -------------------------------------------------
            */

            switch ($operator) {


                case 'equals':

                    if ($actual != $expected) {
                        return false;
                    }

                    break;


                case 'not_equals':

                    if ($actual == $expected) {
                        return false;
                    }

                    break;


                case 'greater_than':

                    if (
                        !is_numeric($actual) ||
                        $actual <= $expected
                    ) {
                        return false;
                    }

                    break;


                case 'less_than':

                    if (
                        !is_numeric($actual) ||
                        $actual >= $expected
                    ) {
                        return false;
                    }

                    break;


                case 'greater_or_equal':

                    if (
                        !is_numeric($actual) ||
                        $actual < $expected
                    ) {
                        return false;
                    }

                    break;


                case 'less_or_equal':

                    if (
                        !is_numeric($actual) ||
                        $actual > $expected
                    ) {
                        return false;
                    }

                    break;


                default:

                    /*
                    -----------------------------------------
                    Unknown condition ko automatically
                    TRUE nahi karna.

                    Security-wise safer behavior:
                    unknown condition = rule doesn't run.
                    -----------------------------------------
                    */

                    return false;
            }
        }


        return true;
    }


    /*
    =====================================================
    ACTION EXECUTOR
    =====================================================

    Ye automation ke THEN part ko handle karta hai.

    Example:

        THEN create_followup_task

    Important:
    Har action ko separate method mein bhejna hai.

    Is se engine future mein huge spaghetti code
    nahi banega.
    */

    private function executeAction(
        array $action,
        array $payload
    ): array
    {

        $type =
            $action['type'] ?? '';


        switch ($type) {


            /*
            =============================================
            COMPLETE WORKFLOW STEP
            =============================================
            */

            case 'complete_workflow_step':

                return $this->completeWorkflowStep(
                    $payload
                );


            /*
            =============================================
            CREATE CORRECTION TASK
            =============================================
            */

            case 'create_correction_task':

                return $this->createCorrectionTask(
                    $payload
                );


            /*
            =============================================
            PAYMENT FOLLOW-UP
            =============================================
            */

            case 'create_payment_followup':

                return $this->createPaymentFollowup(
                    $payload
                );


            /*
            =============================================
            SERVICE FOLLOW-UP
            =============================================
            */

            case 'create_followup_task':

                return $this->createFollowupTask(
                    $payload
                );


            /*
            =============================================
            UNKNOWN ACTION
            =============================================
            */

            default:

                /*
                Unknown automation ko silently ignore nahi
                karna.

                Result mein error return karenge taake
                monitoring system baad mein isko catch
                kar sake.
                */

                return [

                    'success' => false,

                    'message' =>
                        'Unknown automation action: '
                        . $type

                ];
        }
    }


    /*
    =====================================================
    WORKFLOW STEP COMPLETION
    =====================================================

    Document approved hone ke baad current workflow
    step complete karne ka foundation.

    NOTE:

    Exact workflow table names final database schema
    ke saath connect kiye jayenge.
    */

    private function completeWorkflowStep(
        array $payload
    ): array
    {

        $stepId =
            isset($payload['workflow_step_id'])
                ? (int) $payload['workflow_step_id']
                : 0;


        /*
        -------------------------------------------------
        Agar event mein workflow step ID nahi hai to
        database update attempt nahi karenge.
        -------------------------------------------------
        */

        if ($stepId <= 0) {

            return [

                'success' => false,

                'message' =>
                    'Workflow step ID was not supplied.'

            ];
        }


        /*
        -------------------------------------------------
        Prepared statement.

        SQL injection se protection ke liye values ko
        SQL string mein directly concatenate nahi kar rahe.
        -------------------------------------------------
        */

        $sql = "
            UPDATE workflow_steps

            SET
                status = 'completed',
                completed_at = NOW()

            WHERE id = ?

              AND status <> 'completed'
        ";


        $stmt =
            $this->pdo->prepare($sql);


        $stmt->execute([
            $stepId
        ]);


        return [

            'success' => true,

            'message' =>
                'Workflow step processed.',

            'workflow_step_id' =>
                $stepId,

            'updated_rows' =>
                $stmt->rowCount()

        ];
    }


    /*
    =====================================================
    CORRECTION TASK
    =====================================================

    Document reject hone ke baad corrected document ke
    liye task create karne ka foundation.
    */

    private function createCorrectionTask(
        array $payload
    ): array
    {

        $caseId =
            isset($payload['case_id'])
                ? (int) $payload['case_id']
                : 0;


        $documentId =
            isset($payload['document_id'])
                ? (int) $payload['document_id']
                : 0;


        if ($caseId <= 0) {

            return [

                'success' => false,

                'message' =>
                    'Case ID was not supplied.'

            ];
        }


        /*
        -------------------------------------------------
        Task description.

        Client-facing wording aur internal wording ko
        future template system handle karega.
        -------------------------------------------------
        */

        $title =
            'Correct rejected document';


        $description =
            'A document requires correction and resubmission.';


        $sql = "
            INSERT INTO tasks (

                service_case_id,
                title,
                description,
                status,
                priority,
                created_at

            ) VALUES (

                ?,
                ?,
                ?,
                'pending',
                'high',
                NOW()

            )
        ";


        $stmt =
            $this->pdo->prepare($sql);


        $stmt->execute([

            $caseId,

            $title,

            $description

        ]);


        return [

            'success' => true,

            'message' =>
                'Correction task created.',

            'task_id' =>
                (int) $this->pdo->lastInsertId(),

            'document_id' =>
                $documentId

        ];
    }


    /*
    =====================================================
    PAYMENT FOLLOW-UP
    =====================================================

    Overdue invoice ke case mein responsible workflow
    follow-up task create karna.

    Professional decision automatically nahi ho raha.
    Sirf administrative reminder create ho raha hai.
    */

    private function createPaymentFollowup(
        array $payload
    ): array
    {

        $invoiceId =
            isset($payload['invoice_id'])
                ? (int) $payload['invoice_id']
                : 0;


        $caseId =
            isset($payload['case_id'])
                ? (int) $payload['case_id']
                : 0;


        if ($invoiceId <= 0) {

            return [

                'success' => false,

                'message' =>
                    'Invoice ID was not supplied.'

            ];
        }


        $sql = "
            INSERT INTO tasks (

                service_case_id,
                title,
                description,
                status,
                priority,
                created_at

            ) VALUES (

                ?,
                ?,
                ?,
                'pending',
                'high',
                NOW()

            )
        ";


        $stmt =
            $this->pdo->prepare($sql);


        $stmt->execute([

            $caseId > 0
                ? $caseId
                : null,

            'Payment follow-up required',

            'Invoice #' .
                $invoiceId .
                ' has an outstanding balance.'

        ]);


        return [

            'success' => true,

            'message' =>
                'Payment follow-up created.',

            'task_id' =>
                (int) $this->pdo->lastInsertId()

        ];
    }


    /*
    =====================================================
    SERVICE COMPLETION FOLLOW-UP
    =====================================================

    Service complete hone ke baad:

        - client follow-up
        - feedback request
        - renewal reminder

    future automation rules ke through activate honge.

    Abhi basic internal task foundation create kar rahe hain.
    */

    private function createFollowupTask(
        array $payload
    ): array
    {

        $caseId =
            isset($payload['case_id'])
                ? (int) $payload['case_id']
                : 0;


        if ($caseId <= 0) {

            return [

                'success' => false,

                'message' =>
                    'Case ID was not supplied.'

            ];
        }


        $sql = "
            INSERT INTO tasks (

                service_case_id,
                title,
                description,
                status,
                priority,
                created_at

            ) VALUES (

                ?,
                ?,
                ?,
                'pending',
                'normal',
                NOW()

            )
        ";


        $stmt =
            $this->pdo->prepare($sql);


        $stmt->execute([

            $caseId,

            'Client completion follow-up',

            'Service has been completed. Follow up with the client and handle any post-service requirements.'

        ]);


        return [

            'success' => true,

            'message' =>
                'Service follow-up task created.',

            'task_id' =>
                (int) $this->pdo->lastInsertId()

        ];
    }
}
```
