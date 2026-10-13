<?php
declare(strict_types=1); namespace App\Services;
use PDO;
class AuditService { public function __construct(private PDO $db){} public function record(?int $user,string $action,string $entity,?int $entityId,array $meta=[]):void{$s=$this->db->prepare('INSERT INTO audit_logs(user_id,action,entity,entity_id,metadata,created_at) VALUES(?,?,?,?,?,NOW())');$s->execute([$user,$action,$entity,$entityId,json_encode($meta)]);} }
