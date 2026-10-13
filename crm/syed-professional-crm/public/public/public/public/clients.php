<?php
/**
 * =====================================================
 * FILE: clients.php
 * PURPOSE: SPS CRM clients listing
 *
 * WORKFLOW:
 *
 * Database
 *    ↓
 * Client search
 *    ↓
 * Pagination
 *    ↓
 * Client list
 *    ↓
 * Client profile
 *    ↓
 * Start service
 *
 * IMPORTANT:
 * 7,000 clients hon to bhi hum ek page par limited
 * records load karenge. Ye performance ke liye important hai.
 * =====================================================
 */

require_once dirname(__DIR__) . '/config/config.php';

require_login();


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search =
    trim($_GET['search'] ?? '');


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$page =
    max(
        1,
        (int) ($_GET['page'] ?? 1)
    );


$perPage = 25;

$offset =
    ($page - 1) * $perPage;


$clients = [];

$totalClients = 0;


try {

    /*
     * Search condition dynamically prepare karenge.
     */

    if ($search !== '') {

        $countStmt = db()->prepare(
            "SELECT COUNT(*)
             FROM clients
             WHERE first_name LIKE ?
                OR last_name LIKE ?
                OR email LIKE ?
             "
        );


        $like =
            '%' . $search . '%';


        $countStmt->execute([
            $like,
            $like,
            $like
        ]);


        $totalClients =
            (int) $countStmt->fetchColumn();


        /*
         * Actual page records.
         */

        $stmt = db()->prepare(
            "SELECT *
             FROM clients
             WHERE first_name LIKE ?
                OR last_name LIKE ?
                OR email LIKE ?
             ORDER BY id DESC
             LIMIT ? OFFSET ?"
        );


        $stmt->bindValue(
            1,
            $like,
            PDO::PARAM_STR
        );

        $stmt->bindValue(
            2,
            $like,
            PDO::PARAM_STR
        );

        $stmt->bindValue(
            3,
            $like,
            PDO::PARAM_STR
        );

        $stmt->bindValue(
            4,
            $perPage,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            5,
            $offset,
            PDO::PARAM_INT
        );

        $stmt->execute();

        $clients =
            $stmt->fetchAll();

    } else {

        /*
         * Search nahi hai.
         */

        $totalClients =
            (int) db()
                ->query(
                    "SELECT COUNT(*) FROM clients"
                )
                ->fetchColumn();


        $stmt = db()->prepare(
            "SELECT *
             FROM clients
             ORDER BY id DESC
             LIMIT ? OFFSET ?"
        );


        $stmt->bindValue(
            1,
            $perPage,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            2,
            $offset,
            PDO::PARAM_INT
        );

        $stmt->execute();

        $clients =
            $stmt->fetchAll();
    }

} catch (Throwable $exception) {

    /*
     * Agar current database table structure different hai,
     * page crash karne ke bajaye error show karega.
     */

    $error =
        $exception->getMessage();
}


$totalPages =
    max(
        1,
        (int) ceil(
            $totalClients / $perPage
        )
    );

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
        Clients | SPS CRM
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

            <div>
                <strong>SPS CRM</strong>
                <small>Operations</small>
            </div>

        </div>


        <nav>

            <a
                class="nav-link"
                href="<?= APP_URL ?>/index.php"
            >
                ▦ Dashboard
            </a>

            <a
                class="nav-link active"
                href="<?= APP_URL ?>/clients.php"
            >
                ◉ Clients
            </a>

            <a
                class="nav-link"
                href="<?= APP_URL ?>/service.php"
            >
                ◆ File a Service
            </a>

        </nav>


        <div class="sidebar-bottom">

            <a
                class="logout-link"
                href="<?= APP_URL ?>/logout.php"
            >
                Sign out
            </a>

        </div>

    </aside>


    <main class="main-content">

        <header class="topbar">

            <div>

                <span class="eyebrow">
                    CLIENT MANAGEMENT
                </span>

                <h1>
                    Clients
                </h1>

                <p class="muted">
                    <?= number_format($totalClients) ?>
                    client records
                </p>

            </div>

        </header>


        <section class="dashboard-section">

            <form
                class="search-bar"
                method="GET"
            >

                <input
                    type="search"
                    name="search"
                    value="<?= e($search) ?>"
                    placeholder="Search client name or email..."
                >

                <button
                    class="btn btn-primary"
                    type="submit"
                >
                    Search
                </button>

            </form>


            <?php if (isset($error)): ?>

                <div class="alert alert-danger">
                    <?= e($error) ?>
                </div>

            <?php endif; ?>


            <div class="table-card">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Client
                            </th>

                            <th>
                                Email
                            </th>

                            <th>
                                Phone
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (!$clients): ?>

                        <tr>

                            <td colspan="4">

                                <div class="empty-state">

                                    No client records found.

                                </div>

                            </td>

                        </tr>

                    <?php endif; ?>


                    <?php foreach ($clients as $client): ?>

                        <tr>

                            <td>

                                <strong>

                                    <?= e(
                                        trim(
                                            ($client['first_name'] ?? '')
                                            . ' '
                                            . ($client['last_name'] ?? '')
                                        )
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <?= e(
                                    $client['email']
                                    ?? '—'
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $client['phone']
                                    ?? '—'
                                ) ?>

                            </td>


                            <td>

                                <a
                                    class="table-action"
                                    href="<?= APP_URL ?>/client.php?id=<?= (int) $client['id'] ?>"
                                >
                                    View →
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


            <!-- PAGINATION -->

            <?php if ($totalPages > 1): ?>

                <div class="pagination">

                    <?php for (
                        $i = 1;
                        $i <= $totalPages;
                        $i++
                    ): ?>

                        <a
                            class="<?= $i === $page ? 'active' : '' ?>"
                            href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"
                        >
                            <?= $i ?>
                        </a>

                    <?php endfor; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>

</html>