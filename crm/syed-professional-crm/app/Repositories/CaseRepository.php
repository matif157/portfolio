<?php
declare(strict_types=1); namespace App\Repositories;
use PDO;
class CaseRepository {public function __construct(private PDO $db){} public function find(int $id):?array{$s=$this->db->prepare('SELECT * FROM service_cases WHERE id=?');$s->execute([$id]);return $s->fetch()?:null;}}
