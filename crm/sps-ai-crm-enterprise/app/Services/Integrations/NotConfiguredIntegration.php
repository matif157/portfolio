<?php
namespace App\Services\Integrations;
use App\Contracts\Integrations\IntegrationInterface;
final class NotConfiguredIntegration implements IntegrationInterface { public function __construct(private string $integration){} public function name():string{return $this->integration;} public function health():array{return ['status'=>'not_configured','integration'=>$this->integration];} public function execute(string $operation,array $payload=[]):array{return ['status'=>'not_configured','operation'=>$operation,'payload'=>$payload];} }
