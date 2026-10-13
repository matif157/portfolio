<?php
error_reporting(E_ERROR); ini_set('display_errors','1');
global $con;
$con = @mysqli_connect('127.0.0.1', 'root', '', 'syedpro_app', 3306);
if (!$con) { echo json_encode(array('error'=>'db','message'=>mysqli_connect_error())); exit; }
require_once __DIR__ . '/ai_helper.php';
header('Content-Type: application/json; charset=utf-8');
echo json_encode(ai_backend_status());
