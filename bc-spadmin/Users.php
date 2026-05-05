<?php session_start();
    include("../func/bc-spadmin-config.php");

    if(isset($_POST["export-users"])){
        $status = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["status"])));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=all_users.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('S/N', 'Vendor', 'Fullname', 'Username ID', 'Level', 'Balance', 'Phone number', 'Address', 'Referral', 'API Status', 'APIKey', 'Security Answer', 'Reg Date'));

        $sql = "SELECT u.*, v.site_url FROM sas_users u LEFT JOIN sas_vendors v ON u.vendor_id = v.id";
        if($status != 'all'){
            $sql .= " WHERE u.status='$status'";
        }
        $result = mysqli_query($connection_server, $sql);
        $sn = 1;
        while($row = mysqli_fetch_assoc($result)){
            $fullname = $row['firstname'] . ' ' . $row['lastname'] . ' ' . $row['othername'];
            $referral_username = "Not Referred";
            if(!empty($row["referral_id"]) && is_numeric($row["referral_id"])){
                $get_user_referral_details = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_users WHERE id='".$row["referral_id"]."'"));
                $referral_username = $get_user_referral_details["username"];
            }

            $api_status = ($row['api_status'] == 1) ? 'Enabled' : 'Disabled';

            fputcsv($output, array(
                $sn++,
                $row['site_url'] ?? 'N/A',
                $fullname,
                $row['username'],
                accountLevel($row['account_level']),
                $row['balance'],
                $row['phone_number'],
                $row['home_address'],
                $referral_username,
                $api_status,
                $row['api_key'],
                $row['security_answer'],
                formDate($row['reg_date'])
            ));
        }
        fclose($output);
        exit();
    }

    if(isset($_GET["account-status"])){
        $status = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["account-status"])));
        $account_user = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["account-username"])));
        $vendor_id = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["vid"])));
        $statusArray = array(1, 2, 3);
        if(is_numeric($status)){
            if(in_array($status, $statusArray)){
		$send_mail_to_user = false;
		$get_user_details = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_users WHERE vendor_id='$vendor_id' && username='$account_user' LIMIT 1"));

                if($status == 1 || $status == 2 || $status == 3){
                    mysqli_query($connection_server, "UPDATE sas_users SET status='$status' WHERE vendor_id='$vendor_id' && username='$account_user'");
                    $status_text = ($status == 1) ? "activated" : (($status == 2) ? "deactivated" : "deleted");
                    $json_response_array = array("desc" => ucwords($account_user." account $status_text successfully"));
                    $json_response_encode = json_encode($json_response_array,true);
                }
            }else{
                $json_response_array = array("desc" => "Invalid Status Code");
                $json_response_encode = json_encode($json_response_array,true);
            }
        }else{
            $json_response_array = array("desc" => "Non-numeric string");
            $json_response_encode = json_encode($json_response_array,true);
        }
        $json_response_decode = json_decode($json_response_encode,true);
        $_SESSION["product_purchase_response"] = $json_response_decode["desc"];
        header("Location: /bc-spadmin/Users.php");
        exit();
    }

    if(isset($_GET["account-api-status"])){
        $status = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["account-api-status"])));
        $account_user = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["account-username"])));
        $vendor_id = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["vid"])));
        $statusArray = array(1, 2);

        if(is_numeric($status)){
            if(in_array($status, $statusArray)){
                mysqli_query($connection_server, "UPDATE sas_users SET api_status='$status' WHERE vendor_id='$vendor_id' && username='$account_user'");
                $status_text = ($status == 1) ? "activated" : "deactivated";
                $json_response_array = array("desc" => ucwords($account_user." account API status $status_text successfully"));
                $json_response_encode = json_encode($json_response_array,true);
            }else{
                $json_response_array = array("desc" => "Invalid Status Code");
                $json_response_encode = json_encode($json_response_array,true);
            }
        }else{
            $json_response_array = array("desc" => "Non-numeric string");
            $json_response_encode = json_encode($json_response_array,true);
        }
        $json_response_decode = json_decode($json_response_encode,true);
        $_SESSION["product_purchase_response"] = $json_response_decode["desc"];
        header("Location: /bc-spadmin/Users.php");
        exit();
    }

