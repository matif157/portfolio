<?php
declare(strict_types=1); namespace App\Integrations;
class VoiceGateway { public function call(string $to,array $payload=[]):array{return ['started'=>false,'status'=>'disabled','provider'=>getenv('VOICE_PROVIDER')?:'none'];} }
