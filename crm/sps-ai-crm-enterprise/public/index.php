<?php
require __DIR__.'/../bootstrap/env.php';
spl_autoload_register(function($class){if(!str_starts_with($class,'App\\'))return;$file=__DIR__.'/../app/'.str_replace('\\','/',substr($class,4)).'.php';if(is_file($file))require $file;});
App\Support\Session::start();
$router=new App\Support\Router();
(require __DIR__.'/../routes/web.php')($router);
try{$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$base=rtrim(dirname($_SERVER['SCRIPT_NAME']),'/');if($base&&$base!=='/'&&str_starts_with($path,$base))$path=substr($path,strlen($base))?:'/';$router->dispatch($_SERVER['REQUEST_METHOD'],$path);}
catch(Throwable $e){App\Support\Logger::error($e);http_response_code(500);if((require __DIR__.'/../config/app.php')['debug'])echo '<pre>'.htmlspecialchars((string)$e).'</pre>';else echo 'Internal Server Error';}
