<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/app.php';
if (!class_exists(App\Core\Application::class)) throw new RuntimeException('Application class missing');
if (!class_exists(App\Services\Orchestrator::class)) throw new RuntimeException('Orchestrator class missing');
echo "SPS CRM smoke test passed\n";
