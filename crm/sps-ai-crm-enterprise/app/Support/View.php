<?php
namespace App\Support;
final class View { public static function render(string $view,array $data=[]):void{extract($data);require __DIR__.'/../../resources/views/'.$view.'.php';} public static function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');} }
