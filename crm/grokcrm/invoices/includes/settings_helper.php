<?php
/**
 * Settings Helper — loads Customizer values
 * Include after db.php / functions.php
 */

function get_setting(string $key, $default = '')
{
    global $pdo;
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        try {
            $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
            foreach ($rows as $r) {
                $cache[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Exception $e) {
            // settings table may not exist yet
        }
    }

    return $cache[$key] ?? $default;
}

function get_company_info(): array
{
    return [
        'name'    => get_setting('company_name', 'Syed Professional Services'),
        'phone'   => get_setting('company_phone', '(718) 859-5392'),
        'fax'     => get_setting('company_fax', '(718) 859-2237'),
        'email'   => get_setting('company_email', 'invoice@syedpro.com'),
        'website' => get_setting('company_website', 'www.syedpro.com'),
        'street'  => get_setting('company_street', '1012 Coney Island Avenue'),
        'city'    => get_setting('company_city', 'BROOKLYN'),
        'state'   => get_setting('company_state', 'NY'),
        'zip'     => get_setting('company_zip', '11230'),
    ];
}

function invoice_status_label(int $status): string
{
    $map = [1 => 'UNPAID', 2 => 'PARTIAL', 3 => 'PAID', 4 => 'CANCELLED'];
    return $map[$status] ?? 'UNKNOWN';
}

function invoice_status_color(int $status): string
{
    $map = [1 => '#dc3545', 2 => '#fd7e14', 3 => '#198754', 4 => '#6c757d'];
    return $map[$status] ?? '#6c757d';
}
