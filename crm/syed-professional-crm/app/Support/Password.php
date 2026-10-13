<?php
declare(strict_types=1); namespace App\Support;
class Password { public static function hash(string $p):string{return password_hash($p,PASSWORD_DEFAULT);} public static function verify(string $p,string $h):bool{return password_verify($p,$h);} }
