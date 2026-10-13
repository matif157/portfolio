<?php namespace App\Integrations\Contract; interface GovernmentFilingInterface { public function submit(array $payload): array; public function status(string $reference): array; }
