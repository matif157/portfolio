<?php
namespace App\Services;
use App\Core\Auth; use App\Core\Database;
final class EventBus {
    public function dispatch(string $event,array $payload=[],string $source='system'):int{
        $s=Database::pdo()->prepare('INSERT INTO domain_events(tenant_id,event_name,entity_type,entity_id,payload_json,source) VALUES(1,?,?,?,?,?)');
        $s->execute([$event,$payload['entity_type']??null,$payload['entity_id']??null,json_encode($payload),$source]);
        return (int)Database::pdo()->lastInsertId();
    }
}
