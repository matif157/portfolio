<?php
declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, $handler, array $middleware): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'middleware');
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Strip base path (e.g. /SPS_CRM_Phase1)
        $base = Config::get('app.base_url', '');
        if ($base !== '' && strpos($path, $base) === 0) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) continue;

            $pattern = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (!preg_match($pattern, $path, $matches)) continue;

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            // Middleware
            foreach ($route['middleware'] as $mw) {
                if ($mw === 'auth' && !Auth::check()) {
                    redirect('/login');
                }
                if ($mw === 'guest' && Auth::check()) {
                    redirect('/dashboard');
                }
            }

            $handler = $route['handler'];
            if (is_string($handler) && strpos($handler, '@') !== false) {
                [$class, $action] = explode('@', $handler, 2);
                $fqcn = 'App\\Controllers\\' . $class;
                if (!class_exists($fqcn)) {
                    http_response_code(500);
                    die("Controller not found: $fqcn");
                }
                $controller = new $fqcn();
                call_user_func_array([$controller, $action], $params);
                return;
            }

            if (is_callable($handler)) {
                call_user_func_array($handler, $params);
                return;
            }
        }

        http_response_code(404);
        View::render('errors/404', ['title' => 'Not Found']);
    }
}
