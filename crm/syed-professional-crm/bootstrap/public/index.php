<?php

/**
 * ============================================================
 * SYED PROFESSIONAL SERVICES CRM
 * PUBLIC ENTRY POINT
 * ============================================================
 */

$projectRoot = dirname(__DIR__);

/*
|--------------------------------------------------------------------------
| Bootstrap
|--------------------------------------------------------------------------
*/

$app = require_once $projectRoot . '/bootstrap/app.php';


/*
|--------------------------------------------------------------------------
| Session
|--------------------------------------------------------------------------
*/

\App\Core\Session::start();


/*
|--------------------------------------------------------------------------
| Request URI
|--------------------------------------------------------------------------
*/

$requestUri = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
);

$requestUri = is_string($requestUri)
    ? $requestUri
    : '/';


/*
|--------------------------------------------------------------------------
| Application Base Path
|--------------------------------------------------------------------------
*/

$basePath = \App\Core\Url::base();

if (
    $basePath !== ''
    && str_starts_with($requestUri, $basePath)
) {
    $requestUri = substr(
        $requestUri,
        strlen($basePath)
    );
}


/*
|--------------------------------------------------------------------------
| Route
|--------------------------------------------------------------------------
*/

$route = trim($requestUri, '/');


/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

if ($route === 'login') {

    $controller = new \App\Controllers\AuthController();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $controller->login();

        exit;
    }

    $controller->showLogin();

    exit;
}


/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

if ($route === 'logout') {

    $controller = new \App\Controllers\AuthController();

    $controller->logout();

    exit;
}


/*
|--------------------------------------------------------------------------
| CLIENTS
|--------------------------------------------------------------------------
*/

if ($route === 'clients') {

    $controller = new \App\Controllers\ClientController();

    $controller->index();

    exit;
}


/*
|--------------------------------------------------------------------------
| CREATE CLIENT
|--------------------------------------------------------------------------
*/

if ($route === 'clients/create') {

    $controller = new \App\Controllers\ClientController();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $controller->store();

        exit;
    }

    $controller->create();

    exit;
}


/*
|--------------------------------------------------------------------------
| DASHBOARD
|--------------------------------------------------------------------------
*/

if ($route === '' || $route === 'dashboard') {

    \App\Core\Auth::requireLogin();

    $app->run();

    exit;
}


/*
|--------------------------------------------------------------------------
| 404
|--------------------------------------------------------------------------
*/

http_response_code(404);

echo '<!DOCTYPE html>';
echo '<html lang="en">';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>404 - SPS CRM</title>';
echo '</head>';
echo '<body style="font-family:Arial,sans-serif;padding:50px;">';
echo '<h1>404</h1>';
echo '<p>The requested page could not be found.</p>';
echo '</body>';
echo '</html>';