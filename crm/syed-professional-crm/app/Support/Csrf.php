<?php
declare(strict_types=1); namespace App\Support;
use App\Core\Session;
class Csrf { public static function token():string{if(!Session::get('_csrf'))Session::set('_csrf',bin2hex(random_bytes(32)));return (string)Session::get('_csrf');} public static function verify(?string $token):bool{return is_string($token)&&hash_equals((string)Session::get('_csrf',''),$token);} }
