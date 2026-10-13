<?php
// =======================================================
// FILE: customizer.php
// PURPOSE:
// CRM Dynamic Client Form Field Manager
//
// Is page ka kaam:
// 1. New custom fields banana
// 2. Existing fields edit karna
// 3. Fields delete karna
// 4. Client forms ko customize karna
//
// Database:
// form_fields table use ho rahi hai
// =======================================================



// =======================================================
// STEP 1:
// Load database + session system
//
// log_in.php me:
// - database connection
// - session start
// - login variables
//
// hotay hain.
// =======================================================


require_once 'log_in.php';





// =======================================================
// STEP 2:
// Security Check
//
// Agar user login nahi hai,
// to customizer access nahi milega.
//
// =======================================================


if (!isset($_SESSION['user_id'])) {


    header("Location:index.php");

    exit();


}






// =======================================================
// STEP 3:
// Sidebar Load
//
// Security ke baad sidebar load karna
// better practice hai.
//
// =======================================================


require_once 'sidebar.php';








// =======================================================
// STEP 4:
// Default Variables
//
// Ye bohat important hai.
//
// Agar edit mode nahi hai,
// to PHP ko batana hota hai ke
// $editField empty hai.
//
// Warna Undefined variable warning aati hai.
//
// =======================================================


$editField = null;


$fields = [];









// =======================================================
// ADD NEW FIELD
// =======================================================


if(isset($_POST['add_field'])){


    $label = trim($_POST['field_label']);



    // Example:
    //
    // Customer Name
    //
    // convert hoga:
    //
    // customer_name


    $name = strtolower(

        str_replace(

            ' ',

            '_',

            trim($_POST['field_name'])

        )

    );



    $type = $_POST['field_type'];



    $category = trim($_POST['category']);



    $required = isset($_POST['is_required']) ? 1 : 0;



    $visible = isset($_POST['is_visible']) ? 1 : 0;



    $options = trim($_POST['options']);






    $sql = "

    INSERT INTO form_fields

    (
        field_label,
        field_name,
        field_type,
        category,
        is_required,
        is_visible,
        options
    )

    VALUES

    (?,?,?,?,?,?,?)

    ";





    $stmt = $conn->prepare($sql);



    $stmt->execute([


        $label,

        $name,

        $type,

        $category,

        $required,

        $visible,

        $options


    ]);






    echo "

    <script>

    alert('Field Added Successfully');

    window.location='customizer.php';

    </script>

    ";



    exit();



}









// =======================================================
// UPDATE EXISTING FIELD
// =======================================================


if(isset($_POST['update_field'])){



    $id = $_POST['field_id'];



    $label = trim($_POST['field_label']);



    $type = $_POST['field_type'];



    $category = trim($_POST['category']);



    $required = isset($_POST['is_required']) ? 1 : 0;



    $visible = isset($_POST['is_visible']) ? 1 : 0;



    $options = trim($_POST['options']);







    $sql = "

    UPDATE form_fields SET


    field_label=?,

    field_type=?,

    category=?,

    is_required=?,

    is_visible=?,

    options=?


    WHERE id=?


    ";





    $stmt=$conn->prepare($sql);





    $stmt->execute([


        $label,

        $type,

        $category,

        $required,

        $visible,

        $options,

        $id


    ]);






    echo "

    <script>

    alert('Field Updated Successfully');

    window.location='customizer.php';

    </script>

    ";



    exit();


}











// =======================================================
// DELETE FIELD
// =======================================================


if(isset($_GET['delete'])){


    $id=$_GET['delete'];




    $stmt=$conn->prepare(

        "DELETE FROM form_fields WHERE id=?"

    );



    $stmt->execute([$id]);





    echo "

    <script>

    alert('Field Deleted');

    window.location='customizer.php';

    </script>


    ";




    exit();


}











// =======================================================
// LOAD FIELD FOR EDIT
//
// Agar URL me:
// customizer.php?edit=5
//
// hai to field data form me show hoga.
//
// =======================================================



if(isset($_GET['edit'])){


    $id=$_GET['edit'];




    $stmt=$conn->prepare(

        "SELECT * FROM form_fields WHERE id=?"

    );



    $stmt->execute([$id]);



    $editField=$stmt->fetch(PDO::FETCH_ASSOC);



}











// =======================================================
// FETCH ALL FIELDS
// =======================================================



$stmt=$conn->prepare(

"
SELECT *

FROM form_fields

ORDER BY sort_order ASC,id ASC

"

);



$stmt->execute();



$fields=$stmt->fetchAll(PDO::FETCH_ASSOC);




?>



<!DOCTYPE html>

<html lang="en">


<head>


<meta charset="UTF-8">


<meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>

CRM Customizer

</title>





<style>


/* =====================================================
GLOBAL SETTINGS

Browser ki default spacing remove karte hain.

Is se design control hamaray paas hota hai.

===================================================== */


