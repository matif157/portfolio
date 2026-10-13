<?php namespace App\Integrations\Contract; interface OCRProviderInterface { public function extractText(string $filePath): array; }
