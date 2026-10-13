<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Services\Installer;

class InstallController
{
    public function __construct(private Container $container)
    {
    }

    public function show(Request $request): void
    {
        /** @var Installer $installer */
        $installer = $this->container->get('installer');
        $config = $this->container->get('config');

        if ($installer->isInstalled() && !$request->input('force')) {
            Response::redirect(Url::to('configure'));
        }

        $this->container->get('view')->display('install/setup', [
            'title' => 'Install CRM',
            'requirements' => $installer->requirements(),
            'defaults' => [
                'app_name' => $config['name'] ?? 'Syed Professional Services CRM',
                'app_url' => $config['url'] ?? 'http://localhost/syed-professional-crm/public',
                'base_path' => $config['base_path'] ?: '/syed-professional-crm/public',
                'timezone' => $config['timezone'] ?? 'America/New_York',
                'company_name' => $config['company']['name'] ?? 'Syed Professional Services',
                'company_email' => $config['company']['email'] ?? 'admin@sps.local',
                'company_phone' => $config['company']['phone'] ?? '',
                'company_address' => $config['company']['address'] ?? '',
                'db_host' => $config['database']['host'] ?? '127.0.0.1',
                'db_port' => $config['database']['port'] ?? '3306',
                'db_database' => $config['database']['database'] ?? 'sps_professional_crm',
                'db_username' => $config['database']['username'] ?? 'root',
                'db_password' => $config['database']['password'] ?? '',
                'admin_name' => 'Administrator',
                'admin_email' => 'admin@sps.local',
                'admin_password' => 'admin123',
                'app_debug' => true,
                'mail_driver' => 'log',
                'mail_from' => 'noreply@sps.local',
            ],
            'error' => '',
            'success' => '',
        ]);
    }

    public function run(Request $request): void
    {
        /** @var Installer $installer */
        $installer = $this->container->get('installer');

        try {
            $installer->install($request->all());
            Response::redirect(Url::to('login'));
        } catch (\Throwable $e) {
            $this->container->get('view')->display('install/setup', [
                'title' => 'Install CRM',
                'requirements' => $installer->requirements(),
                'defaults' => $request->all() + [
                    'app_debug' => (bool) $request->input('app_debug'),
                ],
                'error' => $e->getMessage(),
                'success' => '',
            ]);
        }
    }
}
