<?php
namespace App\Controllers;
use App\Support\{Auth,View,Response,Request};use App\Services\AI\AIOperationsService;
final class AIController { public function index():void{Auth::requireLogin();View::render('ai/index');} public function decide():void{Auth::requireLogin();$r=new Request();$event=(string)$r->input('event','CLIENT_INACTIVE');$context=$r->all();unset($context['_csrf']);Response::json((new AIOperationsService)->decide($event,$context));}}
