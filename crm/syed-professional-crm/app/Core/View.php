<?php
declare(strict_types=1);

namespace App\Core;

class View
{
    public function __construct(private string $root)
    {
    }

    public function display(string $view, array $data = []): void
    {
        echo $this->render($view, $data);
    }

    public function render(string $view, array $data = []): string
    {
        $file = $this->root . '/resources/views/' . str_replace('.', '/', $view) . '.php';

        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $view);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
