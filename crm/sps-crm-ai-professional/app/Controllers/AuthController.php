<?php
namespace App\Controllers;
use App\Support\{Database,Auth,Session,Csrf,View,Request,Response};
use App\Services\ActivityService;
final class AuthController {
 public function login():void{View::render('auth/login');}
 public function authenticate():void{
  $r=new Request();if(!Csrf::check($r->input('_csrf'))){http_response_code(419);exit('CSRF validation failed');}
  $s=Database::pdo()->prepare("SELECT * FROM users WHERE tenant_id=1 AND (username=? OR email=?) AND is_active=1 LIMIT 1");$s->execute([$r->input('login'),$r->input('login')]);$u=$s->fetch();
  if(!$u||!password_verify((string)$r->input('password'),$u['password_hash'])){(new ActivityService)->log('auth','login_failed',null,null,null,'stage_2','warning');View::render('auth/login',['error'=>'Invalid credentials']);return;}
  Auth::login($u);Database::pdo()->prepare("UPDATE users SET last_login_at=NOW() WHERE id=?")->execute([$u['id']]);(new ActivityService)->log('auth','login', 'user',$u['id'],null,'stage_2');Response::redirect('/');
 }
 public function logout():void{(new ActivityService)->log('auth','logout',null,null,null,'stage_2');Auth::logout();Response::redirect('/login');}
}
