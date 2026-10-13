<?php
namespace App\Services;
use App\Support\Database;use App\Services\ActivityService;
final class InvoiceService {
 public function create(array $d):int{
  $pdo=Database::pdo();$s=$pdo->prepare("INSERT INTO invoices(tenant_id,client_id,business_id,project_id,number,status,total,balance,due_date,created_at,updated_at) VALUES(1,?,?,?,?,?,?,?,?,NOW(),NOW())");
  $s->execute([$d['client_id'],$d['business_id']??null,$d['project_id']??null,$d['number'],$d['status']??'draft',$d['total']??0,$d['balance']??($d['total']??0),$d['due_date']??null]);
  $id=(int)$pdo->lastInsertId();(new ActivityService)->log('billing','invoice_created','invoice',$id,null,'stage_2');return $id;
 }
 public function recordPayment(int $invoiceId,float $amount,string $method='other'):void{
  $pdo=Database::pdo();$pdo->beginTransaction();try{
   $s=$pdo->prepare("INSERT INTO payments(tenant_id,invoice_id,amount,method,status,paid_at,created_at) VALUES(1,?,?,?,?,NOW(),NOW())");$s->execute([$invoiceId,$amount,$method,'received']);
   $u=$pdo->prepare("UPDATE invoices SET balance=GREATEST(balance-?,0),status=IF(balance-?<=0,'paid','partially_paid'),updated_at=NOW() WHERE id=?");$u->execute([$amount,$amount,$invoiceId]);$pdo->commit();
  }catch(\Throwable $e){$pdo->rollBack();throw $e;}
 }
}
