<?php
namespace App\Validators;
use App\Support\Validator;
final class ServiceValidator {
 public function validate(array $data):array{return (new Validator)->required($data,["name"])->errors();}
}
