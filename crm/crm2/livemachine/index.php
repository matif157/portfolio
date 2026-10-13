<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/Auth.php';

$route = $_GET['route'] ?? 'dashboard';
$valid = ['dashboard','employees','attendance','reports','workinghours','payroll','leaves','holidays','salary','settings','login','logout'];
if (!in_array($route, $valid, true)) $route = 'dashboard';

if ($route === 'logout') {
    Auth::logout();
    header('Location: ?route=login'); exit;
}
if ($route === 'login') {
    require __DIR__ . '/views/login.php'; exit;
}

Auth::requireLogin();
$view = __DIR__ . "/views/{$route}.php";
if (!is_file($view)) { http_response_code(404); echo 'Not found'; exit; }
require $view;
