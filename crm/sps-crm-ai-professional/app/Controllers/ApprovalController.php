<?php
namespace App\Controllers;
use App\Support\{Auth,View,Request,Csrf,Response};use App\Repositories\ApprovalRepository;use App\Services\ApprovalService;
final class ApprovalController { public function index():void{Auth::requireLogin();View::render('approvals/index',['rows'=>(new ApprovalRepository)->all()]);} public function decide($id):void{Auth::requireLogin();$r=new Request();if(!Csrf::check($r->input('_csrf')))Response::json(['error'=>'csrf'],419);(new ApprovalService)->decide((int)$id,$r->input('decision')==='approve',(string)$r->input('notes'));Response::redirect('/approvals');}}
