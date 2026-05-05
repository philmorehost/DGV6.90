<?php session_start();
    include("../func/bc-spadmin-config.php");
    include("../func/bc-tables.php");

    // Handle Domain Extension Management
    if(isset($_POST['add_extension'])) {
        $ext = mysqli_real_escape_string($connection_server, trim(strtolower($_POST['extension'])));
        $ext = "." . ltrim($ext, "."); // Ensure it starts with a dot
        $price = mysqli_real_escape_string($connection_server, $_POST['price']);

        if(!empty($ext) && is_numeric($price)) {
            $sql = "INSERT INTO sas_domain_extensions (extension, price) VALUES ('$ext', '$price') ON DUPLICATE KEY UPDATE price='$price'";
            if(mysqli_query($connection_server, $sql)) {
                $_SESSION['page_alert'] = "Domain extension $ext added/updated successfully!";
            } else {
                $_SESSION['page_alert'] = "Error adding extension: " . mysqli_error($connection_server);
            }
        }
        header("Location: DomainSettings.php");
        exit();
    }

    if(isset($_GET['delete_ext'])) {
        $del_id = mysqli_real_escape_string($connection_server, $_GET['delete_ext']);
        mysqli_query($connection_server, "DELETE FROM sas_domain_extensions WHERE id='$del_id'");
        $_SESSION['page_alert'] = "Extension deleted successfully.";
        header("Location: DomainSettings.php");
        exit();
    }

    // Handle form submission
    if(isset($_POST['save_settings'])) {
        $nameservers = mysqli_real_escape_string($connection_server, $_POST['nameservers']);
        $ip_address = mysqli_real_escape_string($connection_server, $_POST['ip_address']);
        $registrar_url = mysqli_real_escape_string($connection_server, $_POST['registrar_url']);

        // For simplicity, we'll store these in the sas_super_admin_options table
        // This requires having a table like this:
        // CREATE TABLE sas_super_admin_options (
        //   option_name VARCHAR(255) PRIMARY KEY,
        //   option_value TEXT
        // );

        $sql_nameservers = "INSERT INTO sas_super_admin_options (option_name, option_value) VALUES ('domain_nameservers', '$nameservers') ON DUPLICATE KEY UPDATE option_value = '$nameservers'";
        $sql_ip = "INSERT INTO sas_super_admin_options (option_name, option_value) VALUES ('domain_ip_address', '$ip_address') ON DUPLICATE KEY UPDATE option_value = '$ip_address'";
        $sql_registrar = "INSERT INTO sas_super_admin_options (option_name, option_value) VALUES ('domain_registrar_url', '$registrar_url') ON DUPLICATE KEY UPDATE option_value = '$registrar_url'";

        if(mysqli_query($connection_server, $sql_nameservers) && mysqli_query($connection_server, $sql_ip) && mysqli_query($connection_server, $sql_registrar)) {
            $_SESSION['page_alert'] = "Settings saved successfully!";
        } else {
            $_SESSION['page_alert'] = "Error saving settings: " . mysqli_error($connection_server);
        }
        header("Location: DomainSettings.php");
        exit();
    }

    // Fetch current settings
    $nameservers = '';
    $ip_address = '';
    $registrar_url = '';
    $sql_fetch = "SELECT * FROM sas_super_admin_options WHERE option_name IN ('domain_nameservers', 'domain_ip_address', 'domain_registrar_url')";
    $result = mysqli_query($connection_server, $sql_fetch);
    while($row = mysqli_fetch_assoc($result)) {
        if($row['option_name'] == 'domain_nameservers') {
            $nameservers = $row['option_value'];
        }
        if($row['option_name'] == 'domain_ip_address') {
            $ip_address = $row['option_value'];
        }
        if($row['option_name'] == 'domain_registrar_url') {
            $registrar_url = $row['option_value'];
        }
    }
