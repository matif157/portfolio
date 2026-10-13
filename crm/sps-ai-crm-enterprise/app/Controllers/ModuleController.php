<?php
namespace App\Controllers;
use App\Support\{Auth,View};
use App\Repositories\{ServiceRepository,ProjectRepository,InvoiceRepository,QuotationRepository,LeadRepository,TaskRepository,ReminderRepository,DocumentRepository,ActivityRepository,NotificationRepository};
final class ModuleController {
 private function page($title,$repo,$view):void{Auth::requireLogin();View::render($view,['title'=>$title,'rows'=>$repo->all()]);}
 public function services():void{$this->page('Services',new ServiceRepository,'modules/list');}
 public function projects():void{$this->page('Projects',new ProjectRepository,'modules/list');}
 public function invoices():void{$this->page('Invoices',new InvoiceRepository,'modules/list');}
 public function quotations():void{$this->page('Quotations',new QuotationRepository,'modules/list');}
 public function leads():void{$this->page('Leads',new LeadRepository,'modules/list');}
 public function tasks():void{$this->page('Tasks',new TaskRepository,'modules/list');}
 public function reminders():void{$this->page('Calendar & Reminders',new ReminderRepository,'modules/list');}
 public function documents():void{$this->page('Documents',new DocumentRepository,'modules/list');}
 public function activities():void{$this->page('Activity & Audit',new ActivityRepository,'modules/list');}
 public function notifications():void{$this->page('Notifications',new NotificationRepository,'modules/list');}
}
