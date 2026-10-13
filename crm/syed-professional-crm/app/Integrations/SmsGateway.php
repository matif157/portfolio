<?php
declare(strict_types=1); namespace App\Integrations;
class SmsGateway { public function send(string $to,string $message):array{return ['sent'=>false,'status'=>'disabled','provider'=>getenv('SMS_PROVIDER')?:'none'];} }
