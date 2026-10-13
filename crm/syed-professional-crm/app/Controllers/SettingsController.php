<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Services\Installer;

class SettingsController
{
    public function __construct(private Container $container)
    {
    }

    public function show(): void
    {
        Auth::requireLogin();
        $this->assertAdmin();

        /** @var Installer $installer */
        $installer = $this->container->get('installer');

        $this->container->get('view')->display('configure/index', [
            'title' => 'Configure CRM',
            'settings' => $installer->currentSettings(),
            'requirements' => $installer->requirements(),
            'error' => '',
            'success' => (string) (Session::pullFlash('configure_success') ?? ''),
            'db_test' => null,
        ]);
    }

    public function save(Request $request): void
    {
        Auth::requireLogin();
        $this->assertAdmin();

        /** @var Installer $installer */
        $installer = $this->container->get('installer');
        $action = (string) $request->input('action', 'save');
        $payload = $request->all();
        $payload['app_debug'] = !empty($payload['app_debug']);
        $payload['session_secure'] = !empty($payload['session_secure']);

        try {
            if ($action === 'test_db') {
                $result = $installer->testDatabase([
                    'host' => (string) ($payload['db_host'] ?? '127.0.0.1'),
                    'port' => (string) ($payload['db_port'] ?? '3306'),
                    'database' => (string) ($payload['db_database'] ?? 'sps_professional_crm'),
                    'username' => (string) ($payload['db_username'] ?? 'root'),
                    'password' => (string) ($payload['db_password'] ?? ''),
                    'charset' => 'utf8mb4',
                ]);

                $this->container->get('view')->display('configure/index', [
                    'title' => 'Configure CRM',
                    'settings' => $payload + $installer->currentSettings(),
                    'requirements' => $installer->requirements(),
                    'error' => $result['ok'] ? '' : $result['message'],
                    'success' => $result['ok'] ? $result['message'] : '',
                    'db_test' => $result,
                ]);
                return;
            }

            if (trim((string) ($payload['admin_password'] ?? '')) === '') {
                $payload['_keep_password'] = true;
            }

            $installer->saveSettings($payload);
            Session::flash(
                'configure_success',
                'Configuration saved. .env, storage/config/local.php and public/.htaccess were updated for this server.'
            );
            Response::redirect(Url::to('configure'));
        } catch (\Throwable $e) {
            $this->container->get('view')->display('configure/index', [
                'title' => 'Configure CRM',
                'settings' => $payload + $installer->currentSettings(),
                'requirements' => $installer->requirements(),
                'error' => $e->getMessage(),
                'success' => '',
                'db_test' => null,
            ]);
        }
    }

    private function assertAdmin(): void
    {
        $role = strtolower(Auth::role());
        if (!in_array($role, ['admin', 'administrator'], true)) {
            http_response_code(403);
            echo 'Admin access required to configure the CRM.';
            exit;
        }
    }
}
