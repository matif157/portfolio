<?php
require __DIR__.'/../bootstrap.php';
$classes=['App\\Core\\Database','App\\Core\\Router','App\\Core\\Auth','App\\Services\\ActivityService','App\\Services\\WorkflowEngine','App\\Services\\RequirementEngine','App\\Services\\InvoiceService','App\\Controllers\\AuthController'];foreach($classes as $c){if(!class_exists($c))throw new RuntimeException('Missing class '.$c);}echo "Class smoke checks passed\n";
