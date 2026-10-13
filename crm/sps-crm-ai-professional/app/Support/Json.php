<?php
namespace App\Support;
final class Json {
 public static function encode(mixed $v): string { return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
 public static function decode(?string $v,array $default=[]): array { if(!$v)return $default; try{$x=json_decode($v,true,512,JSON_THROW_ON_ERROR);return is_array($x)?$x:$default;}catch(\Throwable){return $default;} }
}
