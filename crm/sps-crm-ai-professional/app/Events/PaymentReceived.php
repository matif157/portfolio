<?php
namespace App\Events;
final class PaymentReceived extends DomainEvent { public function __construct(array $payload=[],string $source='system'){parent::__construct('PaymentReceived',$payload,$source,date('c'));} }
