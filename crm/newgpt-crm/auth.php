<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
require_once __DIR__.'/functions.php';
function current_user():?array{
    static $cached=false; if($cached!==false)return $cached;
    $id=(int)($_SESSION['user_id']??0); if($id<=0)return $cached=null;
    try{$s=db()->prepare('SELECT u.*,r.name role_name,r.slug role_slug FROM users u LEFT JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.status="active" LIMIT 1');$s->execute([$id]);$cached=$s->fetch()?:null;}
    catch(Throwable $e){error_log('[SPS AUTH] '.$e->getMessage());$cached=null;}
    return $cached;
}
function logged_in():bool{return current_user()!==null;}
function require_login():void{if(!logged_in()){$_SESSION['after_login']=basename($_SERVER['PHP_SELF']??'dashboard.php');redirect('login.php');}}
function login_user(int $id):void{session_regenerate_id(true);$_SESSION['user_id']=$id;$_SESSION['login_at']=time();}
function logout_user():void{$_SESSION=[];if(ini_get('session.use_cookies')){ $p=session_get_cookie_params();setcookie(session_name(),' ',time()-42000,$p['path']??'/',$p['domain']??'',(bool)($p['secure']??false),(bool)($p['httponly']??true)); }session_destroy();}
function user_can(string $permission):bool{
    $u=current_user(); if(!$u)return false; if(($u['role_slug']??'')==='admin')return true;
    try{$s=db()->prepare('SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=? AND p.slug=?');$s->execute([(int)$u['role_id'],$permission]);return(bool)$s->fetchColumn();}
    catch(Throwable $e){error_log('[SPS AUTH PERM] '.$e->getMessage());return false;}
}
function login_lock_status(PDO $pdo,string $username,string $ip):array{
    if(!table_exists($pdo,'login_attempts'))return['locked'=>false];
    $s=$pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE success=0 AND attempted_at>=NOW()-INTERVAL 15 MINUTE AND (ip_address=? OR username=?)');$s->execute([$ip,$username]);$n=(int)$s->fetchColumn();return['locked'=>$n>=8,'remaining'=>max(0,8-$n)];
}
function record_login_attempt(PDO $pdo,?string $username,bool $success):void{if(table_exists($pdo,'login_attempts')){$s=$pdo->prepare('INSERT INTO login_attempts(username,ip_address,success) VALUES(?,?,?)');$s->execute([$username?:null,$_SERVER['REMOTE_ADDR']??null,$success?1:0]);}}
?>