<?php
require __DIR__.'/../bootstrap.php';use App\Core\Database;
$pdo=Database::pdo();$hash=password_hash('ChangeMeImmediately!123',PASSWORD_DEFAULT);$pdo->prepare('INSERT IGNORE INTO users(id,tenant_id,full_name,email,username,password,role_id,designation) VALUES(1,1,?,?,?,?,1,?)')->execute(['Muhammad Atif','atif@syedpro.com','admin',$hash,'Owner']);
$pdo->prepare('INSERT IGNORE INTO company_contact_info(id,phone,fax,email,website,street,city,state,zip) VALUES(1,?,?,?,?,?,?,?,?)')->execute(['(718) 859-5392','(718) 859-2237','invoice@syedpro.com','www.syedpro.com','1012 Coney Island Avenue','BROOKLYN','NY','11230']);
echo "SPS CRM seeded. Username: admin Password: ChangeMeImmediately!123\n";
