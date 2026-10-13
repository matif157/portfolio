<?php
namespace App\Support;
final class Arr { public static function get(array $a,string $key,mixed $default=null):mixed{foreach(explode('.',$key) as $k){if(!is_array($a)||!array_key_exists($k,$a))return $default;$a=$a[$k];}return $a;} public static function only(array $a,array $keys):array{return array_intersect_key($a,array_flip($keys));} }
