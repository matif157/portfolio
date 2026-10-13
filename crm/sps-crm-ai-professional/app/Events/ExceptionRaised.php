<?php
namespace App\Events;
final class ExceptionRaised extends DomainEvent { public function __construct(array $payload=[],string $source='system'){parent::__construct('ExceptionRaised',$payload,$source,date('c'));} }
