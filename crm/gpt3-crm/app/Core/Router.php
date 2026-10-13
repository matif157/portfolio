<?php
namespace App\Core;
final class Router {
    private array $routes=[];
    public function get(string $pattern, callable $handler): void { $this->routes[]=['GET',$pattern,$handler]; }
    public function post(string $pattern, callable $handler): void { $this->routes[]=['POST',$pattern,$handler]; }
    public function dispatch(string $method, string $path): void {
        $path=trim($path,'/');
        foreach($this->routes as [$m,$p,$h]){
            $rx='#^'.preg_replace('#\{([a-zA-Z_]\w*)\}#','(?P<$1>[^/]+)',trim($p,'/')).'$#';
            if($m===$method && preg_match($rx,$path,$match)){ $args=[]; foreach($match as $k=>$v) if(!is_int($k))$args[]=$v; call_user_func_array($h,$args); return; }
        }
        http_response_code(404); View::render('errors/404');
    }
}
