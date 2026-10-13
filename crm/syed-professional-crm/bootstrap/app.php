<?php
declare(strict_types=1);
$projectRoot = dirname(__DIR__);
spl_autoload_register(function(string $class) use ($projectRoot): void {
    $prefix='App\\'; if (!str_starts_with($class,$prefix)) return;
    $file=$projectRoot.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if (is_file($file)) require_once $file;
});
require_once $projectRoot.'/config/config.php';
$app = new App\Core\Application($projectRoot, $config);
return $app;