?>
<!DOCTYPE html>
<head>
    <title>Domain Setup Instructions</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include("../func/bc-spadmin-header.php"); ?>

    <div class="pagetitle">
        <h1>Domain Setup Instructions</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="Dashboard.php">Home</a></li>
                <li class="breadcrumb-item active">Domain Settings</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-primary py-3 border-0 text-white">
                        <h5 class="fw-bold mb-0">Manage Domain Extensions & Pricing</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="" class="row g-3 align-items-end mb-4">
                            <div class="col-md-5">
                                <label class="form-label small fw-bold text-muted">EXTENSION (e.g. .com)</label>
                                <input type="text" name="extension" class="form-control rounded-3" placeholder=".com" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-bold text-muted">REGISTRATION PRICE (₦)</label>
                                <input type="number" step="0.01" name="price" class="form-control rounded-3" placeholder="5000" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" name="add_extension" class="btn btn-primary w-100 rounded-3 fw-bold">ADD</button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead class="table-light">
                                    <tr class="small text-uppercase">
                                        <th>Extension</th>
                                        <th>Price</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $ext_res = mysqli_query($connection_server, "SELECT * FROM sas_domain_extensions ORDER BY extension ASC");
                                    if(mysqli_num_rows($ext_res) > 0):
                                        while($ext = mysqli_fetch_assoc($ext_res)):
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($ext['extension']); ?></td>
                                        <td class="text-primary fw-bold">₦<?php echo number_format($ext['price'], 2); ?></td>
                                        <td class="text-end">
                                            <a href="?delete_ext=<?php echo $ext['id']; ?>" class="text-danger" onclick="return confirm('Delete this extension?')"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                        <tr><td colspan="3" class="text-center py-3 text-muted small">No extensions configured yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                    <div class="card-header bg-white py-4 border-0">
                        <h5 class="fw-bold mb-0 text-primary">Domain Setup Instructions</h5>
                        <p class="text-muted small mb-0">Set up instructions for vendors to point their domains to your server</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if(isset($_SESSION['page_alert'])): ?>
                            <div class="alert alert-info alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
                                <i class="bi bi-info-circle me-2"></i><?php echo $_SESSION['page_alert']; unset($_SESSION['page_alert']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-4">
                                <label for="nameservers" class="form-label small fw-bold text-muted text-uppercase">Nameservers</label>
                                <textarea class="form-control rounded-3" id="nameservers" name="nameservers" rows="4" placeholder="e.g.&#10;ns1.yourhost.com&#10;ns2.yourhost.com"><?php echo htmlspecialchars($nameservers); ?></textarea>
                                <div class="form-text small">Enter each nameserver on a new line. These will be sent to vendors upon approval.</div>
                            </div>
                            <div class="mb-4">
                                <label for="ip_address" class="form-label small fw-bold text-muted text-uppercase">A Record IP Address</label>
                                <input type="text" class="form-control rounded-3" id="ip_address" name="ip_address" value="<?php echo htmlspecialchars($ip_address); ?>" placeholder="e.g. 192.168.1.1">
                                <div class="form-text small">The primary server IP address for A records.</div>
                            </div>
                            <div class="mb-4">
                                <label for="registrar_url" class="form-label small fw-bold text-muted text-uppercase">Recommended Registrar URL</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-link"></i></span>
                                    <input type="url" class="form-control border-start-0 rounded-end-3" id="registrar_url" name="registrar_url" value="<?php echo htmlspecialchars($registrar_url); ?>" placeholder="https://www.namecheap.com">
                                </div>
                            </div>
                            <div class="mt-5">
                                <button type="submit" name="save_settings" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold shadow-sm">
                                    <i class="bi bi-save2 me-2"></i> Save Configuration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-4 bg-light bg-opacity-50 mt-4">
                    <div class="card-body p-4 small">
                        <h6 class="fw-bold mb-2"><i class="bi bi-info-circle-fill text-info me-1"></i> Why this matters?</h6>
                        <p class="text-muted mb-0">These settings are automatically included in the "Welcome Email" sent to vendors when their registration is approved. Providing clear instructions ensures they can go live with their custom domains quickly and correctly.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include("../func/bc-spadmin-footer.php"); ?>
</body>
</html>
