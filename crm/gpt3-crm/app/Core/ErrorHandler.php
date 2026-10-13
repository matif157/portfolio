<?php
namespace App\Core;
final class ErrorHandler {
    public static function register(): void {
        set_exception_handler(function(\Throwable $e){
            $line='['.date('c').'] '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine().PHP_EOL;
            @file_put_contents(APP_ROOT.'/storage/logs/app.log',$line,FILE_APPEND);
            http_response_code(500);
            if ((require APP_ROOT.'/config/app.php')['debug']) echo '<pre>'.htmlspecialchars((string)$e).'</pre>'; else View::render('errors/500');
        });
    }
}
