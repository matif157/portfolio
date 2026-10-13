<?php
namespace App\Controllers;
use App\Support\{Auth,View};use App\Services\Reporting\ReportingService;
final class ReportController { public function index():void{Auth::requireLogin();$r=new ReportingService;View::render('reports/index',['activity'=>$r->activityOverview(),'receivables'=>$r->receivables()]);}}
