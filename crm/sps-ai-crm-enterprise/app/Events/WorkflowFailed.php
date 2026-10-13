<?php
namespace App\Events;
final class WorkflowFailed extends DomainEvent { public function __construct(array $payload=[],string $source='system'){parent::__construct('WorkflowFailed',$payload,$source,date('c'));} }
