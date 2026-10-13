<?php
require __DIR__.'/../bootstrap/env.php';
spl_autoload_register(function($c){
    if(str_starts_with($c,'App\\')){
        $f=__DIR__.'/../app/'.str_replace('\\','/',substr($c,4)).'.php';
        if(is_file($f))require $f;
    }
});
$result=(new App\Services\AutomationRunnerService(App\Support\Database::pdo()))->run();
echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;
