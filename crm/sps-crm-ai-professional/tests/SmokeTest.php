<?php
// Run with: php tests/SmokeTest.php
require __DIR__.'/../bootstrap/env.php';
spl_autoload_register(function($c){if(str_starts_with($c,'App\\')){$f=__DIR__.'/../app/'.str_replace('\\','/',substr($c,4)).'.php';if(is_file($f))require $f;}});
$classes=['App\\Support\\Database','App\\Support\\Auth','App\\Support\\Csrf','App\\Support\\Router','App\\Services\\ActivityService','App\\Services\\WorkflowService'];
foreach($classes as $c){if(!class_exists($c))throw new RuntimeException("Missing $c");}
echo "Smoke class checks passed.\n";
