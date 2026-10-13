<?php
use App\Core\Session;
function app_config(): array { static $c; return $c ??= require APP_ROOT.'/config/app.php'; }
function app_base_url(): string {
    $configured=rtrim((string)(app_config()['url']??''),'/'); if($configured!=='')return $configured;
    $script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??'')); $dir=rtrim(str_replace('\\','/',dirname($script)),'/');
    return ($dir==='.'||$dir==='/')?'':$dir;
}
function asset(string $path): string { $base=app_base_url(); return ($base===''?'':$base).'/'.ltrim($path,'/'); }
function route(string $name,array $params=[]): string {
    $routes=require APP_ROOT.'/config/routes_map.php';$path=$routes[$name]??$name;
    foreach($params as $k=>$v)$path=str_replace('{'.$k.'}',rawurlencode((string)$v),$path);
    $base=app_base_url();return ($base===''?'':$base).'/index.php?route='.rawurlencode($path);
}
function old(string $key,mixed $default=''):mixed{return $_POST[$key]??$default;}
function flash(string $key):?string{return Session::pullFlash($key);}
function money(float $v):string{return '$'.number_format($v,2);}
function csrf_field():string{return App\Core\Csrf::input();}
function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
