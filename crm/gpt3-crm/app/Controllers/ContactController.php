<?php
namespace App\Controllers;
use App\Core\{Auth,View,Request,Csrf,Response,Database};
use App\Services\ActivityService;
final class ContactController {
 public function index():void{Auth::requireLogin();$s=Database::pdo()->query('SELECT ct.*,c.full_name client_name,b.business_name FROM contacts ct JOIN clients c ON c.id=ct.client_id LEFT JOIN businesses b ON b.id=ct.business_id ORDER BY ct.id DESC LIMIT 500');View::render('contacts/index',['contacts'=>$s->fetchAll()]);}
 public function create():void{Auth::requireLogin();$clients=Database::pdo()->query('SELECT id,full_name FROM clients WHERE deleted_at IS NULL ORDER BY full_name')->fetchAll();View::render('contacts/form',['clients'=>$clients]);}
 public function store():never{Auth::requireLogin();$r=new Request;if(!Csrf::check($r->input('_csrf'))){http_response_code(419);exit('CSRF');}$s=Database::pdo()->prepare('INSERT INTO contacts(tenant_id,client_id,business_id,name,relationship,phone,email,is_primary,notes,created_at) VALUES(1,?,?,?,?,?,?,?,?,NOW())');$s->execute([(int)$r->input('client_id'),$r->input('business_id')?:null,$r->input('name'),$r->input('relationship'),$r->input('phone'),$r->input('email'),!empty($r->input('is_primary'))?1:0,$r->input('notes')]);$id=(int)Database::pdo()->lastInsertId();(new ActivityService)->record('CONTACT_CREATED','Contacts','contact',$id);Response::redirect(route('contacts'));}
}
