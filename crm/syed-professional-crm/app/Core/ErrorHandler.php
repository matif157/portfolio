<?php
declare(strict_types=1); namespace App\Core;
class ErrorHandler { public function __construct(private Logger $logger,private bool $debug){} public function register():void{set_exception_handler(function(\Throwable $e){$this->logger->error($e->getMessage(),['file'=>$e->getFile(),'line'=>$e->getLine()]);http_response_code(500);if($this->debug)echo '<h1>Application Error</h1><pre>'.htmlspecialchars((string)$e).'</pre>';else echo 'Application error.';});} }
