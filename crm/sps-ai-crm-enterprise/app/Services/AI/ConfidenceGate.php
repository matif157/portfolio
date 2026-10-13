<?php
namespace App\Services\AI;
final class ConfidenceGate { public function requiresHuman(float $confidence,float $threshold=0.85):bool{return $confidence<$threshold;} }
