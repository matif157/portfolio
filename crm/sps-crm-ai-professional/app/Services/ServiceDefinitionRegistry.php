<?php
namespace App\Services;
final class ServiceDefinitionRegistry { private array $definitions=[]; public function all():array{if($this->definitions)return $this->definitions;foreach(glob(__DIR__.'/../../config/services/catalog/*.php') as $file){$d=require $file;if(is_array($d)&&isset($d['code']))$this->definitions[$d['code']]=$d;}return $this->definitions;} public function find(string $code):?array{return $this->all()[$code]??null;} }
