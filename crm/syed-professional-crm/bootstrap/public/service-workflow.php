<?php
/**
 * =====================================================
 * FILE: service-workflow.php
 * PURPOSE: Selected service ka guided workflow show karna.
 *
 * WORKFLOW:
 *
 * Service Intake
 *      ↓
 * Confirm & Start
 *      ↓
 * Workflow Created
 *      ↓
 * Tasks
 *      ↓
 * Documents
 *      ↓
 * Review
 *      ↓
 * Completion
 *
 * IMPORTANT:
 * Abhi workflow demo/static hai.
 * Future mein workflow database aur automation engine
 * se dynamically generate hoga.
 * =====================================================
 */

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| Bootstrap
|--------------------------------------------------------------------------
|
| Application ke shared systems load karte hain.
|
*/
$container = require_once dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Url;


/*
|--------------------------------------------------------------------------
| Selected service
|--------------------------------------------------------------------------
*/

$serviceCode = strtoupper(
    $_GET['service'] ?? 'TAX'
);


/*
|--------------------------------------------------------------------------
| Temporary workflow definitions
|--------------------------------------------------------------------------
*/

$workflows = [

    'TAX' => [

        'name' => 'Tax Filing',

        'steps' => [

            [
                'title' => 'Client Information',
                'description' =>
                    'Confirm client profile and required tax information.',
                'status' => 'Current',
            ],

            [
                'title' => 'Document Collection',
                'description' =>
                    'Collect and track required tax documents.',
                'status' => 'Pending',
            ],

            [
                'title' => 'Information Review',
                'description' =>
                    'Review submitted information and documents.',
                'status' => 'Pending',
            ],

            [
                'title' => 'Preparation',
                'description' =>
                    'Prepare the service according to the case requirements.',
                'status' => 'Pending',
            ],

            [
                'title' => 'Client Review',
                'description' =>
                    'Client review and required confirmation.',
                'status' => 'Pending',
            ],

            [
                'title' => 'Filing',
                'description' =>
                    'Final professional review and filing stage.',
                'status' => 'Pending',
            ],

            [
                'title' => 'Completed',
                'description' =>
                    'Close the workflow and record completion.',
                'status' => 'Pending',
            ],

        ],

    ],

    'IMM' => [

        'name' => 'Immigration Services',

        'steps' => [

            [
                'title' => 'Client Intake',
                'description' =>
                    'Collect initial client and case information.',
                'status' => 'Current',
            ],

            [
                'title' => 'Case Assessment',
                'description' =>
                    'Professional review of case requirements.',
                'status' => 'Pending',
            ],

            [
                'title' => 'Document Collection',
                'description' =>
                    'Collect and track required documentation.',
                'status' => 'Pending',
            ],

            [
                'title' => 'Document Review',
                'description' =>
                    'Review documents for completeness.',
                'status' => 'Pending',
            ],

            [
                'title' => 'Preparation',
                'description' =>
                    'Prepare the case according to the approved workflow.',
                'status' => 'Pending',
            ],

            [
                'title' => 'Professional Review',
                'description' =>
                    'Final professional review before submission.',
                'status' => 'Pending',
            ],

            [
                'title' => 'Submission / Filing',
                'description' =>
                    'Authorized filing/submission stage.',
                'status' => 'Pending',
            ],

            [
                'title' => 'Case Tracking',
                'description' =>
                    'Track the case after submission.',
                'status' => 'Pending',
            ],

        ],

    ],

];


/*
|--------------------------------------------------------------------------
| Unknown workflow protection
|--------------------------------------------------------------------------
*/

if (!isset($workflows[$serviceCode])) {

    $serviceCode = 'TAX';

}


/*
|--------------------------------------------------------------------------
| Current workflow
|--------------------------------------------------------------------------
*/

$workflow = $workflows[$serviceCode];

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
        <?= htmlspecialchars($workflow['name']) ?>
        Workflow - SPS CRM
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

            <a href="<?= Url::to('') ?>" class="nav-item">
                <span>▣</span>
                Dashboard
            </a>

            <a href="<?= Url::to('clients.php') ?>" class="nav-item">
                <span>👥</span>
                Clients
            </a>

            <a href="#" class="nav-item">
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
         MAIN
         ================================================= -->

    <main class="main-content">


        <header class="topbar">

            <div>

                <span class="eyebrow">
                    ACTIVE WORKFLOW
                </span>

                <h1>
                    <?= htmlspecialchars(
                        $workflow['name']
                    ) ?>
                </h1>

            </div>


            <div class="topbar-actions">

                <div class="workflow-status">
                    Workflow Active
                </div>

                <div class="avatar">
                    AT
                </div>

            </div>

        </header>


        <section class="dashboard-body">


            <div class="workflow-summary">

                <div>

                    <span class="eyebrow">
                        SERVICE CASE
                    </span>

                    <h2>
                        Guided Service Workflow
                    </h2>

                    <p>
                        CRM ab service ko defined stages mein
                        track karega. Har stage complete hone
                        ke baad next action identify kiya jayega.
                    </p>

                </div>


                <div class="workflow-progress">

                    <strong>
                        1 / <?= count($workflow['steps']) ?>
                    </strong>

                    <small>
                        Current stage
                    </small>

                </div>

            </div>


            <!-- =================================================
                 WORKFLOW STEPS
                 ================================================= -->

            <div class="workflow-container">

            <?php foreach (
                $workflow['steps']
                as $index => $step
            ): ?>

                <div class="workflow-row">


                    <div class="workflow-number
                        <?= $step['status'] === 'Current'
                            ? 'current'
                            : ''
                        ?>"
                    >

                        <?= $index + 1 ?>

                    </div>


                    <div class="workflow-content">

                        <div>

                            <span class="workflow-step-label">
                                STEP <?= $index + 1 ?>
                            </span>

                            <h3>
                                <?= htmlspecialchars(
                                    $step['title']
                                ) ?>
                            </h3>

                            <p>
                                <?= htmlspecialchars(
                                    $step['description']
                                ) ?>
                            </p>

                        </div>


                        <div>

                            <?php if (
                                $step['status'] === 'Current'
                            ): ?>

                                <span class="workflow-current">
                                    CURRENT
                                </span>

                            <?php else: ?>

                                <span class="workflow-pending">
                                    PENDING
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

            </div>


            <!-- =================================================
                 AUTOMATION NOTICE
                 ================================================= -->

            <div class="automation-notice">

                <div class="automation-symbol">
                    ⚡
                </div>

                <div>

                    <strong>
                        Automation Layer
                    </strong>

                    <p>
                        Future version mein yahan se CRM
                        automatically tasks create karega,
                        required documents request karega,
                        reminders schedule karega aur permitted
                        workflow events par notifications trigger
                        karega.
                    </p>

                </div>

            </div>


            <div class="workflow-actions">

                <a
                    href="<?= Url::to(
                        'services.php'
                    ) ?>"
                    class="secondary-button"
                >
                    Back to Services
                </a>


                <button
                    class="primary-button"
                    type="button"
                >
                    Continue Workflow →
                </button>

            </div>


        </section>

    </main>

</div>

</body>

</html>