*{


margin:0;


padding:0;


box-sizing:border-box;


font-family:'Segoe UI',sans-serif;



}





body{


background:

radial-gradient(

circle at top left,

#1d4ed8,

transparent 35%

),


#020617;



min-height:100vh;


color:white;


}









/* =====================================================
MAIN AREA

Sidebar fixed hai.

Isliye left space diya gaya hai.

===================================================== */


.main-content{


margin-left:320px;


padding:35px;


}









/* =====================================================
HEADER CARD

Glass effect:

Transparent + blur

Premium CRM look deta hai.

===================================================== */


.header-card{


background:

rgba(255,255,255,.08);



border:

1px solid rgba(255,255,255,.15);



backdrop-filter:blur(20px);



padding:35px;



border-radius:30px;



display:flex;



justify-content:space-between;



align-items:center;



}


.header-card h1{


font-size:35px;


}


.header-card p{


color:#cbd5e1;


margin-top:10px;


}






.neon-btn{


background:

linear-gradient(

135deg,

#06b6d4,

#2563eb

);



padding:14px 28px;



border-radius:50px;



color:white;



border:none;



font-weight:bold;



cursor:pointer;


transition:.3s;



}



.neon-btn:hover{


transform:translateY(-5px);



box-shadow:

0 15px 40px #2563eb;


}








/* =====================================================
        STAT CARDS

Cards CRM dashboard jaisa look deti hain.

Hover par upar move hoti hain
taake modern animation feel aaye.

===================================================== */


.stats-area{


display:grid;


grid-template-columns:

repeat(auto-fit,minmax(240px,1fr));


gap:25px;


margin-top:35px;



}




.stat-box{


padding:25px;


border-radius:25px;



background:

rgba(255,255,255,.08);



border:

1px solid rgba(255,255,255,.12);



backdrop-filter:

blur(20px);



transition:.4s;



}




.stat-box:hover{


transform:

translateY(-8px);



box-shadow:

0 20px 50px rgba(37,99,235,.35);



}



.stat-box h3{


color:#94a3b8;



}



.stat-box h1{


font-size:40px;


margin-top:15px;



}









/* =====================================================
FORM SECTION

===================================================== */


.form-card{


margin-top:35px;



padding:35px;



background:

rgba(255,255,255,.08);



border-radius:30px;



border:

1px solid rgba(255,255,255,.15);



backdrop-filter:

blur(20px);



}





.form-grid{


display:grid;



grid-template-columns:

repeat(2,1fr);



gap:25px;



}





.field label{


display:block;



margin-bottom:8px;



color:#cbd5e1;



}



.field input,
.field select,
.field textarea{


width:100%;


padding:14px;



border-radius:15px;



border:

1px solid rgba(255,255,255,.2);



background:

rgba(255,255,255,.08);



color:white;



outline:none;



}




.field textarea{


height:100px;


resize:none;


}




.field option{


color:black;


}





.checkbox-group{


display:flex;



gap:30px;



margin-top:25px;



}





.save-btn{


margin-top:25px;



padding:15px 35px;



border:none;



border-radius:50px;



background:

linear-gradient(

135deg,

#22d3ee,

#2563eb

);



color:white;



font-weight:bold;



cursor:pointer;



transition:.3s;



}




.save-btn:hover{


transform:

translateY(-5px);



box-shadow:

0 15px 40px #2563eb;



}









/* =====================================================
TABLE DESIGN

===================================================== */



.table-card{


margin-top:35px;



padding:30px;



border-radius:30px;



background:

rgba(255,255,255,.08);



backdrop-filter:

blur(20px);



border:

1px solid rgba(255,255,255,.15);



}





.crm-table{


width:100%;



border-collapse:collapse;



}





.crm-table th{


padding:18px;



text-align:left;



color:#94a3b8;



}




.crm-table td{


padding:18px;



border-top:

1px solid rgba(255,255,255,.1);



}





.crm-table tr{


transition:.3s;


}



.crm-table tbody tr:hover{


background:

rgba(255,255,255,.08);



transform:

scale(1.01);



}





.badge{


padding:6px 14px;



border-radius:50px;



background:

rgba(56,189,248,.2);



color:#67e8f9;



font-size:13px;



}




.green{


background:

rgba(16,185,129,.2);



color:#34d399;



}




.red{


background:

rgba(239,68,68,.2);



color:#f87171;



}






.action{


padding:8px 15px;



border-radius:30px;



text-decoration:none;



margin-right:5px;



font-size:13px;



}




.edit{


background:#2563eb;


color:white;


}



.delete{


background:#ef4444;


color:white;


}





</style>





</head>


<body>





<div class="main-content">



<div class="header-card">


<div>


<h1>

⚙️ CRM Form Customizer

</h1>


<p>

Create unlimited client fields dynamically

</p>


</div>



