<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';require_login();$pdo=db();
if(!user_can('users.manage')){http_response_code(403);die('Access denied.');}
$msg=flash('success');
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();$action=post_string('action');
    try{
        if($action==='add'){
            $username=post_string('username');$password=post_string('password');$first=post_string('first_name');$last=post_string('last_name');$role=post_int('role_id');
            if($username===''||strlen($password)<10||$first===''||$last===''||!$role)throw new RuntimeException('Username, name, role and a 10+ character password are required.');
            $st=$pdo->prepare('INSERT INTO users(role_id,username,password_hash,first_name,last_name,email,status,must_change_password) VALUES(?,?,?,?,?,?, "active",1)');
            $st->execute([$role,$username,password_hash($password,PASSWORD_DEFAULT),$first,$last,post_string('email')?:null]);
            $id=(int)$pdo->lastInsertId();activity($pdo,'create','users',$id,'User created: '.$username);flash('success','User created.');
        }elseif($action==='toggle'){
            $id=post_int('id');if($id===(int)current_user()['id'])throw new RuntimeException('You cannot deactivate your own account.');
            $pdo->prepare("UPDATE users SET status=IF(status='active','inactive','active') WHERE id=?")->execute([$id]);activity($pdo,'status','users',$id,'User status changed');flash('success','User status updated.');
        }
    }catch(Throwable $e){error_log('[SPS USERS] '.$e->getMessage());flash('success',$e->getMessage());}
    redirect('users.php');
}
$roles=$pdo->query("SELECT * FROM roles ORDER BY name")->fetchAll();
$rows=$pdo->query("SELECT u.*,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id ORDER BY u.id")->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Users · SPS CRM</title><style>body{margin:0;background:#f5f7fb;font-family:Arial;color:#172033}.top{background:#fff;border-bottom:1px solid #e5eaf2;padding:18px 28px;display:flex;justify-content:space-between}.top a{color:#315ee7;text-decoration:none}.wrap{max-width:1150px;margin:auto;padding:28px}.grid{display:grid;grid-template-columns:1fr 1.7fr;gap:16px}.card{background:#fff;border:1px solid #e5eaf2;border-radius:15px;padding:20px}.field{margin:10px 0}.field label{display:block;font-size:12px;font-weight:bold;margin-bottom:6px}.field input,.field select{width:100%;padding:10px;border:1px solid #d8dfeb;border-radius:8px}.btn{background:#315ee7;color:#fff;border:0;padding:10px 13px;border-radius:8px;font-weight:bold}.btn.alt{background:#eef2ff;color:#315ee7}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:11px;border-bottom:1px solid #edf0f5;text-align:left;font-size:13px}.success{background:#ebf9f1;color:#176a3c;padding:10px;border-radius:8px;margin-bottom:14px}@media(max-width:820px){.grid{grid-template-columns:1fr}}</style></head><body><header class="top"><strong>SPS CRM · Users</strong><a href="dashboard.php">← Dashboard</a></header><main class="wrap"><?php if($msg):?><div class="success"><?=e($msg)?></div><?php endif;?><div class="grid"><div class="card"><h2>Add User</h2><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="add"><div class="field"><label>Username</label><input name="username" required></div><div class="field"><label>Password</label><input name="password" type="password" minlength="10" required></div><div class="field"><label>First Name</label><input name="first_name" required></div><div class="field"><label>Last Name</label><input name="last_name" required></div><div class="field"><label>Email</label><input name="email" type="email"></div><div class="field"><label>Role</label><select name="role_id" required><?php foreach($roles as $r):?><option value="<?=$r['id']?>"><?=e($r['name'])?></option><?php endforeach;?></select></div><button class="btn">Create User</button></form></div><div class="card"><h2>User Accounts</h2><table class="table"><tr><th>User</th><th>Role</th><th>Email</th><th>Status</th><th>Action</th></tr><?php foreach($rows as $r):?><tr><td><?=e($r['first_name'].' '.$r['last_name'])?><div><?=e($r['username'])?></div></td><td><?=e($r['role_name'])?></td><td><?=e($r['email']?:'—')?></td><td><?=e($r['status'])?></td><td><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn alt"><?=$r['status']==='active'?'Deactivate':'Activate'?></button></form></td></tr><?php endforeach;?></table></div></div></main></body></html>
