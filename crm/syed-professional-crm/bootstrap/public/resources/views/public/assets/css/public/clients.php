<?php
/**
 * =====================================================
 * FILE: clients.php
 * PURPOSE: CRM ke clients module ka temporary frontend.
 *
 * WORKFLOW:
 * Dashboard
 *    ↓
 * Clients
 *    ↓
 * Client list
 *    ↓
 * Search / Filter
 *    ↓
 * Client Profile
 *
 * IMPORTANT:
 * Abhi demo records hain.
 * Baad mein isi UI ko 7,000+ MySQL clients se connect
 * kiya jayega.
 * =====================================================
 */

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Bootstrap load
|--------------------------------------------------------------------------
|
| require_once = PHP built-in.
| Application ke core systems load karta hai.
|
*/
$container = require_once dirname(__DIR__) . '/bootstrap/app.php';

/*
|--------------------------------------------------------------------------
| Temporary client data
|--------------------------------------------------------------------------
|
| Abhi database connect nahi kar rahe.
| Iska purpose sirf UI aur workflow test karna hai.
|
| Future:
| clients table
|      ↓
| repository
|      ↓
| pagination
|      ↓
| yehi table
|
*/
$clients = [

    [
        'id' => 'SPS-10001',
        'name' => 'Ahmed Khan',
        'email' => 'ahmed@example.com',
        'phone' => '+1 (555) 201-1001',
        'service' => 'Tax Filing',
        'status' => 'Active',
    ],

    [
        'id' => 'SPS-10002',
        'name' => 'Sarah Williams',
        'email' => 'sarah@example.com',
        'phone' => '+1 (555) 201-1002',
        'service' => 'Immigration',
        'status' => 'In Progress',
    ],

    [
        'id' => 'SPS-10003',
        'name' => 'Muhammad Ali',
        'email' => 'muhammad@example.com',
        'phone' => '+1 (555) 201-1003',
        'service' => 'Bookkeeping',
        'status' => 'Active',
    ],

    [
        'id' => 'SPS-10004',
        'name' => 'James Anderson',
        'email' => 'james@example.com',
        'phone' => '+1 (555) 201-1004',
        'service' => 'Business Registration',
        'status' => 'Pending',
    ],

];

/*
|--------------------------------------------------------------------------
| URL helper
|--------------------------------------------------------------------------
|
| Custom Url class application ke internal links generate karti hai.
|
*/
use App\Core\Url;

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
        Clients - Syed Professional Services CRM
    </title>

    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(Url::asset('css/app.css')) ?>"
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
                class="nav-item active"
            >
                <span>👥</span>
                Clients
            </a>

            <a href="#" class="nav-item">
                <span>📁</span>
                Cases
            </a>

            <a
                href="<?= Url::to('services.php') ?>"
                class="nav-item"
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
                    CLIENT MANAGEMENT
                </span>

                <h1>
                    Clients
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
                 PAGE INTRO
                 ================================================= -->

            <div class="section-heading">

                <div>

                    <span class="eyebrow">
                        CLIENT DATABASE
                    </span>

                    <h2>
                        Client Directory
                    </h2>

                </div>

                <button class="primary-button">
                    + Add Client
                </button>

            </div>


            <!-- =================================================
                 SEARCH AREA
                 ================================================= -->

            <div class="client-toolbar">

                <input
                    type="text"
                    class="search-input"
                    placeholder="Search client name, ID, email..."
                >

                <select class="filter-select">

                    <option>
                        All Services
                    </option>

                    <option>
                        Tax Filing
                    </option>

                    <option>
                        Immigration
                    </option>

                    <option>
                        Bookkeeping
                    </option>

                </select>

                <select class="filter-select">

                    <option>
                        All Status
                    </option>

                    <option>
                        Active
                    </option>

                    <option>
                        In Progress
                    </option>

                    <option>
                        Pending
                    </option>

                </select>

            </div>


            <!-- =================================================
                 CLIENT TABLE
                 ================================================= -->

            <div class="client-table-card">

                <table class="client-table">

                    <thead>

                        <tr>

                            <th>
                                CLIENT
                            </th>

                            <th>
                                CONTACT
                            </th>

                            <th>
                                SERVICE
                            </th>

                            <th>
                                STATUS
                            </th>

                            <th>
                                ACTION
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($clients as $client): ?>

                        <tr>

                            <td>

                                <div class="client-name">

                                    <div class="client-avatar">
                                        <?= htmlspecialchars(
                                            strtoupper(
                                                substr($client['name'], 0, 2)
                                            )
                                        ) ?>
                                    </div>

                                    <div>

                                        <strong>
                                            <?= htmlspecialchars($client['name']) ?>
                                        </strong>

                                        <small>
                                            <?= htmlspecialchars($client['id']) ?>
                                        </small>

                                    </div>

                                </div>

                            </td>


                            <td>

                                <div class="contact-info">

                                    <span>
                                        <?= htmlspecialchars($client['email']) ?>
                                    </span>

                                    <small>
                                        <?= htmlspecialchars($client['phone']) ?>
                                    </small>

                                </div>

                            </td>


                            <td>
                                <?= htmlspecialchars($client['service']) ?>
                            </td>


                            <td>

                                <span class="status-pill">

                                    <?= htmlspecialchars($client['status']) ?>

                                </span>

                            </td>


                            <td>

                                <button class="table-action">
                                    View
                                </button>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

</body>

</html>