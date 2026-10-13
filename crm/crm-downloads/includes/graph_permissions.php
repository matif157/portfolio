<?php
/**
 * Graph visibility permissions.
 *
 * - Admins (user_type == '1') always see every graph.
 * - For non-admin users: a graph is hidden unless an explicit row exists in
 *   `graph_permissions` granting access (graph_key, user_id).
 * - The helper auto-creates the `graph_permissions` table the first time it
 *   runs, so no external migration is needed.
 *
 * Requires the caller to have already included includes/db.php (so $con is
 * available) and includes/auth.php (so $_SESSION is populated).
 */

if (!function_exists('graph_perms_bootstrap')) {

    /**
     * Ensure the permissions table exists. Cheap — runs once per request and
     * MySQL is a no-op when the table is already there.
     */
    function graph_perms_bootstrap(mysqli $con): void
    {
        static $done = false;
        if ($done) return;
        $con->query("
            CREATE TABLE IF NOT EXISTS `graph_permissions` (
                `id`         INT NOT NULL AUTO_INCREMENT,
                `user_id`    INT NOT NULL,
                `graph_key`  VARCHAR(64) NOT NULL,
                `granted_by` INT NOT NULL,
                `granted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_user_graph` (`user_id`,`graph_key`),
                KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $done = true;
    }

    /**
     * Authoritative catalog of every graph that can be gated. Keys are stable
     * identifiers persisted in the DB; labels are shown in the admin matrix.
     */
    function graph_perms_catalog(): array
    {
        return [
            'Dashboard' => [
                'dash_kpi_revenue'        => 'KPI – Total Revenue',
                'dash_kpi_balance'        => 'KPI – Outstanding Balance',
                'dash_revenue'            => 'Monthly Revenue (12 mo)',
                'dash_client_status'      => 'Client Status doughnut',
                'dash_invoice_status'     => 'Invoice Status doughnut',
                'dash_project_status'     => 'Project Status pie',
                'dash_top_services'       => 'Revenue by Service (Top 8)',
                'dash_revenue_summary'    => 'Revenue vs Collected vs Outstanding',
            ],
            'General report' => [
                'general_graphs'          => 'General filter – all charts',
            ],
            'Invoices report' => [
                'invoice_graphs'          => 'Invoice filter – all charts',
            ],
            'Projects report' => [
                'project_graphs'          => 'Project filter – all charts',
            ],
            'Quotations report' => [
                'quotation_graphs'        => 'Quotation filter – all charts',
            ],
            'Payments report' => [
                'payment_graphs'          => 'Payment filter – all charts',
            ],
            'Comments report' => [
                'comment_graphs'          => 'Comment filter – all charts',
            ],
            'Reminders report' => [
                'reminder_graphs'         => 'Reminder filter – all charts',
            ],
        ];
    }

    /** Flat [key => label] view of the catalog. */
    function graph_perms_flat_catalog(): array
    {
        $flat = [];
        foreach (graph_perms_catalog() as $section => $items) {
            foreach ($items as $k => $l) $flat[$k] = $l;
        }
        return $flat;
    }

    /**
     * Load the set of graph keys the current request is allowed to see and
     * cache it for the duration of the request.
     */
    function graph_perms_for_current_user(mysqli $con): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        graph_perms_bootstrap($con);

        $is_admin = (($_SESSION['user_type'] ?? '') == '1');
        if ($is_admin) {
            // Admins see everything.
            $cache = ['__admin__' => true];
            return $cache;
        }

        $uid = (int)($_SESSION['user_id'] ?? 0);
        $cache = [];
        if ($uid > 0) {
            $rs = $con->query("SELECT graph_key FROM graph_permissions WHERE user_id = $uid");
            if ($rs) {
                while ($row = $rs->fetch_assoc()) {
                    $cache[$row['graph_key']] = true;
                }
            }
        }
        return $cache;
    }

    /**
     * Check whether the current user is allowed to see the named graph.
     */
    function canSeeGraph(mysqli $con, string $graph_key): bool
    {
        $perms = graph_perms_for_current_user($con);
        return !empty($perms['__admin__']) || !empty($perms[$graph_key]);
    }
}
