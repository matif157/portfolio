<?php
namespace App\Controllers;
use App\Support\{Auth,View,Request,Response,Csrf};use App\Repositories\CaseRepository;use App\Services\Cases\CaseService;
final class CaseController { public function index():void{Auth::requireLogin();View::render('cases/index',['rows'=>(new CaseRepository)->all()]);} public function create():void{Auth::requireLogin();$r=new Request();if(!Csrf::check($r->input('_csrf')))Response::json(['error'=>'csrf'],419);$id=(new CaseService)->create((int)$r->input('client_id'),(string)$r->input('title'),(string)$r->input('type','general'),$r->input('client_service_id')?(int)$r->input('client_service_id'):null);Response::redirect('/cases');}}
