<?php
declare(strict_types=1);

namespace App\Core;

class View
{
    public static function render(string $name, array $data = []): void
    {
        $file = VIEW_PATH . '/' . str_replace('.', '/', $name) . '.php';
        if (!is_file($file)) {
            http_response_code(500);
            die('View not found: ' . htmlspecialchars($name));
        }

        // Auth views and print views without main layout
        $noLayout = in_array($name, ['auth/login', 'invoices/print', 'payments/receipt', 'errors/404', 'errors/403'], true)
            || str_ends_with($name, '/print')
            || str_ends_with($name, '/receipt');

        extract($data, EXTR_SKIP);
        $user = Auth::user();
        $appName = Config::get('app.name', 'SPS CRM');

        if ($noLayout) {
            require $file;
            return;
        }

        // Capture view content
        ob_start();
        require $file;
        $content = ob_get_clean();

        require VIEW_PATH . '/layouts/main.php';
    }
}
