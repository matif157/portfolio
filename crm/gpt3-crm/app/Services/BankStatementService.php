<?php
namespace App\Services;
use App\Core\Database;
use App\Core\Auth;
use App\Core\SecureValue;

final class BankStatementService {
    public function accounts(int $clientId): array {
        $s=Database::pdo()->prepare('SELECT a.*,b.business_name FROM bank_accounts a LEFT JOIN businesses b ON b.id=a.business_id WHERE a.client_id=? AND COALESCE(a.is_active,1)=1 ORDER BY a.bank_name,a.id');
        $s->execute([$clientId]); return $s->fetchAll();
    }
    public function addAccount(int $clientId,array $d): int {
        $last4=preg_replace('/\\D+/','',(string)($d['account_last4']??''));
        if(strlen($last4)>4)$last4=substr($last4,-4);
        $s=Database::pdo()->prepare('INSERT INTO bank_accounts(client_id,business_id,bank_name,account_no_cipher,account_last4,account_type,routing_no_cipher,bank_user_cipher,bank_password_cipher,is_active,added_by,date_added,date_updated) VALUES(?,?,?,?,?,?,?,?,?,1,?,NOW(),NOW())');
        $s->execute([$clientId,$d['business_id']?:null,$d['bank_name']??null,SecureValue::encrypt($d['account_no']??null),$last4,$d['account_type']??'checking',SecureValue::encrypt($d['routing_no']??null),SecureValue::encrypt($d['bank_user']??null),SecureValue::encrypt($d['bank_password']??null),Auth::id()]);
        $id=(int)Database::pdo()->lastInsertId(); (new ActivityService)->record('BANK_ACCOUNT_ADDED','Bank Statements','bank_account',$id,['client_id'=>$clientId,'account_last4'=>$last4],'stage_2'); return $id;
    }
    public function markReceived(int $clientId,int $accountId,int $year,int $month,?int $documentId=null,string $notes=''): void {
        $sql='INSERT INTO bank_statements(client_id,bank_account_id,statement_month,statement_year,received,received_date,received_by,document_id,notes) VALUES(?,?,?,?,1,CURDATE(),?,?,?) ON DUPLICATE KEY UPDATE received=1,received_date=CURDATE(),received_by=VALUES(received_by),document_id=VALUES(document_id),notes=VALUES(notes)';
        Database::pdo()->prepare($sql)->execute([$clientId,$accountId,$month,$year,Auth::id(),$documentId,$notes]);
        (new ActivityService)->record('BANK_STATEMENT_RECEIVED','Bank Statements','bank_statement',$accountId,['client_id'=>$clientId,'year'=>$year,'month'=>$month,'document_id'=>$documentId],'stage_1');
    }
    public function upload(int $clientId,int $accountId,int $year,int $month,array $file,string $notes=''): int {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new \RuntimeException('File upload failed.');
        if(($file['size']??0)>15*1024*1024) throw new \RuntimeException('Statement file is larger than 15MB.');
        $finfo=new \finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($file['tmp_name']);
        $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
        if(!isset($allowed[$mime])) throw new \RuntimeException('Only PDF, JPG or PNG statements are accepted.');
        $pdo=Database::pdo();$a=$pdo->prepare('SELECT bank_name,account_last4,business_id FROM bank_accounts WHERE id=? AND client_id=?');$a->execute([$accountId,$clientId]);$account=$a->fetch();if(!$account)throw new \RuntimeException('Bank account not found.');
        $rel='bank-statements/'.date('Y').'/'.$clientId.'/'.$accountId.'/'.$year.'-'.$month.'-'.bin2hex(random_bytes(6)).'.'.$allowed[$mime];
        $abs=APP_ROOT.'/storage/private/'.$rel; if(!is_dir(dirname($abs)))mkdir(dirname($abs),0775,true);
        if(!move_uploaded_file($file['tmp_name'],$abs))throw new \RuntimeException('Unable to store uploaded statement.');
        $st=$pdo->prepare('INSERT INTO documents(tenant_id,client_id,business_id,name,storage_key,mime_type,size_bytes,is_sensitive,version_no,created_by) VALUES(1,?,?,?,?,?,?,1,1,?)');
        $st->execute([$clientId,$account['business_id']??null,$file['name'],$rel,$mime,(int)$file['size'],Auth::id()]);
        $doc=(int)$pdo->lastInsertId(); $this->markReceived($clientId,$accountId,$year,$month,$doc,$notes); return $doc;
    }
    public function grid(int $clientId,int $year): array {
        $accounts=$this->accounts($clientId);$s=Database::pdo()->prepare('SELECT * FROM bank_statements WHERE client_id=? AND statement_year=? ORDER BY statement_month');$s->execute([$clientId,$year]);$rows=[];foreach($s as $r)$rows[$r['bank_account_id'].':'.$r['statement_month']]=$r;
        foreach($accounts as &$a){$a['months']=[];$a['missing']=0;for($m=1;$m<=12;$m++){ $key=$a['id'].':'.$m;$a['months'][$m]=$rows[$key]??null;if(empty($rows[$key]['received']))$a['missing']++; }}return $accounts;
    }
    public function missingMonths(int $clientId,int $year): array {
        $out=[];foreach($this->grid($clientId,$year) as $a){for($m=1;$m<=12;$m++){if(empty($a['months'][$m]['received']))$out[]=['bank_account_id'=>$a['id'],'bank_name'=>$a['bank_name'],'account_last4'=>$a['account_last4']??'','business_name'=>$a['business_name']??'','month'=>$m,'year'=>$year];}}return $out;
    }
    public function createMissingReminder(int $clientId,int $year,int $userId): int {
        $missing=$this->missingMonths($clientId,$year);if(!$missing)return 0;$pdo=Database::pdo();$c=$pdo->prepare('SELECT full_name,email,phone FROM clients WHERE id=?');$c->execute([$clientId]);$client=$c->fetch();
        $lines=[];foreach($missing as $m)$lines[]=date('F',mktime(0,0,0,$m['month'],1)).' '.$m['year'].' - '.($m['bank_name']??'Bank').' ending '.($m['account_last4']?:'unknown');
        $body="We are preparing your bookkeeping service. The following bank statements are still missing:\n\n".implode("\n",$lines)."\n\nPlease upload them through the client portal or contact SPS if you need assistance.";
        $pdo->prepare("INSERT INTO communications(tenant_id,client_id,channel,direction,subject,body,status,scheduled_at,created_at) VALUES(1,?,'email','outbound',?,?,'queued',NOW(),NOW())")->execute([$clientId,'Missing bank statements - '.$year,$body]);
        $pdo->prepare("INSERT INTO reminders(tenant_id,title,description,reminder_at,reminder_type,status,assigned_user_id,created_by,created_at,updated_at) VALUES(1,?,?,DATE_ADD(NOW(),INTERVAL 2 DAY),'missing_document','active',?,?,NOW(),NOW())")->execute(['Missing bank statements - '.$year,$body,$userId,$userId]);
        $id=(int)$pdo->lastInsertId();$pdo->prepare('INSERT IGNORE INTO reminder_clients(reminder_id,client_id) VALUES(?,?)')->execute([$id,$clientId]);(new ActivityService)->record('MISSING_STATEMENTS_REMINDER','Bank Statements','client',$clientId,['year'=>$year,'missing_count'=>count($missing),'contact_email'=>$client['email']??null]);return $id;
    }
}
