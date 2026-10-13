<?php
/**
 * =====================================================
 * FILE: service-intake.php
 * PURPOSE: Service start karne se pehle client ko
 *          guided intake aur important warnings dikhana.
 *
 * WORKFLOW:
 *
 * Services
 *    ↓
 * Select Service
 *    ↓
 * Select Client
 *    ↓
 * Service Requirements
 *    ↓
 * Important Warnings
 *    ↓
 * User Confirmation
 *    ↓
 * Start Service Workflow
 *
 * NOTE:
 * Abhi ye frontend/demo workflow hai.
 * Real client/service data baad mein MySQL se aayega.
 * =====================================================
 */

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Bootstrap
|--------------------------------------------------------------------------
|
| require_once = PHP built-in.
| Isse application ke shared systems load hote hain.
|
*/
$container = require_once dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Url;


/*
|--------------------------------------------------------------------------
| Temporary selected service
|--------------------------------------------------------------------------
|
| Abhi URL se service code read kar rahe hain.
|
| Example:
| service-intake.php?service=TAX
|
| Future mein service database se load hogi.
|
*/
$serviceCode = strtoupper(
    $_GET['service'] ?? 'TAX'
);


/*
|--------------------------------------------------------------------------
| Temporary service definitions
|--------------------------------------------------------------------------
|
| Ye definitions baad mein database ke service catalog
| se replace hongi.
|
*/
$serviceCatalog = [

    'TAX' => [

        'name' => 'Tax Filing',

        'category' => 'Tax Services',

        'description' =>
            'Guided tax preparation and filing workflow.',

        'warning' =>
            'Please make sure all information and documents '
            . 'provided are accurate and complete.',

        'requirements' => [

            'Client identity information',

            'Tax-year information',

            'Income documents',

            'Applicable deduction documents',

            'Additional supporting documents when required',

        ],

        'steps' => [

            'Client information',

            'Document collection',

            'Information review',

            'Preparation',

            'Client review',

            'Filing',

            'Completion',

        ],

    ],

    'IMM' => [

        'name' => 'Immigration Services',

        'category' => 'Immigration',

        'description' =>
            'Guided immigration case preparation and '
            . 'document workflow.',

        'warning' =>
            'Immigration matters may involve legal or '
            . 'government requirements. The CRM can organize '
            . 'the process but does not replace professional '
            . 'judgment or legal review.',

        'requirements' => [

            'Client identity information',

            'Case-specific information',

            'Required identity documents',

            'Supporting documents',

            'Case-specific forms',

            'Additional documents when required',

        ],

        'steps' => [

            'Client intake',

            'Case assessment',

            'Document collection',

            'Document review',

            'Preparation',

            'Professional review',

            'Submission / filing',

            'Case tracking',

            'Completion',

        ],

    ],

    'BOOK' => [

        'name' => 'Bookkeeping',

        'category' => 'Accounting',

        'description' =>
            'Recurring bookkeeping and financial record workflow.',

        'warning' =>
            'Financial records should be reviewed for '
            . 'completeness and accuracy before processing.',

        'requirements' => [

            'Client information',

            'Accounting period',

            'Bank statements',

            'Transaction records',

            'Supporting financial documents',

        ],

        'steps' => [

            'Client intake',

            'Document collection',

            'Transaction processing',

            'Reconciliation',

            'Review',

            'Reporting',

            'Completion',

        ],

    ],

];


/*
|--------------------------------------------------------------------------
| Validate requested service
|--------------------------------------------------------------------------
|
| array_key_exists() = PHP built-in.
|
| Agar invalid service URL mein aaye to CRM default
| Tax service par silently depend nahi karega.
| Hum safe default use kar rahe hain.
|
*/
if (!array_key_exists($serviceCode, $serviceCatalog)) {

    $serviceCode = 'TAX';

}


/*
|--------------------------------------------------------------------------
| Load selected service
|--------------------------------------------------------------------------
*/

$service = $serviceCatalog[$serviceCode];

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
        Start <?= htmlspecialchars($service['name']) ?>
        - SPS CRM
    </title>

    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(
            Url::asset('css/app.css')
        ) ?>"
    >

</head>


<body>

