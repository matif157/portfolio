<?php
namespace App\Support;
final class Session {
 public static function start():void{
  if(session_status()!==PHP_SESSION_ACTIVE){
   $name=getenv('SESSION_NAME')?:'spscrm';
   session_name($name);
   session_set_cookie_params(['httponly'=>true,'secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'samesite'=>'Lax']);
   session_start();
  }
 }
 public static function get(string $k,mixed $d=null):mixed{self::start();return $_SESSION[$k]??$d;}
 public static function set(string $k,mixed $v):void{self::start();$_SESSION[$k]=$v;}
 public static function forget(string $k):void{self::start();unset($_SESSION[$k]);}
}
