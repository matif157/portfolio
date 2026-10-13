<?php
$env=__DIR__.'/../.env';
if(is_file($env)){
 foreach(file($env,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){
  $line=trim($line); if($line===''||str_starts_with($line,'#')||!str_contains($line,'=')) continue;
  [$k,$v]=explode('=',$line,2); $v=trim($v); $v=trim($v,'"\''); putenv("$k=$v");
 }
}
if(!function_exists('config')){function config(string $key,mixed $default=null):mixed{static $c=[];[$file,$path]=array_pad(explode('.',$key,2),2,null);$c[$file]??=require __DIR__.'/../config/'.$file.'.php';if($path===null)return $c[$file];$v=$c[$file];foreach(explode('.',$path) as $p){if(!is_array($v)||!array_key_exists($p,$v))return $default;$v=$v[$p];}return $v;}}
if(!function_exists('env')){function env(string $key,mixed $default=null):mixed{$v=getenv($key);return $v===false?$default:$v;}}
