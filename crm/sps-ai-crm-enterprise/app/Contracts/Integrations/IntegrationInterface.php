<?php
namespace App\Contracts\Integrations;
interface IntegrationInterface { public function name():string; public function health():array; public function execute(string $operation,array $payload=[]):array; }
