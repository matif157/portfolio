 <?php

$pageTitle = 'Clients';

ob_start();

?>

<div class="page-header">

    <div>
        <div class="eyebrow">CLIENT MANAGEMENT</div>

        <h1>Clients</h1>

        <p>
            Manage client records, services, cases, invoices and activity.
        </p>
    </div>

    <div class="page-actions">
        <button class="button secondary">
            Import
        </button>

        <button class="button primary">
            + New Client
        </button>
    </div>

</div>


<div class="client-toolbar">

    <div class="client-search">

        <span>⌕</span>

        <input
            type="search"
            placeholder="Search by name, phone, email or client ID..."
        >

    </div>

    <select>
        <option>All Clients</option>
        <option>Active</option>
        <option>Inactive</option>
        <option>Pending</option>
    </select>

    <select>
        <option>All Services</option>
        <option>Immigration</option>
        <option>Tax</option>
        <option>Documentation</option>
        <option>Other</option>
    </select>

</div>


<div class="client-summary">

    <div>
        <span>Total Records</span>
        <strong>7,000+</strong>
    </div>

    <div>
        <span>Active</span>
        <strong>—</strong>
    </div>

    <div>
        <span>Pending Cases</span>
        <strong>—</strong>
    </div>

    <div>
        <span>Outstanding</span>
        <strong>—</strong>
    </div>

</div>


<section class="panel clients-panel">

    <div class="panel-header">

        <div>
            <h2>Client Records</h2>

            <p>
                Client information will be connected to the migrated
                database.
            </p>
        </div>

        <button class="text-button">
            Export
        </button>

    </div>


    <div class="table-wrapper">

        <table class="clients-table">

            <thead>

                <tr>
                    <th>Client</th>
                    <th>Client ID</th>
                    <th>Contact</th>
                    <th>Services</th>
                    <th>Status</th>
                    <th>Last Activity</th>
                    <th></th>
                </tr>

            </thead>

            <tbody>

                <tr>

                    <td>

                        <div class="client-cell">

                            <div class="client-avatar">
                                CL
                            </div>

                            <div>
                                <strong>
                                    Client records ready
                                </strong>

                                <span>
                                    Migration connection pending
                                </span>
                            </div>

                        </div>

                    </td>

                    <td>
                        —
                    </td>

                    <td>
                        —
                    </td>

                    <td>
                        —
                    </td>

                    <td>
                        <span class="status pending">
                            Pending
                        </span>
                    </td>

                    <td>
                        —
                    </td>

                    <td>
                        <button class="row-menu">
                            ⋮
                        </button>
                    </td>

                </tr>

            </tbody>

        </table>

    </div>


    <div class="table-footer">

        <span>
            Database migration will populate client records.
        </span>

        <div class="pagination">

            <button disabled>
                ‹
            </button>

            <button class="current">
                1
            </button>

            <button disabled>
                ›
            </button>

        </div>

    </div>

</section>


<?php

$content = ob_get_clean();

require __DIR__ . '/../layouts/app.php';