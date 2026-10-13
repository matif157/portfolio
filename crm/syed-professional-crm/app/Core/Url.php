<?php
declare(strict_types=1); namespace App\Core;
class Url { private static string $base=''; public static function setBasePath(string $base):void{self::$base=rtrim($base,'/');} public static function base():string{return self::$base;} public static function to(string $path=''):string{return self::$base.'/'.ltrim($path,'/');} public static function asset(string $path):string{return self::to($path);} }
