<?php
namespace App\Controllers;
use App\Support\{Auth,View,Request,Response,Csrf};
use App\Services\{ClientService,PermissionService};
use App\Repositories\ClientRepository;
final class ClientController {
 public function index():void{Auth::requireLogin();(new PermissionService)->require('clients.view');View::render('clients/index',['clients'=>(new ClientRepository)->all()]);}
 public function create():void{Auth::requireLogin();(new PermissionService)->require('clients.create');View::render('clients/form');}
 public function store():void{Auth::requireLogin();$r=new Request();if(!Csrf::check($r->input('_csrf'))){http_response_code(419);exit('CSRF');}$v=(new \App\Support\Validator)->required($r->all(),['first_name','last_name'])->email($r->all(),'email');if(!$v->valid()){View::render('clients/form',['errors'=>$v->errors(),'old'=>$r->all()]);return;}$id=(new ClientService)->create($r->all());Response::redirect('/clients/'.$id);}
 public function show($id):void{Auth::requireLogin();(new PermissionService)->require('clients.view');View::render('clients/show',['client'=>(new ClientRepository)->find((int)$id)]);}
}
