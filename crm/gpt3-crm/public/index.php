<?php
// PHP's built-in server uses this file as a router. Return false for real static files.
$requestedPath=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH) ?: '/';
if($requestedPath!=='/' && $requestedPath!=='/index.php'){
    $candidate=__DIR__.$requestedPath;
    if(is_file($candidate)) return false;
}
require __DIR__.'/../bootstrap.php';
use App\Core\Router;
$router=new Router();(require APP_ROOT.'/routes/web.php')($router);
$route=$_GET['route']??null;
if($route!==null){$path=str_starts_with($route,'/')?$route:'/'.ltrim($route,'/');}
else{
    $path=$requestedPath;
    $base=rtrim(dirname($_SERVER['SCRIPT_NAME']??'/'),'/');
    if($base&&$base!=='/'&&str_starts_with($path,$base))$path=substr($path,strlen($base))?:'/';
}
$router->dispatch($_SERVER['REQUEST_METHOD']??'GET',$path);
