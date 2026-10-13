<?php
namespace App\Services\AI;
use App\Support\Database;use App\Support\TenantContext;
final class DocumentIntelligenceService { public function analyze(int $documentId,string $text=''):array{$r=(new AIProviderManager)->provider()->classify($text,['identity','tax_return','bank_statement','invoice','other']);$s=Database::pdo()->prepare('INSERT INTO document_ai_results(tenant_id,document_id,class_name,confidence,extracted_json,validation_json,model_name,status,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())');$s->execute([TenantContext::id(),$documentId,$r['label'],$r['confidence'],json_encode([]),json_encode(['status'=>'needs_review']),'provider:'.$r['provider'],'completed']);return $r;}}
