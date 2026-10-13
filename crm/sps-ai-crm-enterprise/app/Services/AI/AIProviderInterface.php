<?php
namespace App\Services\AI;
interface AIProviderInterface { public function complete(string $instruction,array $context=[]):array; public function classify(string $text,array $labels=[]):array; }
