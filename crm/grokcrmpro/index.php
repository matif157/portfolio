<?php
/**
 * SPS CRM — Front Controller
 * All requests enter here.
 */
declare(strict_types=1);

define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('VIEW_PATH', ROOT_PATH . '/resources/views');

// Error reporting (dev)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Autoload Core + App classes (deterministic, no Composer)
spl_autoload_register(function (string $class): void {
    $prefixes = [
        'App\\Core\\'         => APP_PATH . '/Core/',
        'App\\Controllers\\'  => APP_PATH . '/Controllers/',
        'App\\Repositories\\' => APP_PATH . '/Repositories/',
        'App\\Services\\'     => APP_PATH . '/Services/',
        'App\\Middleware\\'   => APP_PATH . '/Middleware/',
        'App\\Helpers\\'      => APP_PATH . '/Helpers/',
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) continue;
        $relative = str_replace('\\', '/', substr($class, $len));
        $file = $baseDir . $relative . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

// Load helpers
require_once APP_PATH . '/Helpers/functions.php';

// Bootstrap
use App\Core\Config;
use App\Core\Session;
use App\Core\Router;
use App\Core\Database;

Config::load(ROOT_PATH);
Session::start();
Database::connect();

// Routes
$router = new Router();
require ROOT_PATH . '/routes/web.php';

// Dispatch
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = $_SERVER['REQUEST_URI'] ?? '/';
$router->dispatch($method, $uri);
