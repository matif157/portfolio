<?php
declare(strict_types=1);

namespace App\Core;

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\CrudController;
use App\Controllers\SettingsController;
use App\Controllers\InstallController;
use App\Services\Installer;

class Application
{
    public Container $container;
    public Router $router;

    public function __construct(string $root, array $config)
    {
        $this->container = new Container();
        $this->container->set('root', $root);
        $this->container->set('config', $config);
        $this->container->set('db', new Database($config['database']));
        $this->container->set('installer', new Installer($root));

        $logger = new Logger($root . '/storage/logs');
        $this->container->set('logger', $logger);
        (new ErrorHandler($logger, (bool) $config['debug']))->register();

        Url::setBasePath($config['base_path']);
        Session::start();

        $this->container->set('view', new View($root));
        $this->router = new Router();
        $this->routes();
    }

    private function routes(): void
    {
        $install = new InstallController($this->container);
        $this->router->get('/install', [$install, 'show']);
        $this->router->post('/install', [$install, 'run']);

        $auth = new AuthController($this->container);
        $this->router->get('/', function (): void {
            if (!Auth::check()) {
                Response::redirect(Url::to('login'));
            }
            Response::redirect(Url::to('dashboard'));
        });
        $this->router->get('/login', [$auth, 'show']);
        $this->router->post('/login', [$auth, 'login']);
        $this->router->get('/logout', [$auth, 'logout']);

        $dash = new DashboardController($this->container);
        $this->router->get('/dashboard', [$dash, 'index']);

        $settings = new SettingsController($this->container);
        $this->router->get('/configure', [$settings, 'show']);
        $this->router->post('/configure', [$settings, 'save']);
        $this->router->get('/settings', [$settings, 'show']);
        $this->router->post('/settings', [$settings, 'save']);

        $crud = new CrudController($this->container);
        foreach ([
            'clients', 'services', 'cases', 'projects', 'invoices', 'payments',
            'documents', 'tasks', 'comments', 'notifications', 'reports', 'automation',
        ] as $module) {
            $this->router->get('/' . $module, static function ($request) use ($crud, $module): void {
                $crud->index($request, $module);
            });
        }
    }

    public function run(): void
    {
        $request = new Request();
        $path = $request->path();
        $installer = $this->container->get('installer');

        if (!$installer->isInstalled() && !in_array($path, ['/install'], true)) {
            Response::redirect(Url::to('install'));
        }

        $this->router->dispatch($request);
    }
}
