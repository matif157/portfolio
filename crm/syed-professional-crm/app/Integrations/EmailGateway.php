<?php
declare(strict_types=1); namespace App\Integrations;
class EmailGateway { public function send(string $to,string $subject,string $body):array{if(!(getenv('MAIL_ENABLED')?:false))return ['sent'=>false,'status'=>'disabled','message'=>'Email provider is not configured.'];return ['sent'=>false,'status'=>'not_implemented','message'=>'Configure a provider adapter before production use.'];} }
