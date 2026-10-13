<?php
namespace App\Services;
use App\Core\Database;
final class DocumentService {
    public function saveUpload(array $file,int $clientId,?int $projectId=null,bool $sensitive=false):int {
        if(($file['error']??1)!==UPLOAD_ERR_OK) throw new \RuntimeException('Upload failed.');
        if(($file['size']??0)>10*1024*1024) throw new \RuntimeException('File exceeds 10 MB limit.');
        $name=preg_replace('/[^A-Za-z0-9._-]/','_',basename($file['name']));$key=bin2hex(random_bytes(16)).'_'.$name;$dir=PRIVATE_STORAGE.'/documents';if(!is_dir($dir))mkdir($dir,0700,true);move_uploaded_file($file['tmp_name'],$dir.'/'.$key);
        $s=Database::pdo()->prepare('INSERT INTO documents(tenant_id,client_id,project_id,name,storage_key,is_sensitive,created_by,created_at) VALUES(1,?,?,?,?,?,?,NOW())');$s->execute([$clientId,$projectId,$name,$key,$sensitive?1:0,\App\Core\Auth::id()]);$id=(int)Database::pdo()->lastInsertId();(new ActivityService)->record('DOCUMENT_UPLOADED','Documents','document',$id,null,'stage_2');return $id;
    }
}
