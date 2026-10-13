<?php
declare(strict_types=1); namespace App\Repositories;
use PDO;
class ClientRepository {public function __construct(private PDO $db){} public function find(int $id):?array{$s=$this->db->prepare('SELECT * FROM clients WHERE id=?');$s->execute([$id]);return $s->fetch()?:null;} public function search(string $q,int $limit=50):array{$s=$this->db->prepare('SELECT * FROM clients WHERE full_name LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT '.$limit);$x="%$q%";$s->execute([$x,$x]);return $s->fetchAll();}}
