<?php
namespace App\Services\AI;
final class AIProviderManager { public function provider():AIProviderInterface{return new NullAIProvider();} }
