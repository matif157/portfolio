<?php
namespace App\Events;
final class ClientInactive extends DomainEvent { public function __construct(array $payload=[],string $source='system'){parent::__construct('ClientInactive',$payload,$source,date('c'));} }
