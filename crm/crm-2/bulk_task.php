<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "includes/db.php";
include "includes/auth.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
} 

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['fullname'];
$user_initials = getInitials($user_full_name);
$user_type = $_SESSION['user_type'];

function getInitials($name)
{
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return $initials;
}

// Handle bulk operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $selected_clients = $_POST['selected_clients'] ?? [];
        $client_descriptions = $_POST['client_descriptions'] ?? [];
        $same_description = $_POST['same_description'] ?? '';
        $use_same_for_all = isset($_POST['use_same_for_all']) ? true : false;

        if (empty($selected_clients)) {
            $_SESSION['error_msg'] = "Please select at least one client";
            header('Location: bulk_tasks.php');
            exit();
        } else {
            // If using same description for all, apply it to all clients
            if ($use_same_for_all && !empty($same_description)) {
                foreach ($selected_clients as $client_id) {
                    $client_descriptions[$client_id] = $same_description;
                }
            }

            switch ($_POST['action']) {
                case 'export_csv':
                    exportToCSV($selected_clients, $client_descriptions, $con);
                    break;
                case 'export_word':
                    exportToWord($selected_clients, $client_descriptions, $con);
                    break;
            }
        }
    }
}

function exportToCSV($client_ids, $client_descriptions, $con)
{
    // Convert array to comma-separated string for SQL IN clause
    $ids = implode(',', array_map('intval', $client_ids));
    $query = "SELECT * FROM members WHERE member_id IN ($ids)";
    $result = mysqli_query($con, $query);

    // Clean output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }

    $filename = "clients_export_" . date('Y-m-d_H-i-s') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Header row
    fputcsv($output, ['Client ID', 'Name', 'Business Name', 'Email', 'Phone', 'Address', 'City', 'State', 'ZIP', 'Client Since', 'Description']);

    // Data rows
    while ($client = mysqli_fetch_assoc($result)) {
        $full_name = $client['name'] . ' ' . $client['mname'] . ' ' . $client['lname'];
        $description = isset($client_descriptions[$client['member_id']]) ? $client_descriptions[$client['member_id']] : '';
        fputcsv($output, [
            $client['member_id'],
            $full_name,
            $client['bus_name'],
            $client['prm_email'],
            $client['phone'],
            $client['address'],
            $client['city'],
            $client['state'],
            $client['zip'],
            $client['client_since'],
            $description
        ]);
    }

    fclose($output);
    exit();
}

