<?php
declare(strict_types=1); namespace App\Services;
use App\Integrations\EmailGateway;
class CommunicationService { public function __construct(private EmailGateway $email){} public function sendEmail(string $to,string $subject,string $body):array{return $this->email->send($to,$subject,$body);} }
