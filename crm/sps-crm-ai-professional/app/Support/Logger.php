<?php
namespace App\Support;
final class Logger {
 public static function error(Throwable $e):void{file_put_contents(__DIR__.'/../../storage/logs/app.log',date('c').' '.$e->getMessage().PHP_EOL,FILE_APPEND);}
}
