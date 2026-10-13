<?php
declare(strict_types=1); namespace App\Core;
use PDO;
class Database { private ?PDO $pdo=null; public function __construct(private array $config){} public function connection():PDO{if($this->pdo)return $this->pdo;$dsn="mysql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']};charset={$this->config['charset']}";$this->pdo=new PDO($dsn,$this->config['username'],$this->config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);return $this->pdo;} }
