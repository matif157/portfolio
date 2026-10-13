<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "includes/db.php";
include "includes/auth.php";
var_dump($con);
var_dump($_SESSION);
$user_id = $_SESSION['user_id'];

function getInitials($name)
{
   $words = explode(' ', $name);
   $initials = '';
   foreach ($words as $word) {
      $initials .= strtoupper(substr($word, 0, 1));
   }
   return $initials;
}


$user_full_name = $_SESSION['fullname'];
$user_initials = getInitials($user_full_name);
$user_type = $_SESSION['user_type'];

?>

<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
   <title>Syed Professional Services</title>
   <?php include "includes/links.php"; ?>
   
  
</head>

<body class="theme-3">
   
   <div class="loader-bg">
      <div class="loader-track">
         <div class="loader-fill"></div>
      </div>
   </div>

   <?php include "includes/sidebar.php"; ?>
   <?php include "includes/header.php"; ?>
   <div class="dash-container">
      <div class="dash-content">
         <div class="card card-body">
            <div class="page-block">
               <div class="row align-items-center">
                  <div class="col-auto">
                     <div class="page-header-title">
                        <h4 class="m-b-10">Dashboard</h4>
                     </div>
                     <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item">Home</li>
                     </ul>
                  </div>
                 
               </div>
            </div>
         </div>
 
      </div>
   </div>
   <?php include "includes/footer.php"; ?>
   <?php include "includes/scripts.php"; ?>
</body>

</html>