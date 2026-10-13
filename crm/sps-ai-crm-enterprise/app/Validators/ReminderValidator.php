<?php
namespace App\Validators;
use App\Support\Validator;
final class ReminderValidator {
 public function validate(array $data):array{return (new Validator)->required($data,["title", "start_at"])->errors();}
}
