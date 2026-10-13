<?php
declare(strict_types=1); namespace App\Models;
class ServiceCase {public function __construct(public ?int $id,public int $clientId,public int $serviceId,public string $stage='intake',public string $status='open'){} }
