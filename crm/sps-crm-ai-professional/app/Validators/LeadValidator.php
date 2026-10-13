<?php
namespace App\Validators;
use App\Support\Validator;
final class LeadValidator {
 public function validate(array $data):array{return (new Validator)->required($data,["name"])->errors();}
}
