<?php
namespace App\Support;
final class Config { private static array $cache=[]; public static function get(string $file):array{return self::$cache[$file]??=require __DIR__.'/../../config/'.$file.'.php';} }
