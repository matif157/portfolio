<?php

$pageTitle = 'Services';

ob_start();

?>

<div class="page-header">

    <div>
        <div class="eyebrow">SERVICE MANAGEMENT</div>

        <h1>Services</h1>

        <p>
            Manage the services offered by Syed Professional Services.
        </p>
    </div>

    <div class="page-actions">

        <button class="button secondary">
            Service Settings
        </button>

        <button class="button primary">
            + New Service
        </button>

    </div>

</div>


<div class="service-cards">

    <div class="service-card">

        <div class="service-card-top">

            <div class="service-icon">
                IM
            </div>

            <span class="service-status active">
                Active
            </span>

        </div>

        <h2>Immigration Services</h2>

        <p>
            Immigration-related client services and case workflows.
        </p>

        <div class="service-footer">

            <span>
                Cases: —
            </span>

            <button class="text-button">
                Open
            </button>

        </div>

    </div>


    <div class="service-card">

        <div class="service-card-top">

            <div class="service-icon">
                TX
            </div>

            <span class="service-status active">
                Active
            </span>

        </div>

        <h2>Tax Services</h2>

        <p>
            Tax preparation, processing and related client workflows.
        </p>

        <div class="service-footer">

            <span>
                Cases: —
            </span>

            <button class="text-button">
                Open
            </button>

        </div>

    </div>


    <div class="service-card">

        <div class="service-card-top">

            <div class="service-icon">
                DC
            </div>

            <span class="service-status active">
                Active
            </span>

        </div>

        <h2>Documentation</h2>

        <p>
            Document preparation and client documentation services.
        </p>

        <div class="service-footer">

            <span>
                Cases: —
            </span>

            <button class="text-button">
                Open
            </button>

        </div>

    </div>


    <div class="service-card">

        <div class="service-card-top">

            <div class="service-icon">
                OT
            </div>

            <span class="service-status active">
                Active
            </span>

        </div>

        <h2>Other Services</h2>

        <p>
            Additional services and custom client workflows.
        </p>

        <div class="service-footer">

            <span>
                Cases: —
            </span>

            <button class="text-button">
                Open
            </button>

        </div>

    </div>

</div>


<section class="panel">

    <div class="panel-header">

        <div>

            <h2>
                Service Workflow
            </h2>

            <p>
                Standardized workflow stages used by service cases.
            </p>

        </div>

    </div>


    <div class="workflow-stages">

        <div class="workflow-stage">
            <span class="stage-number">01</span>
            <strong>Intake</strong>
            <small>Collect client information</small>
        </div>

        <div class="workflow-line"></div>

        <div class="workflow-stage">
            <span class="stage-number">02</span>
            <strong>Assessment</strong>
            <small>Review requirements</small>
        </div>

        <div class="workflow-line"></div>

        <div class="workflow-stage">
            <span class="stage-number">03</span>
            <strong>Processing</strong>
            <small>Work on the case</small>
        </div>

        <div class="workflow-line"></div>

        <div class="workflow-stage">
            <span class="stage-number">04</span>
            <strong>Review</strong>
            <small>Internal verification</small>
        </div>

        <div class="workflow-line"></div>

        <div class="workflow-stage">
            <span class="stage-number">05</span>
            <strong>Completed</strong>
            <small>Close the service</small>
        </div>

    </div>

</section>


<?php

$content = ob_get_clean();

require __DIR__ . '/../layouts/app.php';