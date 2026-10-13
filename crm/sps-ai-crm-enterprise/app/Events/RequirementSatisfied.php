<?php
namespace App\Events;
final class RequirementSatisfied extends DomainEvent { public function __construct(array $payload=[],string $source='system'){parent::__construct('RequirementSatisfied',$payload,$source,date('c'));} }
