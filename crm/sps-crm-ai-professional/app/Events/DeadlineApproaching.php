<?php
namespace App\Events;
final class DeadlineApproaching extends DomainEvent { public function __construct(array $payload=[],string $source='system'){parent::__construct('DeadlineApproaching',$payload,$source,date('c'));} }
