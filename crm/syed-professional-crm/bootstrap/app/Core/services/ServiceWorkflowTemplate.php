```php
<?php
/**
 * =====================================================
 * FILE: core/services/ServiceWorkflowTemplate.php
 *
 * PURPOSE:
 * SPS ki har professional service ka reusable workflow
 * template define aur manage karna.
 *
 * WORKFLOW:
 *
 * Service
 *    ↓
 * Workflow Template
 *    ↓
 * Workflow Steps
 *    ↓
 * Required Information
 *    ↓
 * Required Documents
 *    ↓
 * Staff Actions
 *    ↓
 * Client Actions
 *    ↓
 * Automation Events
 *
 * IMPORTANT:
 *
 * Ye file actual client case ko directly change nahi karti.
 * Ye sirf "blueprint/template" provide karti hai.
 *
 * Example:
 *
 * Tax Filing Template
 * Immigration Template
 * EIN Template
 * Bookkeeping Template
 *
 * Jab client koi service purchase/start karega,
 * future Service Workflow Engine isi template ko use
 * karke actual case workflow create karega.
 *
 * SECURITY PRINCIPLE:
 *
 * Tax aur immigration mein professional judgment ko
 * automatically approve nahi kiya jayega.
 *
 * CRM:
 * - information collect karega
 * - documents track karega
 * - deadlines manage karega
 * - tasks create karega
 * - reminders bhejega
 * - approvals record karega
 *
 * Lekin final professional/legal/tax decision
 * authorized staff ke control mein rahega.
 * =====================================================
 */


/*
=========================================================
1. CLASS DEFINITION
=========================================================

Ye custom PHP class hai.

Iska purpose service workflow definitions ko ek central
place par rakhna hai.

Is se different pages mein duplicate workflow arrays
banane ki zaroorat nahi padegi.
=========================================================
*/

class ServiceWorkflowTemplate
{

    /*
    =====================================================
    SERVICE TEMPLATE REGISTRY
    =====================================================

    Private property ka matlab:

    Is class ke bahar directly templates modify nahi
    kiye ja sakte.

    Ye accidental workflow changes se protection deta hai.
    */

    private array $templates = [];


    /*
    =====================================================
    CONSTRUCTOR
    =====================================================

    __construct() = PHP built-in class lifecycle method.

    Object create hote hi templates register kiye jayenge.
    */

    public function __construct()
    {
        $this->registerDefaultTemplates();
    }


    /*
    =====================================================
    GET SERVICE TEMPLATE
    =====================================================

    $serviceKey:

        tax_filing
        immigration
        ein
        bookkeeping

    jaisi internal identifier hogi.

    Internal key ko display name se separate rakhna
    important hai.

    Example:

        tax_filing

    Display:

        Tax Filing
    */

    public function get(
        string $serviceKey
    ): ?array {

        $serviceKey = trim(
            strtolower($serviceKey)
        );


        /*
        -------------------------------------------------
        Unknown service ke liye null return.

        Is se system fake workflow assume nahi karega.
        -------------------------------------------------
        */

        return $this->templates[
            $serviceKey
        ] ?? null;
    }


    /*
    =====================================================
    GET ALL SERVICE TEMPLATES
    =====================================================

    Admin/service catalog screen future mein is method
    ko use kar sakti hai.
    */

    public function all(): array
    {
        return $this->templates;
    }


    /*
    =====================================================
    CHECK SERVICE EXISTENCE
    =====================================================
    */

    public function exists(
        string $serviceKey
    ): bool {

        $serviceKey = trim(
            strtolower($serviceKey)
        );


        return isset(
            $this->templates[$serviceKey]
        );
    }


    /*
    =====================================================
    GET WORKFLOW STEPS
    =====================================================

    Kisi service ke individual workflow steps return
    karta hai.
    */

    public function getSteps(
        string $serviceKey
    ): array {

        $template = $this->get(
            $serviceKey
        );


        if (!$template) {
            return [];
        }


        return $template['steps'] ?? [];
    }


    /*
    =====================================================
    GET REQUIRED DOCUMENTS
    =====================================================

    Ye method client intake/document request system
    ke liye useful hoga.

    IMPORTANT:

    Actual documents client ki situation ke mutabiq
    staff modify kar sakta hai.

    Template baseline provide karta hai.
    =====================================================
    */

    public function getRequiredDocuments(
        string $serviceKey
    ): array {

        $template = $this->get(
            $serviceKey
        );


        if (!$template) {
            return [];
        }


        return $template[
            'required_documents'
        ] ?? [];
    }


    /*
    =====================================================
    GET REQUIRED INFORMATION
    =====================================================

    Client intake form dynamically generate karne ke
    liye future mein ye method use hoga.
    */

    public function getRequiredInformation(
        string $serviceKey
    ): array {

        $template = $this->get(
            $serviceKey
        );


        if (!$template) {
            return [];
        }


        return $template[
            'required_information'
        ] ?? [];
    }


    /*
    =====================================================
    REGISTER DEFAULT TEMPLATES
    =====================================================

    Yahan SPS ke initial service blueprints defined hain.

    Production system mein eventually ye database-driven
    service template builder se manage honge.

    Lekin architecture intentionally same rahega:
    
        Template
            ↓
        Workflow Engine
            ↓
        Client Case
    */

    private function registerDefaultTemplates(): void
    {

        /*
        =================================================
        TAX FILING
        =================================================

        Bhai tax workflow mein important point:

        CRM tax calculation ya final professional
        judgment automatically nahi karega.

        Ye workflow administrative stages manage karega.
        */

        $this->templates['tax_filing'] = [

            'key' =>
                'tax_filing',

            'name' =>
                'Tax Filing',

            'category' =>
                'Tax Services',

            'description' =>
                'Structured workflow for tax preparation and filing services.',

            'requires_professional_review' =>
                true,

            'requires_client_approval' =>
                true,

            'sensitive_service' =>
                true,


            /*
            ---------------------------------------------
            CLIENT INFORMATION
            ---------------------------------------------
            */

            'required_information' => [

                [
                    'key' =>
                        'legal_name',

                    'label' =>
                        'Legal Name',

                    'type' =>
                        'text',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'contact_information',

                    'label' =>
                        'Contact Information',

                    'type' =>
                        'contact',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'tax_year',

                    'label' =>
                        'Tax Year',

                    'type' =>
                        'number',

                    'required' =>
                        true
                ]

            ],


            /*
            ---------------------------------------------
            DOCUMENT BASELINE
            ---------------------------------------------
            */

            'required_documents' => [

                [
                    'key' =>
                        'identity_document',

                    'name' =>
                        'Identity Document',

                    'required' =>
                        true,

                    'review_required' =>
                        true
                ],

                [
                    'key' =>
                        'income_documents',

                    'name' =>
                        'Income Documents',

                    'required' =>
                        true,

                    'review_required' =>
                        true
                ],

                [
                    'key' =>
                        'supporting_tax_documents',

                    'name' =>
                        'Supporting Tax Documents',

                    'required' =>
                        true,

                    'review_required' =>
                        true
                ]

            ],


            /*
            ---------------------------------------------
            WORKFLOW STEPS
            ---------------------------------------------
            */

            'steps' => [

                [
                    'key' =>
                        'client_intake',

                    'name' =>
                        'Client Intake',

                    'actor' =>
                        'client',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'document_collection',

                    'name' =>
                        'Document Collection',

                    'actor' =>
                        'client',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'document_review',

                    'name' =>
                        'Document Review',

                    'actor' =>
                        'staff',

                    'required' =>
                        true,

                    'professional_review' =>
                        true
                ],

                [
                    'key' =>
                        'preparation',

                    'name' =>
                        'Preparation',

                    'actor' =>
                        'authorized_staff',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'professional_review',

                    'name' =>
                        'Professional Review',

                    'actor' =>
                        'authorized_professional',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'client_approval',

                    'name' =>
                        'Client Approval',

                    'actor' =>
                        'client',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'filing',

                    'name' =>
                        'Filing',

                    'actor' =>
                        'authorized_staff',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'completion',

                    'name' =>
                        'Service Completion',

                    'actor' =>
                        'system',

                    'required' =>
                        true
                ]

            ],


            /*
            ---------------------------------------------
            AUTOMATION EVENTS
            ---------------------------------------------
            */

            'automation_events' => [

                'case_created',

                'documents_missing',

                'document_uploaded',

                'document_rejected',

                'documents_complete',

                'professional_review_required',

                'client_approval_required',

                'filing_ready',

                'filing_completed',

                'service_completed'

            ]

        ];


        /*
        =================================================
        IMMIGRATION SERVICE
        =================================================

        Bhai immigration ko intentionally flexible rakha
        gaya hai.

        Immigration services ke requirements case type,
        applicant circumstances aur professional assessment
        ke mutabiq vary kar sakte hain.

        Isliye template baseline hai, rigid legal decision
        engine nahi.
        */

        $this->templates['immigration'] = [

            'key' =>
                'immigration',

            'name' =>
                'Immigration Services',

            'category' =>
                'Immigration',

            'description' =>
                'Case-based workflow for immigration service administration.',

            'requires_professional_review' =>
                true,

            'requires_client_approval' =>
                true,

            'sensitive_service' =>
                true,


            /*
            ---------------------------------------------
            CLIENT INTAKE
            ---------------------------------------------
            */

            'required_information' => [

                [
                    'key' =>
                        'legal_name',

                    'label' =>
                        'Legal Name',

                    'type' =>
                        'text',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'date_of_birth',

                    'label' =>
                        'Date of Birth',

                    'type' =>
                        'date',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'country_of_citizenship',

                    'label' =>
                        'Country of Citizenship',

                    'type' =>
                        'country',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'case_type',

                    'label' =>
                        'Immigration Case Type',

                    'type' =>
                        'select',

                    'required' =>
                        true,

                    /*
                    -------------------------------------
                    IMPORTANT:

                    Ye choices workflow routing ke liye
                    hain.

                    Ye legal eligibility conclusion nahi hain.
                    -------------------------------------
                    */

                    'options' => [

                        'family_based',

                        'employment_based',

                        'student',

                        'visitor',

                        'other'

                    ]

                ]

            ],


            /*
            ---------------------------------------------
            DOCUMENT BASELINE
            ---------------------------------------------
            */

            'required_documents' => [

                [
                    'key' =>
                        'passport',

                    'name' =>
                        'Passport',

                    'required' =>
                        true,

                    'review_required' =>
                        true
                ],

                [
                    'key' =>
                        'identity_documents',

                    'name' =>
                        'Identity Documents',

                    'required' =>
                        true,

                    'review_required' =>
                        true
                ],

                [
                    'key' =>
                        'case_supporting_documents',

                    'name' =>
                        'Case Supporting Documents',

                    'required' =>
                        true,

                    'review_required' =>
                        true
                ]

            ],


            /*
            ---------------------------------------------
            WORKFLOW
            ---------------------------------------------
            */

            'steps' => [

                [
                    'key' =>
                        'client_intake',

                    'name' =>
                        'Client Intake',

                    'actor' =>
                        'client',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'case_classification',

                    'name' =>
                        'Case Classification',

                    'actor' =>
                        'authorized_staff',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'document_collection',

                    'name' =>
                        'Document Collection',

                    'actor' =>
                        'client',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'document_review',

                    'name' =>
                        'Document Review',

                    'actor' =>
                        'staff',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'professional_assessment',

                    'name' =>
                        'Professional Assessment',

                    'actor' =>
                        'authorized_professional',

                    'required' =>
                        true,

                    /*
                    -------------------------------------
                    CRM yahan decision nahi karega.
                    -------------------------------------
                    */

                    'human_approval_required' =>
                        true
                ],

                [
                    'key' =>
                        'case_preparation',

                    'name' =>
                        'Case Preparation',

                    'actor' =>
                        'authorized_staff',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'client_review',

                    'name' =>
                        'Client Review',

                    'actor' =>
                        'client',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'authorized_submission',

                    'name' =>
                        'Authorized Submission',

                    'actor' =>
                        'authorized_staff',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'case_monitoring',

                    'name' =>
                        'Case Monitoring',

                    'actor' =>
                        'staff',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'case_completion',

                    'name' =>
                        'Case Completion',

                    'actor' =>
                        'authorized_staff',

                    'required' =>
                        true
                ]

            ],


            /*
            ---------------------------------------------
            AUTOMATION EVENTS
            ---------------------------------------------
            */

            'automation_events' => [

                'case_created',

                'intake_completed',

                'documents_missing',

                'document_uploaded',

                'document_rejected',

                'documents_complete',

                'professional_review_required',

                'client_review_required',

                'submission_ready',

                'submission_recorded',

                'case_milestone_reached',

                'case_completed'

            ]

        ];


        /*
        =================================================
        EIN SERVICE
        =================================================

        EIN relatively structured administrative service
        ho sakti hai, lekin staff verification phir bhi
        workflow ka part rahegi.
        */

        $this->templates['ein'] = [

            'key' =>
                'ein',

            'name' =>
                'EIN Registration',

            'category' =>
                'Business Services',

            'description' =>
                'Structured EIN registration workflow.',

            'requires_professional_review' =>
                true,

            'requires_client_approval' =>
                true,

            'sensitive_service' =>
                true,


            'required_information' => [

                [
                    'key' =>
                        'business_legal_name',

                    'label' =>
                        'Business Legal Name',

                    'type' =>
                        'text',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'business_structure',

                    'label' =>
                        'Business Structure',

                    'type' =>
                        'select',

                    'required' =>
                        true
                ]

            ],


            'required_documents' => [

                [
                    'key' =>
                        'business_documents',

                    'name' =>
                        'Business Documents',

                    'required' =>
                        true,

                    'review_required' =>
                        true
                ]

            ],


            'steps' => [

                [
                    'key' =>
                        'client_information',

                    'name' =>
                        'Client Information',

                    'actor' =>
                        'client',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'document_review',

                    'name' =>
                        'Document Review',

                    'actor' =>
                        'staff',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'professional_review',

                    'name' =>
                        'Professional Review',

                    'actor' =>
                        'authorized_professional',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'application',

                    'name' =>
                        'Application',

                    'actor' =>
                        'authorized_staff',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'confirmation',

                    'name' =>
                        'Confirmation',

                    'actor' =>
                        'staff',

                    'required' =>
                        true
                ],

                [
                    'key' =>
                        'completion',

                    'name' =>
                        'Service Completion',

                    'actor' =>
                        'system',

                    'required' =>
                        true
                ]

            ],


            'automation_events' => [

                'case_created',

                'documents_missing',

                'documents_complete',

                'professional_review_required',

                'application_ready',

                'application_completed',

                'confirmation_uploaded',

                'service_completed'

            ]

        ];

    }
}
```
