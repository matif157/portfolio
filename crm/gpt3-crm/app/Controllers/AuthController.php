<?php
namespace App\Controllers;
use App\Core\{Database,Auth,Csrf,Request,View,Response,Session};
use App\Services\ActivityService;
final class AuthController {
 public function login(): void:void{if(Auth::check())Response::redirect(route('dashboard'));View::render('auth/login',['error'=>Session::pullFlash('error')]);}
 public function authenticate():void{$r=new Request();if(!Csrf::check($r->input('_csrf'))){Session::flash('error','Security token expired. Please try again.');Response::redirect(route('login'));}$s=Database::pdo()->prepare('SELECT u.*,r.role_name FROM users u LEFT JOIN roles r ON r.id=u.role_id WHERE (u.username=? OR u.email=?) AND u.is_active=1 AND u.is_banned=0 LIMIT 1');$s->execute([$r->input('login'),$r->input('login')]);$u=$s->fetch();if(!$u||!password_verify((string)$r->input('password'),$u['password'])){(new ActivityService)->record('AUTH_LOGIN_FAILED','Auth',null,null,['login'=>$r->input('login')],'stage_2','warning');Session::flash('error','Invalid username or password.');Response::redirect(route('login'));}Auth::login($u);Database::pdo()->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$u['id']]);(new ActivityService)->record('AUTH_LOGIN','Auth','user',$u['id'],null,'stage_2');Response::redirect(route('dashboard'));}
 public function logout(): void:void{Auth::logout();Response::redirect(route('login'));}
}
