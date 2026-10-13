<?php namespace App\Integrations\Contract; interface PaymentGatewayInterface { public function createCheckout(array $payload): array; public function verify(string $reference): array; }
