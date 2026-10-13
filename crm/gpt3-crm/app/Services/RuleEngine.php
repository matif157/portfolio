<?php
namespace App\Services;
final class RuleEngine {
 public function matches(array $rule,array $context):bool{
  foreach(($rule['all']??[]) as $condition){$field=$condition['field']??null;$op=$condition['op']??'eq';$want=$condition['value']??null;$have=$context[$field]??null;if(!$this->compare($have,$op,$want))return false;}
  return true;
 }
 private function compare(mixed $a,string $op,mixed $b):bool{return match($op){'eq'=>(string)$a===(string)$b,'neq'=>(string)$a!==(string)$b,'gt'=>(float)$a>(float)$b,'gte'=>(float)$a>=(float)$b,'lt'=>(float)$a<(float)$b,'lte'=>(float)$a<=(float)$b,'contains'=>str_contains(strtolower((string)$a),strtolower((string)$b)), 'in'=>in_array($a,(array)$b,true),default=>false};}
}