<div class="crm-shell">


    <!-- =================================================
         SIDEBAR
         ================================================= -->

    <aside class="sidebar">

        <div class="brand">

            <div class="brand-mark">
                SPS
            </div>

            <div>

                <strong>
                    Syed Professional
                </strong>

                <span>
                    Services CRM
                </span>

            </div>

        </div>


        <div class="sidebar-section">

            <span class="sidebar-label">
                MAIN
            </span>

            <a
                href="<?= Url::to('') ?>"
                class="nav-item"
            >
                <span>▣</span>
                Dashboard
            </a>


            <a
                href="<?= Url::to('clients.php') ?>"
                class="nav-item"
            >
                <span>👥</span>
                Clients
            </a>


            <a
                href="#"
                class="nav-item"
            >
                <span>📁</span>
                Cases
            </a>


            <a
                href="<?= Url::to('services.php') ?>"
                class="nav-item active"
            >
                <span>⚙</span>
                Services
            </a>

        </div>


        <div class="sidebar-section">

            <span class="sidebar-label">
                OPERATIONS
            </span>

            <a href="#" class="nav-item">
                <span>✓</span>
                Tasks
            </a>

            <a href="#" class="nav-item">
                <span>📄</span>
                Documents
            </a>

            <a href="#" class="nav-item">
                <span>💳</span>
                Billing
            </a>

            <a href="#" class="nav-item">
                <span>🔔</span>
                Notifications
            </a>

        </div>


        <div class="sidebar-section">

            <span class="sidebar-label">
                MANAGEMENT
            </span>

            <a href="#" class="nav-item">
                <span>📊</span>
                Reports
            </a>

            <a href="#" class="nav-item">
                <span>🤖</span>
                Automation
            </a>

            <a href="#" class="nav-item">
                <span>🔐</span>
                Administration
            </a>

        </div>


        <div class="sidebar-bottom">

            <div class="system-status">

                <span class="status-dot"></span>

                <div>

                    <strong>
                        System Online
                    </strong>

                    <small>
                        SPS CRM
                    </small>

                </div>

            </div>

        </div>

    </aside>


    <!-- =================================================
         MAIN CONTENT
         ================================================= -->

    <main class="main-content">


        <header class="topbar">

            <div>

                <span class="eyebrow">
                    SERVICE INTAKE
                </span>

                <h1>
                    Start Service
                </h1>

            </div>


            <div class="topbar-actions">

                <button class="icon-button">
                    🔔
                </button>


                <div class="user-profile">

                    <div class="avatar">
                        AT
                    </div>

                    <div>

                        <strong>
                            Administrator
                        </strong>

                        <small>
                            System Admin
                        </small>

                    </div>

                </div>

            </div>

        </header>


        <section class="dashboard-body">


            <!-- =================================================
                 BREADCRUMB
                 ================================================= -->

            <div class="intake-breadcrumb">

                <a href="<?= Url::to('services.php') ?>">
                    Services
                </a>

                <span>
                    /
                </span>

                <strong>
                    <?= htmlspecialchars($service['name']) ?>
                </strong>

            </div>


            <!-- =================================================
                 SERVICE HEADER
                 ================================================= -->

            <div class="intake-header">

                <div>

                    <span class="eyebrow">
                        <?= htmlspecialchars(
                            $service['category']
                        ) ?>
                    </span>

                    <h2>
                        <?= htmlspecialchars(
                            $service['name']
                        ) ?>
                    </h2>

                    <p>
                        <?= htmlspecialchars(
                            $service['description']
                        ) ?>
                    </p>

                </div>


                <div class="service-code-large">

                    <?= htmlspecialchars(
                        $serviceCode
                    ) ?>

                </div>

            </div>


            <!-- =================================================
                 WARNING
                 ================================================= -->

            <div class="critical-warning">

                <div class="warning-symbol">
                    !
                </div>


                <div>

                    <strong>
                        Before You Continue
                    </strong>

                    <p>
                        <?= htmlspecialchars(
                            $service['warning']
                        ) ?>
                    </p>

                </div>

            </div>


            <div class="intake-grid">


                <!-- =================================================
                     REQUIRED INFORMATION
                     ================================================= -->

                <div class="intake-card">

                    <div class="intake-card-header">

                        <span class="intake-icon">
                            ✓
                        </span>

                        <div>

                            <h3>
                                Required Information
                            </h3>

                            <small>
                                Information needed to begin
                            </small>

                        </div>

                    </div>


                    <ul class="requirement-list">

                    <?php foreach (
                        $service['requirements']
                        as $requirement
                    ): ?>

                        <li>

                            <span>
                                ✓
                            </span>

                            <?= htmlspecialchars(
                                $requirement
                            ) ?>

                        </li>

                    <?php endforeach; ?>

                    </ul>

                </div>


                <!-- =================================================
                     WORKFLOW PREVIEW
                     ================================================= -->

                <div class="intake-card">

                    <div class="intake-card-header">

                        <span class="intake-icon">
                            →
                        </span>

                        <div>

                            <h3>
                                Service Workflow
                            </h3>

                            <small>
                                What happens after starting
                            </small>

                        </div>

                    </div>


                    <div class="mini-workflow">

                    <?php foreach (
                        $service['steps']
                        as $index => $step
                    ): ?>

                        <div class="mini-workflow-step">

                            <span>
                                <?= str_pad(
                                    (string)($index + 1),
                                    2,
                                    '0',
                                    STR_PAD_LEFT
                                ) ?>
                            </span>

                            <strong>
                                <?= htmlspecialchars($step) ?>
                            </strong>

                        </div>

                    <?php endforeach; ?>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 CONFIRMATION
                 ================================================= -->

            <div class="confirmation-card">

                <div>

                    <h3>
                        Ready to start?
                    </h3>

                    <p>
                        CRM will create the service workflow
                        after confirmation. Required tasks,
                        documents and review stages can then
                        be tracked from the case.
                    </p>

                </div>


                <div class="confirmation-actions">

                    <a
                        href="<?= Url::to(
                            'services.php'
                        ) ?>"
                        class="secondary-button"
                    >
                        Go Back
                    </a>


                    <a
                        href="<?= Url::to(
                            'service-workflow.php'
                        ) ?>?service=<?= urlencode(
                            $serviceCode
                        ) ?>"
                        class="primary-button"
                    >
                        Confirm & Start
                    </a>

                </div>

            </div>


        </section>

    </main>

</div>

</body>

</html>