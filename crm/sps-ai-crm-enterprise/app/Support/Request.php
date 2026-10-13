<?php
namespace App\Support;
final class Request {
 public function input(string $key,mixed $default=null):mixed{return $_POST[$key]??$_GET[$key]??$default;}
 public function all():array{return array_merge($_GET,$_POST);}
 public function json():array{return json_decode(file_get_contents('php://input'),true)?:[];}
 public function isPost():bool{return $_SERVER['REQUEST_METHOD']==='POST';}
}
