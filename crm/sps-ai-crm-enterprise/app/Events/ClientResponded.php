<?php
namespace App\Events;
final class ClientResponded extends DomainEvent { public function __construct(array $payload=[],string $source='system'){parent::__construct('ClientResponded',$payload,$source,date('c'));} }
