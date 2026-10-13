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