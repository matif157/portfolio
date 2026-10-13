<?php
use App\Controllers\{AuthController,DashboardController,ClientController,ModuleController,CalendarController,ApiController,ServiceController,AIController,CommunicationController,ApprovalController,CaseController,DocumentController,PortalController,AdminController,ReportController};
return function($r){
 $r->get('/login',[new AuthController,'login']);$r->post('/login',[new AuthController,'authenticate']);$r->post('/logout',[new AuthController,'logout']);
 $r->get('/',[new DashboardController,'index']);
 $r->get('/clients',[new ClientController,'index']);$r->get('/clients/create',[new ClientController,'create']);$r->post('/clients',[new ClientController,'store']);$r->get('/clients/{id}',[new ClientController,'show']);
 $m=new ModuleController;$r->get('/leads',[$m,'leads']);$r->get('/projects',[$m,'projects']);$r->get('/quotations',[$m,'quotations']);$r->get('/invoices',[$m,'invoices']);$r->get('/tasks',[$m,'tasks']);$r->get('/activities',[$m,'activities']);$r->get('/notifications',[$m,'notifications']);
 $s=new ServiceController;$r->get('/services',[$s,'index']);$r->get('/services/{id}',[$s,'show']);$r->post('/services/request',[$s,'request']);$r->get('/client-services/{id}/requirements',[$s,'requirements']);
 $c=new CalendarController;$r->get('/calendar',[$c,'index']);$r->get('/api/calendar/reminders',[$c,'day']);$r->get('/api/calendar/clients',[$c,'clients']);$r->post('/api/calendar/reminders',[$c,'store']);
 $d=new DocumentController;$r->get('/documents',[$d,'index']);$r->post('/documents/upload',[$d,'upload']);$r->get('/documents/{id}/download',[$d,'download']);
 $a=new ApiController;$r->get('/api/dashboard',[$a,'dashboard']);
 $ai=new AIController;$r->get('/ai',[$ai,'index']);$r->post('/api/ai/decide',[$ai,'decide']);
 $cm=new CommunicationController;$r->get('/communications',[$cm,'index']);$r->post('/api/communications',[$cm,'queue']);$r->post('/api/communications/{id}/send',[$cm,'send']);
 $ap=new ApprovalController;$r->get('/approvals',[$ap,'index']);$r->post('/approvals/{id}/decide',[$ap,'decide']);
 $ca=new CaseController;$r->get('/cases',[$ca,'index']);$r->post('/cases',[$ca,'create']);
 $ad=new AdminController;$r->get('/admin/users',[$ad,'users']);$r->get('/admin/integrations',[$ad,'integrations']);$r->get('/admin/permissions',[$ad,'permissions']);
 $rp=new ReportController;$r->get('/reports',[$rp,'index']);
 $p=new PortalController;$r->get('/portal/login',[$p,'login']);$r->post('/portal/login',[$p,'authenticate']);$r->get('/portal',[$p,'index']);$r->post('/portal/logout',[$p,'logout']);
};
