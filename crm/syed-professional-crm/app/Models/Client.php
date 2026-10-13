<?php
declare(strict_types=1); namespace App\Models;
class Client {public function __construct(public ?int $id,public string $fullName,public ?string $email=null,public ?string $phone=null){} }