<button class="neon-btn"
onclick="document.getElementById('fieldForm').scrollIntoView({behavior:'smooth'})">

+ Add Field

</button>


</div>







<div class="stats-area">


<div class="stat-box">

<h3>

Total Fields

</h3>

<h1>

<?=count($fields)?>

</h1>

</div>




<div class="stat-box">

<h3>

Visible

</h3>


<h1>

<?php


$total=0;


foreach($fields as $f){


if($f['is_visible']){


$total++;


}


}


echo $total;


?>

</h1>

</div>





<div class="stat-box">


<h3>

Required

</h3>


<h1>


<?php


$total=0;


foreach($fields as $f){


if($f['is_required']){


$total++;


}


}


echo $total;


?>

</h1>

</div>


</div>









<div class="form-card" id="fieldForm">


<h2>

<?= $editField ? "✏️ Edit Field":"➕ Add New Field" ?>

</h2>




<form method="POST">



<?php if($editField): ?>


<input type="hidden"
name="field_id"
value="<?=$editField['id']?>">


<?php endif; ?>





<div class="form-grid">



<div class="field">

<label>

Field Label

</label>


<input

type="text"

name="field_label"

required

value="<?=

$editField

?

htmlspecialchars($editField['field_label'])

:

''

?>"

>


</div>







<div class="field">


<label>

Database Name

</label>


<input

type="text"

name="field_name"

<?= $editField ? "readonly":"" ?>

value="<?=

$editField

?

htmlspecialchars($editField['field_name'])

:

''

?>"

>


</div>







<div class="field">


<label>

Field Type

</label>



<select name="field_type">


<?php


$types=[

'text',

'number',

'email',

'select',

'textarea',

'date'

];


foreach($types as $t):

?>


<option value="<?=$t?>"

<?=

($editField && $editField['field_type']==$t)

?

'selected'

:

''

?>

>


<?=ucfirst($t)?>


</option>


<?php endforeach; ?>



</select>



</div>






<div class="field">


<label>

Category

</label>


<input

type="text"

name="category"

value="<?=

$editField

?

htmlspecialchars($editField['category'])

:

'General'

?>"

>


</div>


</div>








<div class="field" style="margin-top:25px;">


<label>

Dropdown Options

</label>



<textarea name="options">

<?=

$editField

?

htmlspecialchars($editField['options'])

:

''

?>

</textarea>



</div>








<div class="checkbox-group">


<label>


<input type="checkbox"

name="is_required"

<?=

($editField && $editField['is_required'])

?

'checked'

:

''

?>


>


Required



</label>






<label>


<input type="checkbox"

name="is_visible"

<?=

(!$editField || $editField['is_visible'])

?

'checked'

:

''

?>


>


Visible



</label>



</div>








<button

class="save-btn"

type="submit"

name="<?=

$editField

?

'update_field'

:

'add_field'

?>"

>


<?=

$editField

?

'Update Field'

:

'Save Field'

?>



</button>



</form>



</div>









<div class="table-card">


<h2>

📋 Existing Fields

</h2>



<table class="crm-table">


<thead>


<tr>


<th>Label</th>

<th>Name</th>

<th>Type</th>

<th>Category</th>

<th>Required</th>

<th>Visible</th>

<th>Actions</th>


</tr>


</thead>



<tbody>



<?php foreach($fields as $field): ?>


<tr>



<td>

<?=htmlspecialchars($field['field_label'])?>

</td>



<td>

<?=htmlspecialchars($field['field_name'])?>

</td>




<td>


<span class="badge">

<?=$field['field_type']?>

</span>


</td>




<td>

<?=htmlspecialchars($field['category'])?>

</td>




<td>


<span class="<?=

$field['is_required']

?

'badge green'

:

'badge red'

?>">


<?=$field['is_required']?'Yes':'No'?>


</span>


</td>




<td>


<span class="<?=

$field['is_visible']

?

'badge green'

:

'badge red'

?>">


<?=$field['is_visible']?'Yes':'No'?>


</span>


</td>







<td>


<a

href="customizer.php?edit=<?=$field['id']?>"

class="action edit">

Edit

</a>




<a

href="customizer.php?delete=<?=$field['id']?>"

onclick="return confirm('Delete this field?')"

class="action delete">

Delete

</a>



</td>



</tr>



<?php endforeach; ?>




</tbody>


</table>


</div>








</div>




</body>


</html>
<?php
require_once 'log_in.php';
require_once 'sidebar.php';
?>
<!DOCTYPE html>
<html>
<head>
    ...
</head>
<body>

<?php require_once 'sidebar.php'; ?>   <!-- agar pehle se body mein hai to double mat lagana -->

<div class="main-wrapper" style="margin-left:320px; padding:24px;">
    
    <?php require_once 'header_widget.php'; ?>   <!-- ✅ HEADER YAHAN -->

    <!-- page content yahan -->

</div>

</body>
</html>
