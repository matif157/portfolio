<?php
namespace App\Events;
final class WorkflowCompleted extends DomainEvent { public function __construct(array $payload=[],string $source='system'){parent::__construct('WorkflowCompleted',$payload,$source,date('c'));} }
