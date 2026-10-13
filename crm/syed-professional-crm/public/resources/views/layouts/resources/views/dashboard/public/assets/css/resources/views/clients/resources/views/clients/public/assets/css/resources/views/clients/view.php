<?php

$pageTitle = 'Client Profile';

ob_start();

?>

<div class="breadcrumb">
    Clients / Client Profile
</div>


<div class="client-profile-header">

    <div class="profile-main">

        <div class="large-avatar">
            CL
        </div>

        <div>

            <div class="eyebrow">
                CLIENT PROFILE
            </div>

            <h1>
                Client Record
            </h1>

            <p>
                Client ID: —
            </p>

        </div>

    </div>


    <div class="page-actions">

        <button class="button secondary">
            Edit Client
        </button>

        <button class="button primary">
            + Add Service
        </button>

    </div>

</div>


<div class="profile-grid">

    <section class="panel">

        <div class="panel-header">

            <div>
                <h2>Client Information</h2>
                <p>Basic contact and identification details</p>
            </div>

        </div>

        <div class="detail-grid">

            <div>
                <span>Full Name</span>
                <strong>—</strong>
            </div>

            <div>
                <span>Phone</span>
                <strong>—</strong>
            </div>

            <div>
                <span>Email</span>
                <strong>—</strong>
            </div>

            <div>
                <span>CNIC / ID</span>
                <strong>—</strong>
            </div>

            <div>
                <span>Country</span>
                <strong>—</strong>
            </div>

            <div>
                <span>Status</span>
                <strong>
                    <span class="status pending">
                        Pending
                    </span>
                </strong>
            </div>

        </div>

    </section>


    <section class="panel">

        <div class="panel-header">

            <div>
                <h2>Client Summary</h2>
                <p>Connected CRM records</p>
            </div>

        </div>

        <div class="profile-stats">

            <div>
                <strong>—</strong>
                <span>Services</span>
            </div>

            <div>
                <strong>—</strong>
                <span>Cases</span>
            </div>

            <div>
                <strong>—</strong>
                <span>Projects</span>
            </div>

            <div>
                <strong>—</strong>
                <span>Invoices</span>
            </div>

        </div>

    </section>

</div>


<div class="profile-tabs">

    <button class="active">
        Overview
    </button>

    <button>
        Services
    </button>

    <button>
        Cases
    </button>

    <button>
        Projects
    </button>

    <button>
        Invoices
    </button>

    <button>
        Payments
    </button>

    <button>
        Documents
    </button>

    <button>
        Comments
    </button>

    <button>
        Activity
    </button>

    <button>
        Tasks
    </button>

</div>


<section class="panel profile-content">

    <div class="panel-header">

        <div>
            <h2>Client Activity</h2>

            <p>
                All client-related activity will appear here.
            </p>
        </div>

        <button class="button primary">
            + Add Comment
        </button>

    </div>


    <div class="profile-empty">

        <div class="empty-icon">
            ◌
        </div>

        <strong>
            No activity loaded
        </strong>

        <p>
            Existing comments, activities and records will appear
            here after the migration layer is connected.
        </p>

    </div>

</section>


<?php

$content = ob_get_clean();

require __DIR__ . '/../layouts/app.php';