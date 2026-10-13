<?php
include "db.php";

 $tables = [];
 $result = $con->query("SHOW TABLES");
while ($row = $result->fetch_array(MYSQLI_NUM)) {
    $tables[] = $row[0];
}

 $tableData = null;
 $limit = 20;
 $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

/* ---------- Custom Query Handling ---------- */
if (!empty($_POST['custom_query'])) {
    $query = trim($_POST['custom_query']);
    if (stripos($query, 'select') === 0) {
        $result = $con->query($query);
        if ($result && $result->num_rows > 0) {
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $columns = array_keys($data[0]);
            $tableData = [
                'name' => 'Custom Query',
                'columns' => $columns,
                'rows' => $data,
                'total' => count($data),
                'pages' => 1,
                'primaryKey' => null
            ];
        } else {
            echo "<div class='alert alert-warning'>No results found or invalid query.</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>Only SELECT queries are allowed.</div>";
    }
}
/* ---------- Table Selection Handling ---------- */
elseif (!empty($_POST['table_name']) || !empty($_GET['table'])) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['table_name'] ?? $_GET['table']);
    
    // Get primary key
    $primaryKey = null;
    $result = $con->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
    if ($result && $row = $result->fetch_assoc()) {
        $primaryKey = $row['Column_name'];
    }

    $totalRows = 0;
    $result = $con->query("SELECT COUNT(*) AS cnt FROM `$table`");
    if ($result && $row = $result->fetch_assoc()) {
        $totalRows = (int)$row['cnt'];
    }
    $totalPages = ceil($totalRows / $limit);

    $offset = ($page - 1) * $limit;
    $data = [];
    $columns = [];

    $query = "SELECT * FROM `$table` LIMIT $limit OFFSET $offset";
    $result = $con->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        if (!empty($data)) {
            $columns = array_keys($data[0]);
        }
    }

    $tableData = [
        'name' => $table,
        'columns' => $columns,
        'rows' => $data,
        'total' => $totalRows,
        'pages' => $totalPages,
        'primaryKey' => $primaryKey
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Table Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #5EB839;
            --secondary-color: #4A962E;
            --accent-color: #f72585;
            --light-bg: #f8f9fa;
            --card-bg: #ffffff;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --border-color: #dee2e6;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --table-hover: rgba(67, 97, 238, 0.05);
            --header-bg: linear-gradient(to right, #f8f9fa, #e9ecef);
            --shadow-color: rgba(0, 0, 0, 0.08);
            --shadow-hover-color: rgba(0, 0, 0, 0.12);
        }

        [data-theme="dark"] {
            --primary-color: #5EB839;
            --secondary-color: #68c746ff;
            --accent-color: #f72585;
            --light-bg: #1a1d29;
            --card-bg: #222633;
            --text-primary: #e9ecef;
            --text-secondary: #adb5bd;
            --border-color: #343a40;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --table-hover: rgba(94, 184, 57, 0.1);
            --header-bg: linear-gradient(to right, #2a2d3a, #1f2330);
            --shadow-color: rgba(0, 0, 0, 0.3);
            --shadow-hover-color: rgba(0, 0, 0, 0.5);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: var(--text-primary);
            padding: 0;
            margin: 0;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .navbar-brand i {
            margin-right: 8px;
        }

        .container-fluid {
            padding: 20px;
        }

        .card {
             border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 4px 12px var(--shadow-color);
            margin-bottom: 24px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background-color: var(--card-bg);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px var(--shadow-hover-color);
        }

        .card-header {
            background: var(--header-bg);
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            border-radius: 12px 12px 0 0 !important;
            padding: 15px 20px;
            color: var(--text-primary);
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 8px var(--shadow-color);
        }

        .table {
            margin-bottom: 0;
            color: var(--text-primary);
        }

        .table thead th {
            background-color: var(--primary-color);
            color: white;
            border: none;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table tbody tr:hover {
            background-color: var(--table-hover);
        }

        .table td, .table th {
            padding: 12px 15px;
            vertical-align: middle;
                border: 1px solid var(--border-color);

        }

        .primary-key {
            background-color: rgba(94, 184, 57, 0.1);
            font-weight: 600;
            position: relative;
        }

        [data-theme="dark"] .primary-key {
            background-color: rgba(94, 184, 57, 0.2);
        }

        .pagination {
            justify-content: center;
            margin-top: 20px;
        }

        .page-link {
            color: var(--primary-color);
            border-radius: 6px;
            margin: 0 3px;
            background-color: var(--card-bg);
            border-color: var(--border-color);
        }

        .page-link:hover {
            color: var(--text-primary);
            background-color: var(--table-hover);
        }

        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .form-control, .form-select {
            background-color: var(--card-bg);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(94, 184, 57, 0.25);
            background-color: var(--card-bg);
            color: var(--text-primary);
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(94, 184, 57, 0.3);
        }

        .stats-card h5 {
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 5px;
        }

        .stats-card h3 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }

        .table-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .table-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .table-actions {
            display: flex;
            gap: 10px;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-success {
            color: var(--success-color);
            border-color: var(--success-color);
        }

        .btn-outline-success:hover {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }

        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .custom-query-container {
            background-color: var(--light-bg);
            border-radius: 8px;
            padding: 15px;
        }

        .footer {
            text-align: center;
            padding: 20px;
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 40px;
        }

        .theme-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .theme-toggle:hover {
            transform: rotate(20deg);
        }

        @media (max-width: 768px) {
            .table-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .table-actions {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="bi bi-database-fill"></i>
                Database Table Viewer
            </a>
            <div class="d-flex align-items-center">
                <span class="navbar-text me-3">
                    <i class="bi bi-server"></i> Connected to Database
                </span>
                <button class="theme-toggle" id="theme-toggle" aria-label="Toggle theme">
                    <i class="bi bi-sun-fill" id="theme-icon"></i>
                </button>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <?php if (!empty($tableData)): ?>
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <h5>Table Name</h5>
                        <h3><?= htmlspecialchars($tableData['name']) ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h5>Total Rows</h5>
                        <h3><?= number_format($tableData['total']) ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h5>Columns</h5>
                        <h3><?= count($tableData['columns']) ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h5>Primary Key</h5>
                        <h3><?= $tableData['primaryKey'] ? htmlspecialchars($tableData['primaryKey']) : 'None' ?></h3>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-table"></i> Select Table
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="mb-3">
                                <select name="table_name" class="form-select">
                                    <option value="">-- Choose a table --</option>
                                    <?php foreach ($tables as $tbl): ?>
                                        <option value="<?= htmlspecialchars($tbl) ?>" <?= (isset($tableData['name']) && $tableData['name'] === $tbl) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($tbl) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-eye"></i> View Data
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-code-slash"></i> Run SQL Query
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="mb-3">
                                <textarea name="custom_query" class="form-control" rows="3" placeholder="e.g. SELECT * FROM users LIMIT 10"><?= htmlspecialchars($_POST['custom_query'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-play-fill"></i> Run Query
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($tableData): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-grid-3x3-gap"></i> Table Data
                    </div>
                    <div class="table-actions">
                        <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print
                        </button>
                        <button class="btn btn-sm btn-outline-success" onclick="exportTable()">
                            <i class="bi bi-download"></i> Export
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-info">
                        <div class="table-name">
                            <i class="bi bi-table"></i> <?= htmlspecialchars($tableData['name']) ?>
                            <?php if ($tableData['primaryKey']): ?>
                                <span class="badge bg-primary ms-2">PK: <?= htmlspecialchars($tableData['primaryKey']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="search-box">
                            <input type="text" class="form-control" placeholder="Search..." id="tableSearch">
                            <i class="bi bi-search"></i>
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <table class="table table-hover" id="dataTable">
                            <thead>
                                <tr>
                                    <?php foreach ($tableData['columns'] as $col): ?>
                                        <th class="<?= $tableData['primaryKey'] === $col ? 'primary-key' : '' ?>">
                                            <?= htmlspecialchars($col) ?>
                                            <?php if ($tableData['primaryKey'] === $col): ?>
                                                <i class="bi bi-key-fill ms-1"></i>
                                            <?php endif; ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tableData['rows'] as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $key => $value): ?>
                                            <td class="<?= $tableData['primaryKey'] === $key ? 'primary-key' : '' ?>">
                                                <?= htmlspecialchars($value) ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($tableData['pages'] > 1): ?>
                        <nav aria-label="Table pagination">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?table=<?= urlencode($tableData['name']) ?>&page=<?= $page - 1 ?>">
                                            <i class="bi bi-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php 
                                $startPage = max(1, $page - 2);
                                $endPage = min($tableData['pages'], $page + 2);
                                
                                if ($startPage > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?table=' . urlencode($tableData['name']) . '&page=1">1</a></li>';
                                    if ($startPage > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                
                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?table=<?= urlencode($tableData['name']) ?>&page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php 
                                if ($endPage < $tableData['pages']) {
                                    if ($endPage < $tableData['pages'] - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    echo '<li class="page-item"><a class="page-link" href="?table=' . urlencode($tableData['name']) . '&page=' . $tableData['pages'] . '">' . $tableData['pages'] . '</a></li>';
                                }
                                ?>
                                
                                <?php if ($page < $tableData['pages']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?table=<?= urlencode($tableData['name']) ?>&page=<?= $page + 1 ?>">
                                            Next <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>&copy; <?= date('Y') ?> Database Table Viewer. All rights reserved.</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Theme toggle functionality
        const themeToggle = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        const htmlElement = document.documentElement;
        
        // Check for saved theme preference or default to light mode
        const currentTheme = localStorage.getItem('theme') || 'light';
        htmlElement.setAttribute('data-theme', currentTheme);
        
        // Update icon based on current theme
        if (currentTheme === 'dark') {
            themeIcon.classList.remove('bi-sun-fill');
            themeIcon.classList.add('bi-moon-fill');
        }
        
        // Toggle theme when button is clicked
        themeToggle.addEventListener('click', function() {
            const theme = htmlElement.getAttribute('data-theme');
            const newTheme = theme === 'dark' ? 'light' : 'dark';
            
            htmlElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Update icon
            if (newTheme === 'dark') {
                themeIcon.classList.remove('bi-sun-fill');
                themeIcon.classList.add('bi-moon-fill');
            } else {
                themeIcon.classList.remove('bi-moon-fill');
                themeIcon.classList.add('bi-sun-fill');
            }
        });
        
        // Table search functionality
        document.getElementById('tableSearch')?.addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const table = document.getElementById('dataTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j].textContent.toLowerCase().indexOf(searchValue) > -1) {
                        found = true;
                        break;
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
        });
        
        // Export table functionality
        function exportTable() {
            const table = document.getElementById('dataTable');
            let csv = [];
            
            // Get headers
            const headers = [];
            const headerCells = table.querySelectorAll('thead th');
            headerCells.forEach(cell => {
                headers.push(cell.textContent.trim());
            });
            csv.push(headers.join(','));
            
            // Get rows
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const rowData = [];
                const cells = row.querySelectorAll('td');
                cells.forEach(cell => {
                    // Escape quotes and handle commas
                    let value = cell.textContent.trim();
                    if (value.includes(',') || value.includes('"')) {
                        value = `"${value.replace(/"/g, '""')}"`;
                    }
                    rowData.push(value);
                });
                csv.push(rowData.join(','));
            });
            
            // Create download link
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `<?= isset($tableData['name']) ? htmlspecialchars($tableData['name']) : 'table' ?>_${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>