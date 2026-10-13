<?php
require __DIR__.'/../bootstrap.php';$r=new App\Core\Router;(require APP_ROOT.'/routes/web.php')($r);echo "Routes loaded successfully.\n";
