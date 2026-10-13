<?php
namespace App\Support;
use App\Repositories\UserRepository;
final class Auth {
 public static function user():?array{$id=Session::get('user_id');return $id?(new UserRepository)->find((int)$id):null;}
 public static function id():?int{$id=Session::get('user_id');return $id?(int)$id:null;}
 public static function check():bool{return self::id()!==null;}
 public static function login(array $u):void{session_regenerate_id(true);Session::set('user_id',(int)$u['id']);Session::set('tenant_id',(int)$u['tenant_id']);}
 public static function logout():void{Session::start();$_SESSION=[];session_destroy();}
 public static function requireLogin():void{if(!self::check()){header('Location: /login');exit;}}
}
