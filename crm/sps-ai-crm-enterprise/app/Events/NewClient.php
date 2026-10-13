<?php
namespace App\Events;
final class NewClient extends DomainEvent { public function __construct(array $payload=[],string $source='system'){parent::__construct('NewClient',$payload,$source,date('c'));} }
