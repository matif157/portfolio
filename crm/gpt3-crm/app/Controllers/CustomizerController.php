<?php
namespace App\Controllers;
use App\Core\{Auth,View,Request,Csrf,Response,Database};
use App\Services\{PermissionService,ActivityService};
final class CustomizerController {
 public function index():void{Auth::requireLogin();(new PermissionService)->require('System','roles');$fields=Database::pdo()->query('SELECT * FROM custom_fields WHERE tenant_id=1 ORDER BY entity_type,sort_order,id')->fetchAll();View::render('admin/customizer',['fields'=>$fields]);}
 public function store():never{Auth::requireLogin();(new PermissionService)->require('System','roles');$r=new Request;if(!Csrf::check($r->input('_csrf'))){http_response_code(419);exit('CSRF');}$s=Database::pdo()->prepare('INSERT INTO custom_fields(tenant_id,entity_type,field_name,field_label,field_type,options_json,is_required,is_visible,sort_order,created_at) VALUES(1,?,?,?,?,?,?,?, ?,NOW())');$opts=$r->input('options')?array_values(array_filter(array_map('trim',explode(',',(string)$r->input('options'))))):null;$s->execute([$r->input('entity_type'),$r->input('field_name'),$r->input('field_label'),$r->input('field_type','text'),$opts?json_encode($opts):null,!empty($r->input('is_required'))?1:0,1,(int)$r->input('sort_order',0)]);(new ActivityService)->record('CUSTOM_FIELD_CREATED','System','custom_field',(int)Database::pdo()->lastInsertId(),null,'stage_3');Response::redirect(route('customizer'));}
}
