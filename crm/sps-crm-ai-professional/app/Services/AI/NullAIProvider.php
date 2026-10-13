<?php
namespace App\Services\AI;
final class NullAIProvider implements AIProviderInterface { public function complete(string $instruction,array $context=[]):array{return ['text'=>'No external AI provider configured.','confidence'=>0.0,'provider'=>'none'];} public function classify(string $text,array $labels=[]):array{return ['label'=>$labels[0]??'unknown','confidence'=>0.0,'provider'=>'none'];} }
