<?php
declare(strict_types=1); namespace App\Controllers;
use App\Core\{Container,Auth};
class CrudController {public function __construct(private Container $c){} public function index($r,string $module):void{Auth::requireLogin();$this->c->get('view')->display($module.'/index',['title'=>ucfirst($module),'module'=>$module]);}}
