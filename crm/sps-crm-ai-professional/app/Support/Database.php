<?php
namespace App\Support;
use PDO;
final class Database {
 private static ?PDO $pdo=null;
 public static function pdo():PDO {
  if(self::$pdo)return self::$pdo;
  $c=require __DIR__.'/../../config/database.php';
  $dsn="mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset=utf8mb4";
  return self::$pdo=new PDO($dsn,$c['username'],$c['password'],[
   PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
   PDO::ATTR_EMULATE_PREPARES=>false
  ]);
 }
}
