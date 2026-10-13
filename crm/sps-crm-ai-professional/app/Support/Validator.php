<?php
namespace App\Support;
final class Validator {
 private array $errors=[];
 public function required(array $d,array $fields):self{foreach($fields as $f)if(!isset($d[$f])||trim((string)$d[$f])==='')$this->errors[$f]='Required';return $this;}
 public function email(array $d,string $f):self{if(!empty($d[$f])&&!filter_var($d[$f],FILTER_VALIDATE_EMAIL))$this->errors[$f]='Invalid email';return $this;}
 public function errors():array{return $this->errors;}
 public function valid():bool{return !$this->errors;}
}
