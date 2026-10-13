<?php
require __DIR__.'/../bootstrap.php';
use App\Core\Database;
$path=__DIR__.'/legacy_data/23_Structured_Member_Accounts.xlsx';if(!is_file($path))exit("XLSX source not found.\n");
if(!class_exists('ZipArchive'))exit("ZipArchive extension is required for XLSX import.\n");
$z=new ZipArchive;if($z->open($path)!==true)exit("Cannot open XLSX.\n");
$shared=[];$xml=$z->getFromName('xl/sharedStrings.xml');if($xml){$sx=simplexml_load_string($xml);foreach($sx->si as $si){$shared[]=(string)($si->t?:implode('',iterator_to_array($si->r->t??[])));}}
$sheet=simplexml_load_string($z->getFromName('xl/worksheets/sheet1.xml'));$rows=[];foreach($sheet->sheetData->row as $row){$out=[];foreach($row->c as $cell){$ref=(string)$cell['r'];$type=(string)$cell['t'];$v=(string)$cell->v;$out[]=$type==='s'?($shared[(int)$v]??''):$v;} $rows[]=$out;}
$header=array_map('trim',$rows[0]??[]);$pdo=Database::pdo();$pdo->exec('TRUNCATE TABLE legacy_member_accounts_sheet');$ins=$pdo->prepare('INSERT INTO legacy_member_accounts_sheet(member_id,member_name,business_name,primary_email,phone,cell_phone,invoice_service_log,total_billed,amount_paid,outstanding_balance) VALUES(?,?,?,?,?,?,?,?,?,?)');
foreach(array_slice($rows,1) as $r){$r=array_pad($r,10,null);$ins->execute([$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],is_numeric($r[7])?$r[7]:null,is_numeric($r[8])?$r[8]:null,is_numeric($r[9])?$r[9]:null]);}
$z->close();echo 'Imported '.max(0,count($rows)-1).' spreadsheet rows.\n';
