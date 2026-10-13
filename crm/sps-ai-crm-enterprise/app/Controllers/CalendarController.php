<?php
namespace App\Controllers;
use App\Support\{Auth,Database,View,Request,Csrf,Response,TenantContext};
use App\Services\ReminderService;
final class CalendarController {
 public function index():void{Auth::requireLogin();View::render('calendar/index');}
 public function store():void{Auth::requireLogin();$r=new Request();if(!Csrf::check($r->input('_csrf')))Response::json(['error'=>'csrf'],419);$ids=$r->input('client_ids',[]);if(!is_array($ids))$ids=preg_split('/\s*,\s*/',(string)$ids,-1,PREG_SPLIT_NO_EMPTY);$id=(new ReminderService)->create(['title'=>$r->input('title'),'description'=>$r->input('description'),'start_at'=>$r->input('start_at'),'recurrence_type'=>$r->input('recurrence_type','none'),'assigned_user_id'=>$r->input('assigned_user_id')?:Auth::id(),'created_by'=>Auth::id()],array_map('intval',$ids));Response::json(['success'=>true,'id'=>$id],201);}
 public function clients():never{Auth::requireLogin();$q=trim((string)($_GET['q']??''));$s=Database::pdo()->prepare("SELECT id,first_name,last_name,email FROM clients WHERE tenant_id=? AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?) ORDER BY first_name,last_name LIMIT 50");$like='%'.$q.'%';$s->execute([TenantContext::id(),$like,$like,$like]);Response::json(['clients'=>$s->fetchAll()]);}
 public function day():never{Auth::requireLogin();$date=$_GET['date']??date('Y-m-d');$s=Database::pdo()->prepare("SELECT r.*,GROUP_CONCAT(CONCAT(c.first_name,' ',c.last_name) SEPARATOR ', ') client_name FROM reminders r LEFT JOIN reminder_clients rc ON rc.reminder_id=r.id LEFT JOIN clients c ON c.id=rc.client_id WHERE r.tenant_id=? AND DATE(r.start_at)=? GROUP BY r.id ORDER BY r.start_at");$s->execute([TenantContext::id(),$date]);Response::json(['reminders'=>$s->fetchAll()]);}
}
