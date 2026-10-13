 ```php
<?php
/**
 * =====================================================
 * FILE: core/services/ServiceWorkflowBuilder.php
 *
 * PURPOSE:
 * ServiceWorkflowTemplate ko use karke actual client
 * service ka workflow create karna.
 *
 * WORKFLOW:
 *
 * Client
 *   ↓
 * Service Selected
 *   ↓
 * Template Load
 *   ↓
 * Service Case Create
 *   ↓
 * Workflow Steps Create
 *   ↓
 * First Step Activate
 *   ↓
 * Automation Engine
 *
 * IMPORTANT:
 *
 * Template = blueprint
 * Case      = actual client's service
 *
 * Example:
 *
 * "Tax Filing" template ek blueprint hai.
 *
 * Jab Client A tax service purchase kare:
 *
 * Tax Filing Template
 *        ↓
 * Client A Tax Case
 *        ↓
 * Client A ke individual workflow steps
 *
 * SECURITY:
 *
 * Tax aur immigration workflows mein professional
 * approval ko system automatically bypass nahi karega.
 *
 * CRM administrative workflow ko automate karega,
 * professional judgment ko nahi.
 * =====================================================
 */


/*
=========================================================
SERVICE WORKFLOW TEMPLATE LOAD
=========================================================

require_once = PHP built-in include function.

Why:
ServiceWorkflowTemplate class ko is file mein available
karna zaroori hai.

Agar class load na ho to PHP fatal error de sakta hai.
=========================================================
*/

require_once __DIR__ . '/ServiceWorkflowTemplate.php';


/**
 * =====================================================
 * CLASS: ServiceWorkflowBuilder
 *
 * PURPOSE:
 * Service template se actual client workflow generate
 * karna.
 * =====================================================
 */

class ServiceWorkflowBuilder
{

    /*
    =====================================================
    DATABASE CONNECTION
    =====================================================

    PDO = PHP built-in database interface.

    Database operations ke liye prepared statements
    use kiye jayenge.
    */

    private PDO $pdo;


    /*
    =====================================================
    TEMPLATE ENGINE
    =====================================================

    Ye object service templates provide karega.
    */

    private ServiceWorkflowTemplate $templates;


    /*
    =====================================================
    CONSTRUCTOR
    =====================================================

    Object create hote waqt:

    - database connection
    - workflow template engine

    dono available ho jayenge.
    */

    public function __construct(
        PDO $pdo,
        ?ServiceWorkflowTemplate $templates = null
    ) {

        $this->pdo = $pdo;

        /*
        -------------------------------------------------
        Agar template engine externally nahi diya gaya,
        to naya template engine create kar dein.
        -------------------------------------------------
        */

        $this->templates =
            $templates
            ?? new ServiceWorkflowTemplate();
    }


    /*
    =====================================================
    BUILD SERVICE WORKFLOW
    =====================================================

    Ye class ka main method hai.

    Example:

        build(
            'tax_filing',
            125,
            4
        );

    Meaning:

        tax_filing
        client_id = 125
        created_by = staff user 4

    Ye method:

        1. template verify karega
        2. transaction start karega
        3. service case create karega
        4. workflow steps create karega
        5. first step activate karega
        6. transaction commit karega
    */

    public function build(
        string $serviceKey,
        int $clientId,
        ?int $createdBy = null
    ): array {

        /*
        -------------------------------------------------
        Basic input validation.
        -------------------------------------------------
        */

        $serviceKey =
            trim(
                strtolower($serviceKey)
            );


        if ($serviceKey === '') {

            return [

                'success' => false,

                'message' =>
                    'Service key is required.'

            ];
        }


        if ($clientId <= 0) {

            return [

                'success' => false,

                'message' =>
                    'Valid client ID is required.'

            ];
        }


        /*
        -------------------------------------------------
        Service template load karein.
        -------------------------------------------------
        */

        $template =
            $this->templates->get(
                $serviceKey
            );


        /*
        -------------------------------------------------
        Unknown service ko case create karne ki
        permission nahi deni.

        Ye important security behavior hai.

        Unknown service =
        No workflow creation.
        -------------------------------------------------
        */

        if (!$template) {

            return [

                'success' => false,

                'message' =>
                    'Service workflow template not found.'

            ];
        }


        /*
        =================================================
        TRANSACTION START
        =================================================

        PDO::beginTransaction() = PDO ka built-in method.

        Why:

        Case create ho gaya lekin steps create na hue
        to database incomplete state mein chala jayega.

        Transaction ensure karta hai:

            ALL SUCCESS
                 OR
            ALL ROLLBACK
        */

        try {

            $this->pdo->beginTransaction();


            /*
            -------------------------------------------------
            SERVICE CASE CREATE
            -------------------------------------------------

            Actual client service case create hota hai.
            -------------------------------------------------
            */

            $caseId =
                $this->createServiceCase(
                    $serviceKey,
                    $template,
                    $clientId,
                    $createdBy
                );


            /*
            -------------------------------------------------
            WORKFLOW STEPS CREATE
            -------------------------------------------------

            Template ke har step ka actual database record
            create hoga.
            -------------------------------------------------
            */

            $steps =
                $this->createWorkflowSteps(
                    $caseId,
                    $template['steps'] ?? []
                );


            /*
            -------------------------------------------------
            FIRST STEP ACTIVATE
            -------------------------------------------------

            Har workflow ka first step automatically
            "active" ho jayega.

            Baaki steps pending rahenge.
            -------------------------------------------------
            */

            $this->activateFirstStep(
                $steps
            );


            /*
            -------------------------------------------------
            TRANSACTION COMMIT
            -------------------------------------------------

            commit() = PDO built-in method.

            Is point par database changes permanently
            save ho jayenge.
            -------------------------------------------------
            */

            $this->pdo->commit();


            /*
            -------------------------------------------------
            Successful response.
            -------------------------------------------------
            */

            return [

                'success' => true,

                'message' =>
                    'Service workflow created successfully.',

                'case_id' =>
                    $caseId,

                'service_key' =>
                    $serviceKey,

                'steps_created' =>
                    count($steps)

            ];

        } catch (
            Throwable $exception
        ) {

            /*
            =================================================
            ERROR / ROLLBACK
            =================================================

            Throwable = PHP built-in interface hierarchy.

            Agar kisi bhi database operation mein error aaye:

                Case rollback
                Steps rollback

            Is se half-created workflow nahi banta.
            */

            if (
                $this->pdo->inTransaction()
            ) {

                $this->pdo->rollBack();
            }


            /*
            -------------------------------------------------
            Error ko caller ke liye safe format mein return.

            Production mein detailed exception user ko
            directly nahi dikhani.

            Detailed error internal logging system mein
            save karna chahiye.
            -------------------------------------------------
            */

            return [

                'success' => false,

                'message' =>
                    'Unable to create service workflow.',

                'error_code' =>
                    'SERVICE_WORKFLOW_CREATE_FAILED'

            ];
        }
    }


    /*
    =====================================================
    CREATE SERVICE CASE
    =====================================================

    Actual client service ka parent record create karta hai.

    IMPORTANT:

    Table structure ko hamari final database migration
    ke saath align rakha jayega.

    Is method ko isolated rakhne ka faida ye hai ke
    future schema change hone par poori class rewrite
    nahi karni padegi.
    */

    private function createServiceCase(
        string $serviceKey,
        array $template,
        int $clientId,
        ?int $createdBy
    ): int {

        /*
        -------------------------------------------------
        Service display name safely obtain karein.
        -------------------------------------------------
        */

        $serviceName =
            $template['name']
            ?? $serviceKey;


        /*
        -------------------------------------------------
        Prepared SQL.

        Values directly SQL string mein concatenate
        nahi ki ja rahi.

        Ye SQL injection risk reduce karta hai.
        -------------------------------------------------
        */

        $sql = "
            INSERT INTO service_cases
            (
                client_id,
                service_key,
                service_name,
                status,
                current_step,
                created_by,
                created_at,
                updated_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                'open',
                1,
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

            $clientId,

            $serviceKey,

            $serviceName,

            $createdBy

        ]);


        /*
        -------------------------------------------------
        lastInsertId() = PDO built-in method.

        Ye newly created service case ka database ID
        return karta hai.
        -------------------------------------------------
        */

        return (int)
            $this->pdo->lastInsertId();
    }


    /*
    =====================================================
    CREATE WORKFLOW STEPS
    =====================================================

    Template ke steps ko actual workflow records mein
    convert karta hai.
    */

    private function createWorkflowSteps(
        int $caseId,
        array $templateSteps
    ): array {

        /*
        -------------------------------------------------
        Agar template mein koi step nahi hai to workflow
        incomplete hai.

        Empty array return karenge.
        -------------------------------------------------
        */

        if (
            empty($templateSteps)
        ) {

            throw new RuntimeException(
                'Service workflow contains no steps.'
            );
        }


        /*
        -------------------------------------------------
        Prepared INSERT query.

        Same prepared statement multiple steps ke liye
        reuse hoga.

        Ye efficient bhi hai aur secure bhi.
        -------------------------------------------------
        */

        $sql = "
            INSERT INTO workflow_steps
            (
                service_case_id,
                step_key,
                step_name,
                step_order,
                actor_type,
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


        $createdSteps = [];


        /*
        =================================================
        STEP LOOP
        =================================================

        foreach = PHP built-in loop.

        Har template step ko ek actual database step
        mein convert karega.
        */

        foreach (
            $templateSteps
            as $index => $step
        ) {

            /*
            -------------------------------------------------
            Safe values.
            -------------------------------------------------
            */

            $stepKey =
                trim(
                    (string)
                    (
                        $step['key']
                        ?? ''
                    )
                );


            $stepName =
                trim(
                    (string)
                    (
                        $step['name']
                        ?? ''
                    )
                );


            $actor =
                trim(
                    (string)
                    (
                        $step['actor']
                        ?? 'staff'
                    )
                );


            /*
            -------------------------------------------------
            Invalid step ko silently insert nahi karna.
            -------------------------------------------------
            */

            if (
                $stepKey === '' ||
                $stepName === ''
            ) {

                throw new RuntimeException(
                    'Invalid workflow step definition.'
                );
            }


            /*
            -------------------------------------------------
            Step order:

            Array index 0 se start hota hai,
            lekin workflow order 1 se start karenge.
            -------------------------------------------------
            */

            $stepOrder =
                $index + 1;


            /*
            -------------------------------------------------
            Required flag.

            Empty/missing value ko default true rakha gaya
            hai kyun ke service workflow mein accidental
            optional step create nahi karna.
            -------------------------------------------------
            */

            $required =
                array_key_exists(
                    'required',
                    $step
                )
                ? (
                    $step['required']
                        ? 1
                        : 0
                )
                : 1;


            /*
            -------------------------------------------------
            Database insert.
            -------------------------------------------------
            */

            $stmt->execute([

                $caseId,

                $stepKey,

                $stepName,

                $stepOrder,

                $actor,

                $required

            ]);


            /*
            -------------------------------------------------
            Newly created step ID save karein.

            Ye later first step activate karne aur
            automation events ke liye useful hoga.
            -------------------------------------------------
            */

            $createdSteps[] = [

                'id' =>
                    (int)
                    $this->pdo->lastInsertId(),

                'step_key' =>
                    $stepKey,

                'step_name' =>
                    $stepName,

                'step_order' =>
                    $stepOrder,

                'actor' =>
                    $actor

            ];
        }


        return $createdSteps;
    }


    /*
    =====================================================
    ACTIVATE FIRST STEP
    =====================================================

    Workflow creation ke baad first step active hoga.

    Example:

        Client Intake = active
        Document Collection = pending
        Review = pending

    Is se CRM ko clear current position milti hai.
    */

    private function activateFirstStep(
        array $steps
    ): void {

        if (
            empty($steps)
        ) {

            throw new RuntimeException(
                'Cannot activate an empty workflow.'
            );
        }


        /*
        -------------------------------------------------
        First step already array ke first position par
        hai kyun ke createWorkflowSteps() ordered list
        return karta hai.
        -------------------------------------------------
        */

        $firstStep =
            $steps[0];


        $stepId =
            (int)
            $firstStep['id'];


        /*
        -------------------------------------------------
        Prepared UPDATE.

        Sirf newly-created workflow ka specific step
        update kar rahe hain.
        -------------------------------------------------
        */

        $sql = "
            UPDATE workflow_steps

            SET
                status = 'active',
                started_at = NOW(),
                updated_at = NOW()

            WHERE id = ?

              AND status = 'pending'
        ";


        $stmt =
            $this->pdo->prepare(
                $sql
            );


        $stmt->execute([
            $stepId
        ]);
    }


    /*
    =====================================================
    PREVIEW WORKFLOW
    =====================================================

    Ye method database mein kuch save nahi karta.

    UI user ko "Start Service" press karne se pehle
    workflow preview dikha sakti hai.

    Example:

        Tax Filing

        ✓ Client Intake
        ✓ Document Collection
        → Document Review
        ○ Professional Review
        ○ Client Approval
        ○ Filing

    Ye exactly tumhare friendly CRM requirement ke liye
    important hai.
    */

    public function preview(
        string $serviceKey
    ): ?array {

        $template =
            $this->templates->get(
                $serviceKey
            );


        if (!$template) {
            return null;
        }


        return [

            'service_key' =>
                $template['key'],

            'service_name' =>
                $template['name'],

            'description' =>
                $template['description'] ?? '',

            'professional_review_required' =>
                (bool)
                (
                    $template[
                        'requires_professional_review'
                    ]
                    ?? false
                ),

            'client_approval_required' =>
                (bool)
                (
                    $template[
                        'requires_client_approval'
                    ]
                    ?? false
                ),

            'required_information' =>
                $template[
                    'required_information'
                ] ?? [],

            'required_documents' =>
                $template[
                    'required_documents'
                ] ?? [],

            'steps' =>
                $template['steps'] ?? []

        ];
    }
}
```
