<?php
declare(strict_types=1); namespace App\Services;
class DocumentIntelligence { public function analyze(string $path):array{return ['status'=>'queued','provider'=>getenv('OCR_PROVIDER')?:'local','confidence'=>null,'message'=>'Document queued for OCR/AI analysis; no external provider is assumed active.'];} }
