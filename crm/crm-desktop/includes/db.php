<?php
$host = '127.0.0.1';
$username = 'root';
$password = '';
$database = 'syedpro_app';

$con = mysqli_connect($host, $username, $password, $database);

if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
 