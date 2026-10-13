<?php
namespace App\Services;
use App\Support\Database;use App\Services\ActivityService;
final class ClientService {
 public function create(array $d):int{
  $s=Database::pdo()->prepare("INSERT INTO clients(tenant_id,first_name,middle_name,last_name,client_type,email,phone,website,status,created_at,updated_at) VALUES(1,?,?,?,?,?,?,?,NOW(),NOW())");
  $s->execute([$d['first_name'],$d['middle_name']??null,$d['last_name'],$d['client_type']??'individual',$d['email']??null,$d['phone']??null,$d['website']??null,$d['status']??'active']);
  $id=(int)Database::pdo()->lastInsertId();(new ActivityService)->log('clients','created','client',$id);return $id;
 }
}
