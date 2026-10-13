<?php
namespace App\Validators;
use App\Support\Validator;
final class ProjectValidator {
 public function validate(array $data):array{return (new Validator)->required($data,["client_id", "name", "start_date", "end_date"])->errors();}
}