?>
<!DOCTYPE html>
<head>
    <title>All Users | Super Admin</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link rel="stylesheet" href="/cssfile/bc-style.css">

  <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets-2/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="../assets-2/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include("../func/bc-spadmin-header.php"); ?>

    <div class="pagetitle">
      <h1>ALL PLATFORM USERS</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Users</li>
        </ol>
      </nav>
    </div>

    <section class="section dashboard">
      <div class="col-12">
        <?php
            $limit = 50;
            $page_num = (isset($_GET["page"]) && is_numeric($_GET["page"]) && $_GET["page"] >= 1) ? (int)$_GET["page"] : 1;
            $offset = ($page_num - 1) * $limit;

            $search_statement = "";
            $search_parameter = "";
            if(isset($_GET["searchq"]) && !empty(trim($_GET["searchq"]))){
                $sq = mysqli_real_escape_string($connection_server, trim($_GET["searchq"]));
                $search_statement = " WHERE (u.email LIKE '%$sq%' OR u.phone_number LIKE '%$sq%' OR u.username LIKE '%$sq%' OR u.firstname LIKE '%$sq%' OR u.lastname LIKE '%$sq%' OR v.site_url LIKE '%$sq%')";
                $search_parameter = "searchq=$sq&";
            }

            if(isset($_GET["vid"]) && is_numeric($_GET["vid"])){
                $vid = (int)$_GET["vid"];
                if(empty($search_statement)) $search_statement = " WHERE u.vendor_id='$vid'";
                else $search_statement .= " AND u.vendor_id='$vid'";
                $search_parameter .= "vid=$vid&";
            }

            $sql = "SELECT u.*, v.site_url, v.id as vid FROM sas_users u LEFT JOIN sas_vendors v ON u.vendor_id = v.id $search_statement ORDER BY u.reg_date DESC LIMIT $limit OFFSET $offset";
            $get_users = mysqli_query($connection_server, $sql);
        ?>

        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-body p-4">
                <form method="get" action="Users.php" class="row g-2 mb-4">
                    <div class="col-md-5">
                        <input name="searchq" type="text" value="<?php echo $_GET["searchq"] ?? ''; ?>" placeholder="Search Email, Username, Vendor..." class="form-control" />
                    </div>
                    <div class="col-md-3">
                        <select name="vid" class="form-select">
                            <option value="">All Vendors</option>
                            <?php
                                $vs = mysqli_query($connection_server, "SELECT id, site_url FROM sas_vendors ORDER BY site_url ASC");
                                while($vrow = mysqli_fetch_assoc($vs)){
                                    $selected = (isset($_GET["vid"]) && $_GET["vid"] == $vrow['id']) ? 'selected' : '';
                                    echo '<option value="'.$vrow['id'].'" '.$selected.'>'.$vrow['site_url'].'</option>';
                                }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-secondary w-100" data-bs-toggle="collapse" data-bs-target="#exportCollapse">Export</button>
                    </div>
                </form>

                <div class="collapse" id="exportCollapse">
                    <form method="post" action="Users.php" class="d-flex gap-2 p-3 border rounded">
                        <select name="status" class="form-select">
                            <option value="all">All Status</option>
                            <option value="1">Active</option>
                            <option value="2">Blocked</option>
                            <option value="3">Deleted</option>
                        </select>
                        <button name="export-users" type="submit" class="btn btn-success text-nowrap">Download CSV</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-primary">Platform Users</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                    <thead class="bg-light">
                      <tr>
                          <th class="ps-4">S/N</th><th>User / Vendor</th><th>Account</th><th>Financials</th><th>Status</th><th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php
                    $count = $offset + 1;
                    if(mysqli_num_rows($get_users) > 0){
                        while($user = mysqli_fetch_assoc($get_users)){
                            $status_badge = ($user['status'] == 1) ? '<span class="badge bg-success">Active</span>' : (($user['status'] == 2) ? '<span class="badge bg-warning">Blocked</span>' : '<span class="badge bg-danger">Deleted</span>');

                            echo '
                            <tr>
                                <td class="ps-4">'.$count++.'</td>
                                <td>
                                    <div class="fw-bold">'.ucwords($user["firstname"]." ".$user["lastname"]).'</div>
                                    <div class="small text-muted">@'.$user['username'].'</div>
                                    <div class="small text-primary">'.$user['site_url'].'</div>
                                </td>
                                <td>
                                    <div class="small">'.accountLevel($user["account_level"]).'</div>
                                    <div class="small text-muted">'.$user['phone_number'].'</div>
                                </td>
                                <td>
                                    <div class="fw-bold">₦'.number_format($user["balance"], 2).'</div>
                                </td>
                                <td>'.$status_badge.'</td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="UserEdit.php?userID='.$user['id'].'" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <button class="btn btn-sm btn-outline-warning" onclick="updateStatus(`2`, `'.$user['username'].'`, `'.$user['vid'].'`)" title="Suspend"><i class="bi bi-ban"></i></button>
                                        <button class="btn btn-sm btn-outline-success" onclick="updateStatus(`1`, `'.$user['username'].'`, `'.$user['vid'].'`)" title="Activate"><i class="bi bi-check"></i></button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="updateStatus(`3`, `'.$user['username'].'`, `'.$user['vid'].'`)" title="Delete"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>';
                        }
                    } else {
                        echo '<tr><td colspan="6" class="text-center py-4">No users found.</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-center gap-2">
            <?php if($page_num > 1){ ?>
                <a href="Users.php?<?php echo $search_parameter; ?>page=<?php echo $page_num - 1; ?>" class="btn btn-primary">Prev</a>
            <?php } ?>
            <?php
                $total_users = mysqli_num_rows(mysqli_query($connection_server, "SELECT u.id FROM sas_users u LEFT JOIN sas_vendors v ON u.vendor_id = v.id $search_statement"));
                if(($offset + $limit) < $total_users){
            ?>
                <a href="Users.php?<?php echo $search_parameter; ?>page=<?php echo $page_num + 1; ?>" class="btn btn-primary">Next</a>
            <?php } ?>
        </div>
      </div>
    </section>

    <script>
    function updateStatus(status, user, vid){
        if(confirm("Change status for @" + user + "?")){
            window.location.href = "Users.php?account-status=" + status + "&account-username=" + user + "&vid=" + vid;
        }
    }
    </script>

    <?php include("../func/bc-spadmin-footer.php"); ?>
</body>
</html>