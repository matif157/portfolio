<?php
namespace App\Validators;
use App\Support\Validator;
final class TaskValidator {
 public function validate(array $data):array{return (new Validator)->required($data,["title"])->errors();}
}
