<?php
require '/var/www/html/syedpro_new/includes/db.php';
echo 'PHP tz:  ' . date_default_timezone_get() . "\n";
echo 'PHP now: ' . date('Y-m-d H:i:s') . "\n";
$r = mysqli_fetch_row(mysqli_query($con, "SELECT NOW(), @@session.time_zone"));
echo 'DB now:  ' . $r[0] . ' (session tz: ' . $r[1] . ")\n";
