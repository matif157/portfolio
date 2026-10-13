<?php
require __DIR__.'/app/Core/Env.php'; App\Core\Env::load(__DIR__.'/.env'); require __DIR__.'/config/constants.php'; require __DIR__.'/config/app.php';
spl_autoload_register(function($class){if(!str_starts_with($class,'App\\'))return;$file=__DIR__.'/app/'.str_replace('\\','/',substr($class,4)).'.php';if(is_file($file))require $file;});
require __DIR__.'/app/Core/Helpers.php';
App\Core\Session::start(); App\Core\ErrorHandler::register();
