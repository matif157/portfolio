<?php
declare(strict_types=1); namespace App\Controllers;
use App\Core\{Container,Auth};
class DashboardController {public function __construct(private Container $c){} public function index():void{Auth::requireLogin();$this->c->get('view')->display('dashboard/index',['title'=>'Dashboard']);}}
