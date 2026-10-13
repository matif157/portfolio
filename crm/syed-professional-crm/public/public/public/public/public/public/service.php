<?php
/**
 * =====================================================
 * FILE: service.php
 * PURPOSE: Guided Service Filing prototype
 *
 * WORKFLOW:
 *
 * Client
 *    ↓
 * Select Service
 *    ↓
 * Service Questions
 *    ↓
 * Warnings
 *    ↓
 * Review
 *    ↓
 * Submit
 *
 * IMPORTANT:
 * Abhi database mein service create nahi kar rahe.
 * Ye intentionally workflow prototype hai.
 *
 * Final version mein:
 *
 * Service Template
 *      ↓
 * Automation Engine
 *      ↓
 * Required Documents
 *      ↓
 * Tasks
 *      ↓
 * Notifications
 *      ↓
 * Billing
 *      ↓
 * Completion
 *
 * connect hoga.
 * =====================================================
 */

require_once dirname(__DIR__) . '/config/config.php';

require_login();


$type =
    $_GET['type']
    ?? '';


$clientId =
    (int) ($_GET['client_id'] ?? 0);


/*
|--------------------------------------------------------------------------
| AVAILABLE SERVICE TYPES
|--------------------------------------------------------------------------
*/

$services = [

    'tax' => [

        'name' =>
            'Tax Filing',

        'description' =>
            'Guided tax service intake.',

        'documents' => [

            'Government-issued ID',

            'Prior-year tax return',

            'Income documents',

            'Relevant expense documents',

        ],

    ],


    'immigration' => [

        'name' =>
            'Immigration Service',

        'description' =>
            'Guided immigration case intake.',

        'documents' => [

            'Passport',

            'Government-issued ID',

            'Immigration documents',

            'Supporting evidence',

        ],

    ],


    'business' => [

        'name' =>
            'Business Registration',

        'description' =>
            'Guided business service intake.',

        'documents' => [

            'Owner identification',

            'Business information',

            'Business address information',

        ],

    ],

];


if (!isset($services[$type])) {

    $type = '';

}

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
        File Service | SPS CRM
    </title>

    <link
        rel="stylesheet"
        href="<?= APP_URL ?>/assets/css/app.css"
    >

</head>

<body>

<div class="crm-shell">

    <aside class="sidebar">

        <div class="sidebar-brand">

            <div class="brand-logo small">
                SPS
            </div>

            <strong>
                SPS CRM
            </strong>

        </div>


        <nav>

            <a
                class="nav-link"
                href="<?= APP_URL ?>/index.php"
            >
                ▦ Dashboard
            </a>

            <a
                class="nav-link"
                href="<?= APP_URL ?>/clients.php"
            >
                ◉ Clients
            </a>

            <a
                class="nav-link active"
                href="<?= APP_URL ?>/service.php"
            >
                ◆ File a Service
            </a>

        </nav>

    </aside>


    <main class="main-content">

        <header class="topbar">

            <div>

                <span class="eyebrow">
                    GUIDED SERVICE FILING
                </span>

                <h1>
                    File a Service
                </h1>

                <p class="muted">
                    The CRM will guide you through each step.
                </p>

            </div>

        </header>


        <!-- STEP INDICATOR -->

        <div class="steps">

            <div class="step active">
                <span>1</span>
                Service
            </div>

            <div class="step">
                <span>2</span>
                Information
            </div>

            <div class="step">
                <span>3</span>
                Documents
            </div>

            <div class="step">
                <span>4</span>
                Review
            </div>

        </div>


        <section class="wizard-card">

            <span class="eyebrow">
                STEP 1
            </span>

            <h2>
                What service are you filing?
            </h2>

            <p class="muted">
                Select a service. Required questions and
                documents will be generated automatically.
            </p>


            <div class="service-grid">

                <?php foreach (
                    $services as $key => $service
                ): ?>

                    <a
                        class="service-card <?= $type === $key ? 'selected' : '' ?>"
                        href="?type=<?= e($key) ?><?= $clientId ? '&client_id=' . $clientId : '' ?>"
                    >

                        <span class="service-icon">
                            <?= strtoupper(
                                substr(
                                    $service['name'],
                                    0,
                                    1
                                )
                            ) ?>
                        </span>

                        <h3>
                            <?= e(
                                $service['name']
                            ) ?>
                        </h3>

                        <p>
                            <?= e(
                                $service['description']
                            ) ?>
                        </p>

                    </a>

                <?php endforeach; ?>

            </div>


            <?php if ($type !== ''): ?>

                <div class="workflow-preview">

                    <div>

                        <span class="eyebrow">
                            AUTOMATIC CHECKLIST
                        </span>

                        <h2>
                            <?= e(
                                $services[$type]['name']
                            ) ?>
                        </h2>

                        <p class="muted">
                            Before filing, SPS staff will be
                            guided through these requirements.
                        </p>

                    </div>


                    <div class="checklist">

                        <?php foreach (
                            $services[$type]['documents']
                            as $document
                        ): ?>

                            <div class="check-item">

                                <span>
                                    ✓
                                </span>

                                <?= e($document) ?>

                            </div>

                        <?php endforeach; ?>

                    </div>


                    <!-- WARNING -->

                    <div class="workflow-warning">

                        <strong>
                            ⚠ Review before filing
                        </strong>

                        <p>
                            Do not submit the service until all
                            required information and documents
                            have been reviewed.
                        </p>

                    </div>


                    <button
                        class="btn btn-primary"
                        type="button"
                        onclick="alert('Temporary workflow: next step will be connected to the service intake engine.')"
                    >
                        Continue to Information →
                    </button>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>

</html>