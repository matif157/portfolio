<?php
namespace App\Validators;
use App\Support\Validator;
final class ClientValidator {
 public function validate(array $data):array{return (new Validator)->required($data,["first_name", "last_name"])->errors();}
}
