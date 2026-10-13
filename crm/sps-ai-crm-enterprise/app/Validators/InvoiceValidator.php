<?php
namespace App\Validators;
use App\Support\Validator;
final class InvoiceValidator {
 public function validate(array $data):array{return (new Validator)->required($data,["client_id", "number"])->errors();}
}
