<?php

// =======================================================
// FILE: users.php
// PURPOSE: CRM User Management System
// =======================================================



// =======================================================
// STEP 1: LOAD SYSTEM FILES
// =======================================================


// Pehle login file load kar rahe hain.
//
// Is file me:
// - Database connection ($pdo)
// - Session handling
// - Current logged in user information
// available hoti hai.
//
// Authentication pehle load karna zaroori hai
// taake permission check properly work kare.


require_once 'log_in.php';


// sidebar.php me CRM navigation hai.
//
// Isko include karne se har page par
// same professional sidebar show hoti hai.


require_once 'sidebar.php';






// =======================================================
// STEP 2: USER PERMISSION CHECK
// =======================================================


// Sirf Admin aur Super Admin
// users manage kar sakte hain.
//
// Future me isay proper permission table
// se connect kiya ja sakta hai.


$allowed_roles = [

    'Super Admin',

    'Admin'

];





if (!in_array($currentUser['role_name'], $allowed_roles)) {


    exit("

    <div style='padding:40px;font-family:Poppins'>

    <h2 style='color:#ef4444'>
    Access Denied
    </h2>

    <p>
    You do not have permission to manage users.
    </p>


    <a href='dashboard.php'>
    ← Back to Dashboard
    </a>


    </div>

    ");


}







// =======================================================
// STEP 3: FETCH USERS
// =======================================================


// Database se tamam users fetch kar rahe hain.
//
// LEFT JOIN ka matlab:
// Agar user ka role missing bhi ho
// tab bhi user show hoga.


$sql = "

SELECT 

u.*,

r.role_name


FROM users u


LEFT JOIN roles r

ON u.role_id = r.id


ORDER BY u.id DESC


";




$users = $pdo->query($sql)->fetchAll();







// =======================================================
// STEP 4: FETCH ACTIVE ROLES
// =======================================================


// Add User modal ke dropdown ke liye
// available roles load kar rahe hain.


$roles = $pdo->query("

SELECT id, role_name

FROM roles

WHERE is_active = 1

ORDER BY id

")->fetchAll();



?>





<!DOCTYPE html>

<html lang="en">


<head>


<meta charset="UTF-8">


<meta name="viewport" content="width=device-width, initial-scale=1.0">



<title>User Management - <?= COMPANY_NAME ?></title>





<style>



/*======================================================

GLOBAL RESET

Browser ki default spacing remove karna.

Har browser apni default margin deta hai.

Isliye hum khud control karte hain.

======================================================*/


*{


margin:0;


padding:0;


box-sizing:border-box;



font-family:

'Segoe UI',
sans-serif;



}






/*======================================================

ROOT VARIABLES

Yahan se complete theme control hoti hai.

Agar CRM ka color change karna ho

to sirf yahan values change karni hain.

======================================================*/


:root{


--primary:#2563eb;


--secondary:#38bdf8;


--purple:#8b5cf6;


--dark:#020617;


--glass:

rgba(255,255,255,.08);



--border:

rgba(255,255,255,.15);



--text:#e2e8f0;


}







/*======================================================

BODY

Dark futuristic CRM background.

======================================================*/


body{


min-height:100vh;



background:


radial-gradient(

circle at top left,

rgba(37,99,235,.25),

transparent 40%

),



radial-gradient(

circle at bottom right,

rgba(139,92,246,.25),

transparent 40%

),



#020617;



color:var(--text);



}








/*======================================================

MAIN CONTENT

Sidebar fixed hai.

Isliye left margin diya gaya hai.

margin-left:

Sidebar ki width ke barabar hona chahiye.

======================================================*/


.main-content{


margin-left:320px;



padding:30px;



min-height:100vh;



}







/*======================================================

PAGE HEADER

Glass effect dashboard style.

======================================================*/


.page-header{


display:flex;



justify-content:space-between;



align-items:center;



padding:30px;



border-radius:25px;



background:

var(--glass);



border:

1px solid var(--border);



backdrop-filter:

blur(20px);



box-shadow:

0 20px 50px rgba(0,0,0,.3);



animation:

slideDown .7s ease;



}






@keyframes slideDown{


from{


opacity:0;


transform:

translateY(-40px);



}



to{


opacity:1;


transform:

translateY(0);



}



}







.page-header h1{


font-size:32px;


color:white;


}



.page-header p{


margin-top:8px;


color:#94a3b8;


}






/*======================================================

ADD USER BUTTON

======================================================*/


.add-user-btn{


padding:14px 25px;



border-radius:50px;



border:none;



background:

linear-gradient(

135deg,

#2563eb,

#7c3aed

);



color:white;



font-weight:600;



cursor:pointer;



text-decoration:none;



transition:.4s;



box-shadow:

0 10px 30px rgba(37,99,235,.4);



}




.add-user-btn:hover{


transform:

translateY(-5px);



box-shadow:

0 20px 40px rgba(37,99,235,.7);



}






</style>



</head>



<body>





<!-- =====================================================

MAIN CONTENT START

Sidebar already loaded from sidebar.php

===================================================== -->



<div class="main-content">





<div class="page-header">



<div>


<h1>

👥 User Management Center

</h1>


<p>

Logged in as:

<strong>

<?= htmlspecialchars($currentUser['full_name']) ?>

</strong>


(

<?= htmlspecialchars($currentUser['role_name']) ?>

)


</p>


</div>




<button class="add-user-btn">

+

Add New User

</button>



</div>
<!-- =====================================================
        USER STATISTICS SECTION

        Ye cards admin ko quick overview dete hain.

        Example:

        Total users kitne hain?
        Active users kitne hain?
        Admin kitne hain?

===================================================== -->


<div class="stats-container">



<div class="user-stat-card blue-card">


<div class="stat-icon">

👥

</div>


<div>

<h3>

Total Users

</h3>


<h1>

<?= count($users) ?>

</h1>


<p>

Registered accounts

</p>


</div>


</div>





<div class="user-stat-card green-card">


<div class="stat-icon">

🟢

</div>


<div>

<h3>

Active Users

</h3>


<h1>

<?php

$activeUsers = 0;


foreach($users as $u){

    if($u['is_active']){

        $activeUsers++;

    }

}


echo $activeUsers;

?>

</h1>


<p>

Currently active

</p>


</div>


</div>






<div class="user-stat-card purple-card">


<div class="stat-icon">

🛡️

</div>


<div>

<h3>

Admin Users

</h3>


<h1>


<?php

$admins = 0;


foreach($users as $u){

if(
isset($u['role_name']) &&
(
$u['role_name']=="Admin" ||
$u['role_name']=="Super Admin"
)

){

$admins++;

}

}


echo $admins;


?>


</h1>


<p>

Management access

</p>


</div>


</div>







<div class="user-stat-card orange-card">


<div class="stat-icon">

⏳

</div>


<div>

<h3>

Inactive Users

</h3>


<h1>


<?= count($users)-$activeUsers ?>


</h1>


<p>

Need attention

</p>


</div>


</div>




</div>








<!-- =====================================================
        SEARCH TOOLBAR

        Future me yahan AJAX search add ki ja sakti hai.

===================================================== -->


<div class="toolbar">


<div class="search-box">


🔍


<input 

type="text"

placeholder="Search users..."

/>


</div>





<div class="filter-box">


<select>


<option>

All Roles

</option>


<?php foreach($roles as $role): ?>


<option>

<?= htmlspecialchars($role['role_name']) ?>


</option>


<?php endforeach; ?>


</select>



</div>



</div>










<!-- =====================================================
        USERS TABLE

        Database ke users yahan show honge.

===================================================== -->


<div class="users-table-card">



<div class="table-title">


<h2>

All System Users

</h2>


<p>

Manage account access and permissions

</p>


</div>





<div class="table-scroll">


<table class="crm-table">



<thead>


<tr>


<th>

#

</th>


<th>

User

</th>


<th>

Username

</th>


<th>

Email

</th>


<th>

Role

</th>


<th>

Designation

</th>


<th>

Phone

</th>


<th>

Status

</th>


<th>

Last Login

</th>


<th>

Action

</th>


</tr>


</thead>




<tbody>



<?php if(empty($users)): ?>


<tr>


<td colspan="10" class="empty">


No users found.


</td>


</tr>



<?php else: ?>




<?php foreach($users as $index=>$user): ?>



<tr>


<td>

<?= $index+1 ?>

</td>




<td>


<div class="user-profile">



<div class="avatar">


<?= strtoupper(substr($user['full_name'],0,1)) ?>


</div>



<div>


<strong>

<?= htmlspecialchars($user['full_name']) ?>

</strong>



<?php if($user['id']==$_SESSION['user_id']): ?>


<span class="you-badge">

You

</span>


<?php endif; ?>


</div>


</div>


</td>







<td>


<?= htmlspecialchars($user['username']) ?>


</td>







<td>


<?= htmlspecialchars($user['email']) ?>


</td>







<td>


<span class="role-badge">


<?= htmlspecialchars($user['role_name'] ?? 'No Role') ?>


</span>


</td>






<td>


<?= htmlspecialchars($user['designation'] ?? '-') ?>


</td>






<td>


<?= htmlspecialchars($user['phone'] ?? '-') ?>


</td>








<td>



<?php if($user['is_active']): ?>


<span class="active-status">

Active

</span>


<?php else: ?>


<span class="inactive-status">

Inactive

</span>


<?php endif; ?>


</td>







<td>


<?php


if($user['last_login']){


echo date(

'd M Y, h:i A',

strtotime($user['last_login'])

);


}

else{


echo "Never";


}



?>


</td>







<td>



<a

href="edit_user.php?id=<?= $user['id'] ?>"

class="action edit"

title="Edit User"

>

✏️

</a>







<?php if($user['id'] != $_SESSION['user_id']): ?>


<a

href="user_actions.php?action=toggle&id=<?= $user['id'] ?>"

class="action toggle"

onclick="return confirm('Are you sure?')"

>

<?php if($user['is_active']): ?>

🔒

<?php else: ?>

🔓

<?php endif; ?>


</a>


<?php endif; ?>




</td>




</tr>




<?php endforeach; ?>



<?php endif; ?>



</tbody>



</table>



</div>



</div>









<style>



/*====================================================
        STATISTICS GRID

        CSS Grid cards ko automatically
        arrange karta hai.

====================================================*/


.stats-container{


display:grid;



grid-template-columns:

repeat(

auto-fit,

minmax(240px,1fr)

);



gap:25px;



margin-top:35px;



}






.user-stat-card{


padding:25px;



border-radius:25px;



display:flex;



align-items:center;



gap:20px;



background:

rgba(255,255,255,.07);



border:

1px solid rgba(255,255,255,.12);



backdrop-filter:

blur(20px);



transition:.4s;



}




.user-stat-card:hover{


transform:

translateY(-10px);



box-shadow:

0 20px 50px rgba(0,0,0,.35);



}







.stat-icon{


width:65px;



height:65px;



border-radius:20px;



display:flex;



align-items:center;



justify-content:center;



font-size:30px;



background:

rgba(255,255,255,.15);



}







.user-stat-card h3{


color:#cbd5e1;


font-size:15px;


}





.user-stat-card h1{


color:white;


font-size:35px;


margin:5px 0;


}





.user-stat-card p{


color:#94a3b8;


font-size:13px;


}








.blue-card .stat-icon{


background:

rgba(37,99,235,.3);


}



.green-card .stat-icon{


background:

rgba(16,185,129,.3);


}



.purple-card .stat-icon{


background:

rgba(139,92,246,.3);


}



.orange-card .stat-icon{


background:

rgba(245,158,11,.3);


}








/*====================================================
        TOOLBAR

====================================================*/


.toolbar{


margin-top:35px;



display:flex;



justify-content:space-between;



gap:20px;



}



.search-box,
.filter-box{


background:

rgba(255,255,255,.08);



padding:15px 20px;



border-radius:20px;



border:

1px solid rgba(255,255,255,.12);



}



.search-box input,
.filter-box select{


background:transparent;



border:none;



outline:none;



color:white;



}





.filter-box option{


color:black;


}








/*====================================================
        TABLE CARD

====================================================*/


.users-table-card{


margin-top:30px;



padding:30px;



border-radius:25px;



background:

rgba(255,255,255,.06);



border:

1px solid rgba(255,255,255,.12);



backdrop-filter:

blur(20px);



}






.table-title h2{


color:white;


}



.table-title p{


color:#94a3b8;


margin-bottom:25px;


}







.table-scroll{


overflow-x:auto;


}







.crm-table{


width:100%;


border-collapse:collapse;


}




.crm-table th{


padding:18px;



color:#94a3b8;



text-align:left;



}



.crm-table td{


padding:18px;



border-top:

1px solid rgba(255,255,255,.08);



color:#e2e8f0;



}





.crm-table tr{


transition:.3s;


}



.crm-table tbody tr:hover{


background:

rgba(255,255,255,.06);



transform:

scale(1.01);



}







.user-profile{


display:flex;



align-items:center;



gap:15px;



}





.avatar{


width:45px;



height:45px;



border-radius:50%;



display:flex;



align-items:center;



justify-content:center;



background:

linear-gradient(

135deg,

#2563eb,

#8b5cf6

);



font-weight:bold;



color:white;



}







.you-badge{


font-size:11px;



padding:3px 8px;



background:#2563eb;



border-radius:20px;



color:white;



}




.role-badge,
.active-status,
.inactive-status{


padding:6px 12px;



border-radius:30px;



font-size:12px;



}





.role-badge{


background:

rgba(56,189,248,.2);



color:#38bdf8;



}




.active-status{


background:

rgba(16,185,129,.2);



color:#34d399;



}




.inactive-status{


background:

rgba(239,68,68,.2);



color:#f87171;



}






.action{


display:inline-flex;



width:38px;



height:38px;



align-items:center;



justify-content:center;



border-radius:12px;



text-decoration:none;



background:

rgba(255,255,255,.1);



margin:3px;



transition:.3s;



}



.action:hover{


transform:

translateY(-5px);



background:#2563eb;



}



.empty{


text-align:center;


padding:50px;


color:#94a3b8;


}



</style>
<!-- =====================================================
        USER MANAGEMENT LOWER SECTION

        Yahan extra management tools add hain.

        Professional CRM dashboards me
        sirf table nahi hoti,
        balkay insights bhi hotay hain.

===================================================== -->



<div class="management-grid">





<!-- =====================================================
        ROLE MANAGEMENT CARD

===================================================== -->


<div class="management-card">


<div class="card-heading">


<h2>

🔐 Roles Overview

</h2>


<p>

Permission levels in system

</p>


</div>





<div class="role-list">



<?php foreach($roles as $role): ?>


<div class="role-item">


<div class="role-circle">

🛡️

</div>



<div>


<h4>

<?= htmlspecialchars($role['role_name']) ?>

</h4>



<p>

System Access Role

</p>



</div>



</div>



<?php endforeach; ?>



</div>




</div>







<!-- =====================================================
        ACTIVITY TIMELINE

===================================================== -->


<div class="management-card">


<div class="card-heading">


<h2>

📌 Recent Activity

</h2>


<p>

Latest user actions

</p>


</div>






<div class="activity-line">



<div class="activity-item">


<div class="activity-icon">

👤

</div>


<div>


<h4>

New User Created

</h4>


<p>

A new account was added.

</p>


<span>

10 minutes ago

</span>


</div>


</div>







<div class="activity-item">


<div class="activity-icon">

🔑

</div>


<div>


<h4>

Password Updated

</h4>


<p>

Security credentials changed.

</p>


<span>

1 hour ago

</span>


</div>


</div>







<div class="activity-item">


<div class="activity-icon">

⚙️

</div>


<div>


<h4>

Role Changed

</h4>


<p>

Permission updated successfully.

</p>


<span>

Today

</span>


</div>


</div>




</div>



</div>







</div>










<!-- =====================================================
        ADD USER MODAL

        Existing PHP action same rakhi hai.

        Sirf design change kiya gaya hai.

===================================================== -->



<div class="custom-modal" id="addUserModal">


<div class="modal-box">



<div class="modal-header">


<h2>

➕ Create New User

</h2>



<button onclick="closeModal()">

×

</button>



</div>








<form action="user_actions.php" method="POST">


<input type="hidden" name="action" value="add">






<div class="input-group">


<label>

Full Name

</label>


<input

type="text"

name="full_name"

required

>



</div>







<div class="input-group">


<label>

Email Address

</label>


<input

type="email"

name="email"

required

>



</div>







<div class="input-group">


<label>

Username

</label>


<input

type="text"

name="username"

required

>



</div>








<div class="input-group">


<label>

Password

</label>


<input

type="password"

name="password"

required

>



</div>








<div class="input-group">


<label>

Select Role

</label>


<select name="role_id" required>


<option value="">

Choose Role

</option>


<?php foreach($roles as $role): ?>


<option value="<?= $role['id'] ?>">


<?= htmlspecialchars($role['role_name']) ?>


</option>


<?php endforeach; ?>


</select>



</div>








<div class="input-group">


<label>

Designation

</label>


<input

type="text"

name="designation"

>



</div>








<div class="input-group">


<label>

Phone

</label>


<input

type="text"

name="phone"

>



</div>









<div class="modal-actions">


<button

type="button"

onclick="closeModal()"

class="cancel-btn"

>

Cancel

</button>





<button

type="submit"

class="save-btn"

>

Create User

</button>


</div>





</form>




</div>



</div>









<style>




/*====================================================
        MANAGEMENT GRID

====================================================*/


.management-grid{


display:grid;



grid-template-columns:

repeat(

2,

1fr

);



gap:30px;



margin-top:35px;



}






.management-card{


padding:30px;



border-radius:25px;



background:

rgba(255,255,255,.07);



border:

1px solid rgba(255,255,255,.12);



backdrop-filter:

blur(25px);



}



.card-heading h2{


color:white;


}



.card-heading p{


color:#94a3b8;



margin-top:5px;


}








/*====================================================
        ROLE ITEMS

====================================================*/


.role-item{


display:flex;



align-items:center;



gap:15px;



padding:15px;



margin-top:15px;



border-radius:18px;



background:

rgba(255,255,255,.06);



transition:.3s;



}




.role-item:hover{


transform:

translateX(8px);



background:

rgba(37,99,235,.2);



}





.role-circle{


width:45px;



height:45px;



display:flex;



align-items:center;



justify-content:center;



border-radius:50%;



background:

rgba(37,99,235,.3);



}





.role-item h4{


color:white;


}





.role-item p{


color:#94a3b8;



font-size:13px;



}








/*====================================================
        ACTIVITY TIMELINE

====================================================*/


.activity-item{


display:flex;



gap:15px;



padding:18px 0;



border-bottom:

1px solid rgba(255,255,255,.1);



}




.activity-icon{


width:45px;



height:45px;



border-radius:50%;



display:flex;



align-items:center;



justify-content:center;



background:

rgba(56,189,248,.2);



}




.activity-item h4{


color:white;


}



.activity-item p{


color:#94a3b8;



font-size:14px;



margin:4px 0;



}




.activity-item span{


color:#38bdf8;



font-size:12px;



}










/*====================================================
        CUSTOM MODAL

        Bootstrap ke baghair
        lightweight modern modal.

====================================================*/


.custom-modal{


display:none;



position:fixed;



top:0;



left:0;



width:100%;



height:100%;



background:

rgba(0,0,0,.7);



backdrop-filter:

blur(10px);



align-items:center;



justify-content:center;



z-index:999;



}






.modal-box{


width:500px;



max-width:95%;



padding:35px;



border-radius:30px;



background:

#0f172a;



border:

1px solid rgba(255,255,255,.15);



box-shadow:

0 30px 80px rgba(0,0,0,.6);



animation:

modalOpen .4s ease;



}







@keyframes modalOpen{


from{


opacity:0;


transform:

scale(.8);



}


to{


opacity:1;


transform:

scale(1);



}


}





.modal-header{


display:flex;



justify-content:space-between;



align-items:center;



margin-bottom:25px;



}





.modal-header h2{


color:white;


}



.modal-header button{


background:none;



border:none;



color:white;



font-size:30px;



cursor:pointer;



}







.input-group{


margin-bottom:18px;



}





.input-group label{


display:block;



color:#cbd5e1;



margin-bottom:8px;



}





.input-group input,
.input-group select{


width:100%;



padding:14px;



border-radius:15px;



border:

1px solid rgba(255,255,255,.15);



background:

rgba(255,255,255,.08);



color:white;



outline:none;



}





.input-group select option{


color:black;


}







.modal-actions{


display:flex;



justify-content:flex-end;



gap:15px;



margin-top:25px;



}



.cancel-btn,
.save-btn{


padding:12px 25px;



border-radius:50px;



border:none;



cursor:pointer;



font-weight:600;



}





.cancel-btn{


background:#334155;



color:white;



}




.save-btn{


background:

linear-gradient(

135deg,

#2563eb,

#8b5cf6

);



color:white;



}




</style>







<script>


// =====================================================
// MODAL CONTROL
//
// Button click par modal open/close hota hai.
//
// Simple JavaScript use ki gayi hai
// taake extra libraries ki zaroorat na ho.
//
// =====================================================



function openModal(){


document.getElementById("addUserModal")
.style.display="flex";


}





function closeModal(){


document.getElementById("addUserModal")
.style.display="none";


}


</script>
<style>


/*====================================================
        RESPONSIVE DESIGN

        Different screen sizes ke liye layout adjust.

        Desktop:
        2 columns

        Mobile:
        1 column

====================================================*/



@media(max-width:1200px){


.main-content{


margin-left:280px;


}



.management-grid{


grid-template-columns:1fr;



}



}






@media(max-width:900px){



/*
    Mobile/tablet par sidebar ki wajah se
    content squeeze na ho.

*/


.main-content{


margin-left:0;



padding:20px;



}







.page-header{


flex-direction:column;



align-items:flex-start;



gap:20px;



}







.toolbar{


flex-direction:column;



}



}





@media(max-width:600px){



.page-header h1{


font-size:24px;


}





.stats-container{


grid-template-columns:1fr;



}




.user-stat-card{


padding:20px;



}




.crm-table{


font-size:13px;


}



.modal-box{


padding:25px;


}



}




/*====================================================
        FUTURISTIC ANIMATIONS

        Cards ko smooth floating effect.

====================================================*/


.user-stat-card,
.users-table-card,
.management-card{


animation:

floatAnimation 6s infinite ease-in-out;



}






@keyframes floatAnimation{


0%{


transform:translateY(0);



}



50%{


transform:translateY(-6px);



}



100%{


transform:translateY(0);



}



}







/*====================================================
        GLOW EFFECT

        Hover par futuristic CRM feeling.

====================================================*/


.users-table-card:hover,
.management-card:hover{


box-shadow:


0 0 40px rgba(56,189,248,.15),


0 0 80px rgba(37,99,235,.15);



}








/*====================================================
        TABLE ROW LIGHT EFFECT

====================================================*/


.crm-table tbody tr:hover td{


color:white;



}








/*====================================================
        BUTTON ACTIVE EFFECT

====================================================*/


.save-btn:hover,
.add-user-btn:hover{


filter:

brightness(1.2);



}




</style>








<script>


// =====================================================
// ADD USER BUTTON CONNECT
//
// Header button ko modal ke sath connect karna.
//
// Jab admin button press karega
// modal open hoga.
//
// =====================================================



document.querySelector(".add-user-btn")
.onclick=function(){


openModal();


};







// Modal ke bahar click karne par close

window.onclick=function(event){


let modal=document.getElementById("addUserModal");



if(event.target==modal){


closeModal();


}



}



</script>









<!-- =====================================================
        PAGE END

===================================================== -->



</div>


<!-- main-content closing -->


</body>


</html>