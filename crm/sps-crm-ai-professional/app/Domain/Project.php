<?php
namespace App\Domain;
final class Project { public function __construct(public readonly int $id,public readonly array $attributes=[]){} public function get(string $key,mixed $default=null):mixed{return $this->attributes[$key]??$default;} }
