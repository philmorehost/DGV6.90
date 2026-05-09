<?php
include("func/bc-connect.php");
$res = mysqli_query($connection_server, "SHOW COLUMNS FROM sas_vendors");
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
