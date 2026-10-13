<?php
namespace App\Controllers;
use App\Core\{Auth,View,Request,Response};use App\Services\AiService;
final class AIController {public function index():void{Auth::requireLogin();View::render('ai/index');}public function decide():never{Auth::requireLogin();$r=new Request;$result=(new AiService)->analyze($r->input('prompt',''),['user_id'=>Auth::id()]);Response::json($result);}}
