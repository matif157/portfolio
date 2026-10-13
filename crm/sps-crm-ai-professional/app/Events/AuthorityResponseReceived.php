<?php
namespace App\Events;
final class AuthorityResponseReceived extends DomainEvent { public function __construct(array $payload=[],string $source='system'){parent::__construct('AuthorityResponseReceived',$payload,$source,date('c'));} }
