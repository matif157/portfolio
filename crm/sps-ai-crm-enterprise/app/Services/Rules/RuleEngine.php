<?php
namespace App\Services\Rules;
final class RuleEngine { public function evaluate(array $rules,array $context):array{$matched=[];foreach($rules as $rule){$ok=true;foreach(($rule['when']??[]) as $k=>$expected){if(($context[$k]??null)!==$expected){$ok=false;break;}}if($ok)$matched[]=$rule;}return $matched;} }
