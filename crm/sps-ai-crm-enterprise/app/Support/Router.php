<?php
namespace App\Support;
final class Router {
 private array $routes=[];
 public function get(string $p,callable $h):void{$this->routes[]=['GET',$p,$h];}
 public function post(string $p,callable $h):void{$this->routes[]=['POST',$p,$h];}
 public function dispatch(string $method,string $path):void{
  foreach($this->routes as [$m,$pattern,$h]){
   $rx='#^'.preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#','(?P<$1>[^/]+)',trim($pattern,'/')).'$#';
   if($m===$method&&preg_match($rx,trim($path,'/'),$matches)){ $args=[];foreach($matches as $k=>$v)if(!is_int($k))$args[]=$v;call_user_func_array($h,$args);return;}
  }
  http_response_code(404); View::render('errors/404');
 }
}