function exportToWord($client_ids, $client_descriptions, $con)
{
    // Convert array to comma-separated string for SQL IN clause
    $ids = implode(',', array_map('intval', $client_ids));
    $query = "SELECT * FROM members WHERE member_id IN ($ids)";
    $result = mysqli_query($con, $query);

    // Clean output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }

    $filename = "clients_export_" . date('Y-m-d_H-i-s') . ".doc";
    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Expires: 0');

    $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">';
    $html .= '<head><meta charset="utf-8"><title>Client Export</title>';
    $html .= '<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { color: #6FD943; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th { background-color: #6FD943; color: white; padding: 10px; text-align: left; font-weight: bold; }
        td { border: 1px solid #ddd; padding: 8px; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .description-cell { max-width: 250px; word-wrap: break-word; }
    </style>';
    $html .= '</head><body>';

    $html .= '<h2>Client List with Descriptions</h2>';
    $html .= '<table>';
    $html .= '<tr>
                <th>Client ID</th>
                <th>Name</th>
                <th>Business Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Address</th>
                <th>City</th>
                <th>State</th>
                <th>ZIP</th>
                <th>Description</th>
              </tr>';

    while ($client = mysqli_fetch_assoc($result)) {
        $full_name = $client['name'] . ' ' . $client['mname'] . ' ' . $client['lname'];
        $description = isset($client_descriptions[$client['member_id']]) ? htmlspecialchars($client_descriptions[$client['member_id']]) : '';
        $html .= '<tr>
                    <td>' . $client['member_id'] . '</td>
                    <td>' . htmlspecialchars($full_name) . '</td>
                    <td>' . htmlspecialchars($client['bus_name']) . '</td>
                    <td>' . htmlspecialchars($client['prm_email']) . '</td>
                    <td>' . htmlspecialchars($client['phone']) . '</td>
                    <td>' . htmlspecialchars($client['address']) . '</td>
                    <td>' . htmlspecialchars($client['city']) . '</td>
                    <td>' . htmlspecialchars($client['state']) . '</td>
                    <td>' . htmlspecialchars($client['zip']) . '</td>
                    <td class="description-cell">' . $description . '</td>
                  </tr>';
    }

    $html .= '</table></body></html>';

    echo $html;
    exit();
}

// Fetch all clients for select2
$query = "SELECT member_id, name, mname, lname, bus_name, prm_email FROM members WHERE status = 1 ORDER BY name, lname";
$result = mysqli_query($con, $query);
$clients = [];
while ($row = mysqli_fetch_assoc($result)) {
    $clients[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
    <title>Bulk Tasks - Syed Professional Services</title>
    <?php include "includes/links.php"; ?>

    <!-- Select2 CSS -->
    <!-- Font Awesome 5 (for fas fa- icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        /* Ensure Select2 search is visible */
        .select2-search {
            display: block !important;
        }

        .select2-search__field {
            width: 100% !important;
            padding: 5px !important;
        }

        .select2-container--default .select2-selection--multiple {
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            min-height: 38px;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #6FD943;
            border: 1px solid #6FD943;
            color: white;
            padding: 3px 8px;
            margin: 3px;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: white;
            margin-right: 5px;
            font-weight: bold;
        }

        .select2-container--default .select2-results__option--highlighted {
            background-color: #6FD943;
        }

        .select2-dropdown {
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
        }

        .action-card {
            transition: all 0.3s ease;
            border: 2px solid #6FD943;
            color: #6FD943;
            background-color: white;
            font-weight: 500;
        }

        .action-card:hover {
            background-color: #6FD943;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(111, 217, 67, 0.3);
        }

        .selected-count {
            background-color: #6FD943;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            display: inline-block;
        }

        .bulk-operations-card {
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .bulk-operations-card .card-header {
            background-color: #6FD943;
            color: white;
            border: none;
        }

        .page-header-title h4 {
            color: #6FD943;
        }

        .breadcrumb-item.active {
            color: #6FD943;
        }

        .form-control:focus {
            border-color: #6FD943;
            box-shadow: 0 0 0 0.2rem rgba(111, 217, 67, 0.25);
        }

        .alert-success {
            background-color: rgba(111, 217, 67, 0.1);
            border-color: #6FD943;
            color: #3a7f24;
        }

        .alert-danger {
            background-color: rgba(255, 0, 0, 0.1);
            border-color: #ff0000;
            color: #cc0000;
        }

        .selected-clients-table {
            margin-top: 20px;
        }

        .selected-clients-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }

        .client-row {
            transition: background-color 0.2s;
        }

        .client-row:hover {
            background-color: #f8f9fa;
        }

        .remove-client {
            color: #dc3545;
            cursor: pointer;
            transition: color 0.2s;
            font-size: 18px;
        }

        .remove-client:hover {
            color: #a02622;
        }

        .description-input {
            min-width: 250px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .description-options {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .description-options .form-check {
            margin-bottom: 10px;
        }

        .description-options .form-check-input:checked {
            background-color: #6FD943;
            border-color: #6FD943;
        }

        .same-description-field {
            margin-top: 10px;
            display: none;
        }

        .apply-to-all-btn {
            background-color: #6FD943;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .apply-to-all-btn:hover {
            background-color: #5cb836;
        }
        
    </style>
</head>

<body class="theme-3">

    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>

    <?php include "includes/sidebar.php"; ?>
    <?php include "includes/header.php"; ?>

    <div class="dash-container">
        <div class="dash-content">
            <div class="card card-body">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="page-header-title">
                                <h4 class="m-b-10">Bulk Operations</h4>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Bulk Tasks</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success_msg'];
                    unset($_SESSION['success_msg']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error_msg'];
                    unset($_SESSION['error_msg']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Main Content -->
            <div class="row">
                <!-- Client Selection -->
                <div class="col-12">
                    <div class="card bulk-operations-card">
                        <div class="card-header text-white">
                            <h5 class="mb-0"><i class="fas fa-users me-2"></i>Select Clients for Bulk Operations</h5>
                        </div>
                        <div class="card-body">
                            <form id="bulkForm" method="POST">
                                <div class="mb-3">
                                    <label for="clientSelect" class="form-label fw-bold">Choose Clients:</label>
                                    <select class="form-select" id="clientSelect" multiple="multiple">
                                        <?php foreach ($clients as $client): ?>
                                            <option value="<?php echo $client['member_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($client['name'] . ' ' . $client['lname']); ?>"
                                                data-business="<?php echo htmlspecialchars($client['bus_name']); ?>"
                                                data-email="<?php echo htmlspecialchars($client['prm_email']); ?>">
                                                <?php echo htmlspecialchars('ID: '.$client['member_id'] . ' - ' . $client['name'] . ' ' . $client['lname'] . ' - ' . $client['bus_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Start typing to search clients...</small>
                                </div>

                                <div class="selected-count d-none mb-3">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <span id="selectedCount">0</span> clients selected
                                </div>

                                <!-- Description Options -->
                                <div id="descriptionOptions" class="description-options d-none">
                                    <h6 class="mb-3">Description Options:</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="descriptionType"
                                            id="individualDesc" value="individual" checked>
                                        <label class="form-check-label" for="individualDesc">
                                            <strong>Individual Descriptions</strong> - Add different description for
                                            each client
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="descriptionType"
                                            id="sameDesc" value="same">
                                        <label class="form-check-label" for="sameDesc">
                                            <strong>Same Description for All</strong> - Use one description for all
                                            selected clients
                                        </label>
                                    </div>

                                    <div id="sameDescriptionField" class="same-description-field">
                                        <label for="sameDescription" class="form-label">Description for all
                                            clients:</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="sameDescription"
                                                placeholder="Enter description to apply to all clients...">
                                            <button class="apply-to-all-btn" type="button" onclick="applyToAll()">
                                                <i class="fas fa-check me-1"></i>Apply to All
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Selected Clients Table -->
                                <div id="selectedClientsSection" class="d-none">
                                    <h5 class="mb-3">Selected Clients</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered selected-clients-table">
                                            <thead>
                                                <tr>
                                                    <th width="5%">#</th>
                                                    <th width="22%">Client Name</th>
                                                    <th width="22%">Business Name</th>
                                                    <th width="18%">Email</th>
                                                    <th width="28%">Description</th>
                                                    <th width="5%">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="selectedClientsBody">
                                                <!-- Selected clients will be added here dynamically -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Empty State -->
                                <div id="emptyState" class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <p>No clients selected yet. Search and select clients from the dropdown above to add
                                        them to the list.</p>
                                </div>

                                <!-- Action Buttons -->
                                <div class="row g-3 mt-3" id="actionButtons" style="display: none;">
                                    <div class="col-md-6">
                                        <button type="button" class="btn w-100 action-card"
                                            onclick="performAction('export_csv')">
                                            <i class="fas fa-file-csv me-2"></i>Export to CSV
                                        </button>
                                    </div>
                                    <div class="col-md-6">
                                        <button type="button" class="btn w-100 action-card"
                                            onclick="performAction('export_word')">
                                            <i class="fas fa-file-word me-2"></i>Export to Word
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>

    <!-- jQuery (required before Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        let selectedClientsData = {};

        $(document).ready(function () {
            // Initialize Select2 with explicit search configuration
            $('#clientSelect').select2({
                placeholder: 'Search and select clients...',
                allowClear: true,
                closeOnSelect: false,
                width: '100%',
                minimumInputLength: 0,
                tags: false,
                tokenSeparators: [','],
                // Ensure search is always visible
                dropdownCssClass: 'select2-dropdown-visible'
            });

            // Handle client selection
            $('#clientSelect').on('change', function () {
                const selected = $(this).val();
                const count = selected ? selected.length : 0;
                $('#selectedCount').text(count);

                if (count > 0) {
                    $('.selected-count').removeClass('d-none');
                    $('#descriptionOptions').removeClass('d-none');
                    updateSelectedClientsTable(selected);
                } else {
                    $('.selected-count').addClass('d-none');
                    $('#descriptionOptions').addClass('d-none');
                    $('#selectedClientsSection').addClass('d-none');
                    $('#emptyState').removeClass('d-none');
                    $('#actionButtons').hide();
                }
            });

            // Handle description type change
            $('input[name="descriptionType"]').on('change', function () {
                if ($(this).val() === 'same') {
                    $('#sameDescriptionField').slideDown();
                    // Disable individual description fields
                    $('.description-input').prop('disabled', true).css('background-color', '#f8f9fa');
                } else {
                    $('#sameDescriptionField').slideUp();
                    // Enable individual description fields
                    $('.description-input').prop('disabled', false).css('background-color', 'white');
                }
            });
        });

        function updateSelectedClientsTable(selectedIds) {
            const tbody = $('#selectedClientsBody');
            tbody.empty();
            selectedClientsData = {};

            selectedIds.forEach(function (id, index) {
                const option = $('#clientSelect option[value="' + id + '"]');
                const name = option.data('name');
                const business = option.data('business');
                const email = option.data('email');

                selectedClientsData[id] = {
                    name: name,
                    business: business,
                    email: email,
                    description: ''
                };

                const row = '<tr class="client-row" data-client-id="' + id + '">' +
                    '<td>' + (index + 1) + '</td>' +
                    '<td>' + name + '</td>' +
                    '<td>' + business + '</td>' +
                    '<td>' + email + '</td>' +
                    '<td><input type="text" class="form-control description-input" name="client_descriptions[' + id + ']" placeholder="Add individual description..."></td>' +
                    '<td><i class="fas fa-times remove-client" onclick="removeClient(' + id + ')"></i></td>' +
                    '</tr>';
                tbody.append(row);
            });

            $('#selectedClientsSection').removeClass('d-none');
            $('#emptyState').addClass('d-none');
            $('#actionButtons').show();
        }

        function removeClient(clientId) {
            // Remove from select2
            $('#clientSelect option[value="' + clientId + '"]').prop('selected', false);
            $('#clientSelect').trigger('change');

            // Remove from data
            delete selectedClientsData[clientId];

            // Update table
            const selected = $('#clientSelect').val() || [];
            if (selected.length > 0) {
                updateSelectedClientsTable(selected);
            }
        }

        function applyToAll() {
            const sameDescription = $('#sameDescription').val();
            if (!sameDescription) {
                alert('Please enter a description first');
                return;
            }

            // Apply to all individual description fields
            $('.description-input').val(sameDescription);

            // Show success feedback
            const btn = $('.apply-to-all-btn');
            const originalText = btn.html();
            btn.html('<i class="fas fa-check me-1"></i>Applied!').css('background-color', '#28a745');
            setTimeout(function () {
                btn.html(originalText).css('background-color', '#6FD943');
            }, 1500);
        }

        function performAction(action) {
            const selected = $('#clientSelect').val();

            if (!selected || selected.length === 0) {
                alert('Please select at least one client');
                return;
            }

            // Create a hidden form to submit the data
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            // Add action field
            const actionField = document.createElement('input');
            actionField.type = 'hidden';
            actionField.name = 'action';
            actionField.value = action;
            form.appendChild(actionField);

            // Add selected client IDs
            selected.forEach(function (id) {
                const clientField = document.createElement('input');
                clientField.type = 'hidden';
                clientField.name = 'selected_clients[]';
                clientField.value = id;
                form.appendChild(clientField);
            });

            // Check if using same description for all
            if ($('input[name="descriptionType"]:checked').val() === 'same') {
                const sameDescField = document.createElement('input');
                sameDescField.type = 'hidden';
                sameDescField.name = 'use_same_for_all';
                sameDescField.value = '1';
                form.appendChild(sameDescField);

                const sameDescValue = document.createElement('input');
                sameDescValue.type = 'hidden';
                sameDescValue.name = 'same_description';
                sameDescValue.value = $('#sameDescription').val();
                form.appendChild(sameDescValue);
            } else {
                // Collect individual descriptions
                $('.description-input').each(function () {
                    const name = $(this).attr('name');
                    const match = name.match(/client_descriptions\[(\d+)\]/);
                    if (match) {
                        const clientId = match[1];
                        const descField = document.createElement('input');
                        descField.type = 'hidden';
                        descField.name = 'client_descriptions[' + clientId + ']';
                        descField.value = $(this).val();
                        form.appendChild(descField);
                    }
                });
            }

            // Submit the form
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>

</html>