<?php
declare(strict_types=1); namespace App\Core;
class Logger { public function __construct(private string $dir){if(!is_dir($dir))mkdir($dir,0775,true);} public function info(string $m,array $c=[]):void{$this->write('INFO',$m,$c);} public function error(string $m,array $c=[]):void{$this->write('ERROR',$m,$c);} private function write(string $level,string $m,array $c):void{$line=date('c')." [$level] $m ".json_encode($c).PHP_EOL;file_put_contents($this->dir.'/app.log',$line,FILE_APPEND|LOCK_EX);} }
