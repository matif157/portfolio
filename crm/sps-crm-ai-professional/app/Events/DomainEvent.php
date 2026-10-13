<?php
namespace App\Events;
final class DomainEvent { public function __construct(public readonly string $name,public readonly array $payload=[],public readonly string $source='system',public readonly string $occurredAt=''){} }
