<?php
namespace App\Events;
final class DocumentUploaded extends DomainEvent { public function __construct(array $payload=[],string $source='system'){parent::__construct('DocumentUploaded',$payload,$source,date('c'));} }
