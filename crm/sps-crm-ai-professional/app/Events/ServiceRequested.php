<?php
namespace App\Events;
final class ServiceRequested extends DomainEvent { public function __construct(array $payload=[],string $source='system'){parent::__construct('ServiceRequested',$payload,$source,date('c'));} }
