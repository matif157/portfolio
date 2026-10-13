<?php
namespace App\Events;
final class ApprovalRequested extends DomainEvent { public function __construct(array $payload=[],string $source='system'){parent::__construct('ApprovalRequested',$payload,$source,date('c'));} }
