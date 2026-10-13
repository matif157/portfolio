<?php
use App\Controllers\ServiceExecutionController;
use App\Controllers\RequirementController;
use App\Controllers\ServiceOperationsController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ClientController;
use App\Controllers\ServiceController;
use App\Controllers\ModuleController;
use App\Controllers\CalendarController;
use App\Controllers\AIController;
use App\Controllers\AdminController;
use App\Controllers\PortalController;
use App\Controllers\ReportController;
use App\Controllers\BankStatementController;
use App\Controllers\BusinessController;
use App\Controllers\ContactController;
use App\Controllers\CustomizerController;
use App\Controllers\HealthController;

return function($r){

 $a=new AuthController;$r->get('/login',[$a,'login']);$r->post('/login',[$a,'authenticate']);$r->get('/logout',[$a,'logout']);
 $d=new DashboardController;$r->get('/',[$d,'index']);$r->get('/dashboard',[$d,'index']);
 $c=new ClientController;$r->get('/clients',[$c,'index']);$r->get('/clients/create',[$c,'create']);$r->post('/clients',[$c,'store']);$r->get('/clients/{id}',[$c,'show']);$r->get('/clients/{id}/edit',[$c,'edit']);$r->post('/clients/{id}/edit',[$c,'update']);$r->get('/clients/{id}/archive',[$c,'archive']);$r->post('/clients/{id}/comments',[$c,'comment']);
 $s=new ServiceController;$r->get('/services',[$s,'index']);$r->get('/services/create',[$s,'create']);$r->post('/services',[$s,'store']);$r->get('/services/{id}',[$s,'show']);$r->get('/services/{id}/edit',[$s,'edit']);$r->get('/services/{id}/workflow',[$s,'workflow']);$r->post('/services/{id}/workflow/steps',[$s,'workflowStepStore']);$r->post('/services/{id}/edit',[$s,'update']);$r->post('/services/request',[$s,'request']);$r->get('/client-services/{id}/requirements',[$s,'requirements']);$r->post('/client-services/{id}/requirements/satisfy',[$s,'satisfyRequirement']);
 $m=new ModuleController;$r->get('/leads',[$m,'leads']);$r->get('/leads/create',[$m,'leadCreate']);$r->post('/leads',[$m,'leadStore']);$r->get('/projects',[$m,'projects']);$r->get('/projects/create',[$m,'projectCreate']);$r->post('/projects',[$m,'projectStore']);$r->get('/projects/{id}',[$m,'projectView']);$r->get('/projects/{id}/edit',[$m,'projectEdit']);$r->post('/workflow-steps/{id}/complete',[$m,'workflowStepComplete']);$r->post('/workflow-instances/{id}/complete',[$m,'workflowComplete']);$r->post('/projects/{id}/edit',[$m,'projectUpdate']);$r->post('/projects/{id}/status',[$m,'projectStatus']);$r->get('/quotations',[$m,'quotations']);$r->get('/invoices',[$m,'invoices']);$r->get('/invoices/create',[$m,'invoiceCreate']);$r->post('/invoices',[$m,'invoiceStore']);$r->get('/invoices/{id}',[$m,'invoiceView']);$r->post('/invoices/{id}/payment',[$m,'invoicePayment']);$r->get('/tasks',[$m,'tasks']);$r->get('/tasks/create',[$m,'taskCreate']);$r->post('/tasks',[$m,'taskStore']);$r->get('/tasks/{id}/complete',[$m,'taskComplete']);$r->get('/activities',[$m,'activities']);$r->get('/notifications',[$m,'notifications']);$r->get('/documents',[$m,'documents']);$r->get('/communications',[$m,'communications']);$r->get('/approvals',[$m,'approvals']);$r->get('/cases',[$m,'cases']);
 $b=new BusinessController;$r->get('/businesses',[$b,'index']);$r->get('/businesses/create',[$b,'create']);$r->post('/businesses',[$b,'store']);$r->get('/businesses/{id}',[$b,'show']);
 $ct=new ContactController;$r->get('/contacts',[$ct,'index']);$r->get('/contacts/create',[$ct,'create']);$r->post('/contacts',[$ct,'store']);
 $bs=new BankStatementController;$r->get('/bank-statements',[$bs,'index']);$r->post('/bank-statements/account',[$bs,'addAccount']);$r->post('/bank-statements/mark',[$bs,'mark']);$r->post('/bank-statements/upload',[$bs,'upload']);$r->post('/bank-statements/remind',[$bs,'remind']);$r->post('/bank-statements/start-bookkeeping',[$bs,'startBookkeeping']);
 $cu=new CustomizerController;$r->get('/admin/customizer',[$cu,'index']);$r->post('/admin/customizer',[$cu,'store']);
 $h=new HealthController;$r->get('/admin/health',[$h,'index']);
 $cal=new CalendarController;$r->get('/calendar',[$cal,'index']);$r->get('/api/calendar/reminders',[$cal,'day']);$r->get('/api/calendar/clients',[$cal,'clients']);$r->post('/api/calendar/reminders',[$cal,'store']);
 $ai=new AIController;$r->get('/ai',[$ai,'index']);$r->post('/api/ai/decide',[$ai,'decide']);
 $ad=new AdminController;$r->get('/admin/users',[$ad,'users']);$r->get('/admin/users/create',[$ad,'userCreate']);$r->post('/admin/users',[$ad,'userStore']);$r->get('/admin/users/{id}/edit',[$ad,'userEdit']);$r->post('/admin/users/{id}/edit',[$ad,'userUpdate']);$r->get('/admin/roles',[$ad,'roles']);$r->post('/admin/roles',[$ad,'roleSave']);$r->get('/admin/integrations',[$ad,'integrations']);
 $rp=new ReportController;$r->get('/reports',[$rp,'index']);
 $p=new PortalController;$r->get('/portal/login',[$p,'login']);$r->post('/portal/login',[$p,'authenticate']);$r->get('/portal',[$p,'index']);$r->get('/portal/logout',[$p,'logout']);
 $se = new ServiceExecutionController;
 $r->post('/projects/{id}/workflow/start', [$se,'start']);
 $r->post('/workflow/{id}/steps/{step}', [$se,'completeStep']);

 $so = new ServiceOperationsController;
 $r->get('/client-services/{id}/status', [$so,'status']);
 $r->post('/documents/link-requirement', [$so,'attachDocument']);

 $rc = new RequirementController;
 $r->get('/client-services/{id}/requirements/json', [$rc,'list']);
 $r->post('/client-service-requirements/{id}/complete', [$rc,'complete']);

};
