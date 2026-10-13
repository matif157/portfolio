<?php
namespace App\Services\Communications;
use App\Support\Database;use App\Support\TenantContext;
final class CommunicationTemplateService { public function render(int $templateId,array $vars):array{$s=Database::pdo()->prepare('SELECT * FROM communication_templates WHERE tenant_id=? AND id=?');$s->execute([TenantContext::id(),$templateId]);$t=$s->fetch();if(!$t)throw new \RuntimeException('Template not found');$replace=function(string $x)use($vars){return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/',fn($m)=>(string)($vars[$m[1]]??$m[0]),$x);};return ['subject'=>$replace($t['subject_template']??''),'body'=>$replace($t['body_template'])];}}
