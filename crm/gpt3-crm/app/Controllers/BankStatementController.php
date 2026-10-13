<?php
namespace App\Controllers;
use App\Core\{Auth,View,Request,Csrf,Response,Database};
use App\Services\{BankStatementService,PermissionService,BookkeepingAutomationService};
final class BankStatementController {
 public function index():void{
  Auth::requireLogin();(new PermissionService)->require('Client','view');$clients=Database::pdo()->query('SELECT id,full_name FROM clients WHERE deleted_at IS NULL ORDER BY full_name LIMIT 1000')->fetchAll();
  $clientId=(int)($_GET['client_id']??($clients[0]['id']??0));$year=(int)($_GET['year']??date('Y'));$service=new BankStatementService();$accounts=$clientId?$service->grid($clientId,$year):[];$missing=$clientId?$service->missingMonths($clientId,$year):[];
  View::render('bank_statements/index',compact('clients','clientId','year','accounts','missing'));
 }
 public function addAccount():never{Auth::requireLogin();(new PermissionService)->require('Client','edit');$r=new Request;if(!Csrf::check($r->input('_csrf'))){http_response_code(419);exit('CSRF');}(new BankStatementService)->addAccount((int)$r->input('client_id'),$r->all());Response::redirect(route('bank-statements',['client_id'=>(int)$r->input('client_id'),'year'=>(int)$r->input('year',date('Y'))]));}
 public function mark():never{Auth::requireLogin();(new PermissionService)->require('Client','edit');$r=new Request;if(!Csrf::check($r->input('_csrf'))){http_response_code(419);exit('CSRF');}(new BankStatementService)->markReceived((int)$r->input('client_id'),(int)$r->input('bank_account_id'),(int)$r->input('year'),(int)$r->input('month'),null,(string)$r->input('notes',''));Response::redirect(route('bank-statements',['client_id'=>(int)$r->input('client_id'),'year'=>(int)$r->input('year')]));}
 public function upload():never{Auth::requireLogin();(new PermissionService)->require('Client','edit');$r=new Request;if(!Csrf::check($r->input('_csrf'))){http_response_code(419);exit('CSRF');}$f=$_FILES['statement']??[];(new BankStatementService)->upload((int)$r->input('client_id'),(int)$r->input('bank_account_id'),(int)$r->input('year'),(int)$r->input('month'),$f,(string)$r->input('notes',''));Response::redirect(route('bank-statements',['client_id'=>(int)$r->input('client_id'),'year'=>(int)$r->input('year')]));}
 public function remind():never{Auth::requireLogin();(new PermissionService)->require('Client','edit');$r=new Request;if(!Csrf::check($r->input('_csrf'))){http_response_code(419);exit('CSRF');}(new BankStatementService)->createMissingReminder((int)$r->input('client_id'),(int)$r->input('year'),Auth::id());Response::redirect(route('bank-statements',['client_id'=>(int)$r->input('client_id'),'year'=>(int)$r->input('year')]));}
 public function startBookkeeping():never{Auth::requireLogin();(new PermissionService)->require('Service','request');$r=new Request;if(!Csrf::check($r->input('_csrf'))){http_response_code(419);exit('CSRF');}(new BookkeepingAutomationService)->startForClient((int)$r->input('client_id'),(int)$r->input('business_id',0),(int)$r->input('year',date('Y')));Response::redirect(route('bank-statements',['client_id'=>(int)$r->input('client_id'),'year'=>(int)$r->input('year',date('Y'))]));}
}
