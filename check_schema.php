<?php
include("func/bc-connect.php");
$res = mysqli_query($connection_server, "SHOW CREATE TABLE sas_super_admin_options");
$row = mysqli_fetch_assoc($res);
print_r($row);

// Also check for duplicates
$res2 = mysqli_query($connection_server, "SELECT option_name, COUNT(*) as c FROM sas_super_admin_options GROUP BY option_name HAVING c > 1");
while($row2 = mysqli_fetch_assoc($res2)) {
    print_r($row2);
}
?>
