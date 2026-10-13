<?php
namespace App\Services;
use App\Core\Database;
final class ImportService {
    public function csv(string $file,string $table): int { $pdo=Database::pdo(); if(!is_file($file)) throw new \RuntimeException('CSV not found: '.$file); $fh=fopen($file,'r');$headers=fgetcsv($fh);$n=0;while(($row=fgetcsv($fh))!==false){$cols=array_map(fn($h)=>preg_replace('/[^a-zA-Z0-9_]/','_',strtolower($h)),$headers);$payload=[];foreach($cols as $i=>$c)$payload[$c]=$row[$i]??null;if($table==='legacy_member_accounts'){$s=$pdo->prepare('INSERT INTO legacy_member_accounts(member_id,member_name,business_name,primary_email,phone,cell_phone,invoice_service_log,total_billed,amount_paid,outstanding_balance) VALUES(?,?,?,?,?,?,?,?,?,?)');$s->execute([$payload['member_id'],$payload['member_name'],$payload['business_name'],$payload['primary_email'],$payload['phone_number'],$payload['cell_phone'],$payload['invoice_services_log'],$payload['total_billed'],$payload['amount_paid'],$payload['outstanding_balance']]);$n++;}}fclose($fh);return $n; }
}
