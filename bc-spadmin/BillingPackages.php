<?php session_start();
    include("../func/bc-spadmin-config.php");

    // Auto-migration: Ensure package_type column exists
    $check_pkg_cols = mysqli_query($connection_server, "SHOW COLUMNS FROM sas_billing_packages LIKE 'package_type'");
    if(mysqli_num_rows($check_pkg_cols) == 0) {
        mysqli_query($connection_server, "ALTER TABLE sas_billing_packages ADD COLUMN package_type VARCHAR(20) DEFAULT 'subscription' AFTER name");
    }

    // Handle Delete Request
    if(isset($_GET['delete_id'])) {
        $delete_id = mysqli_real_escape_string($connection_server, $_GET['delete_id']);
        mysqli_query($connection_server, "DELETE FROM sas_billing_packages WHERE id='$delete_id'");
        $_SESSION['page_alert'] = "Package deleted successfully!";
        header("Location: BillingPackages.php");
        exit();
    }

    // Handle Add/Edit Request
    if(isset($_POST['save_package'])) {
        $package_id = mysqli_real_escape_string($connection_server, $_POST['package_id']);
        $name = mysqli_real_escape_string($connection_server, $_POST['name']);
        $price = mysqli_real_escape_string($connection_server, $_POST['price']);
        $duration_days = mysqli_real_escape_string($connection_server, $_POST['duration_days']);
        $package_type = mysqli_real_escape_string($connection_server, $_POST['package_type']);

        if(empty($package_id)) {
            // Add New Package
            $sql = "INSERT INTO sas_billing_packages (name, package_type, price, duration_days) VALUES ('$name', '$package_type', '$price', '$duration_days')";
            $_SESSION['page_alert'] = "Package added successfully!";
        } else {
            // Update Existing Package
            $sql = "UPDATE sas_billing_packages SET name='$name', package_type='$package_type', price='$price', duration_days='$duration_days' WHERE id='$package_id'";
            $_SESSION['page_alert'] = "Package updated successfully!";
        }

        if(!mysqli_query($connection_server, $sql)) {
            // If query fails, show the error instead of the generic success message
            $_SESSION['page_alert'] = "Error saving package: " . mysqli_error($connection_server);
        }

        header("Location: BillingPackages.php");
        exit();
    }

    // Handle App Service Prices
    if(isset($_POST["update-app-services"])){
        $apk_price = mysqli_real_escape_string($connection_server, $_POST['apk_price']);
        $ios_price = mysqli_real_escape_string($connection_server, $_POST['ios_price']);
        $playstore_price = mysqli_real_escape_string($connection_server, $_POST['playstore_price']);
        $sms_bridge_price = mysqli_real_escape_string($connection_server, $_POST['sms_bridge_price']);

        mysqli_query($connection_server, "INSERT INTO sas_super_admin_options (option_name, option_value) VALUES ('apk_development_price', '$apk_price') ON DUPLICATE KEY UPDATE option_value='$apk_price'");
        mysqli_query($connection_server, "INSERT INTO sas_super_admin_options (option_name, option_value) VALUES ('ios_development_price', '$ios_price') ON DUPLICATE KEY UPDATE option_value='$ios_price'");
        mysqli_query($connection_server, "INSERT INTO sas_super_admin_options (option_name, option_value) VALUES ('playstore_listing_price', '$playstore_price') ON DUPLICATE KEY UPDATE option_value='$playstore_price'");
        mysqli_query($connection_server, "INSERT INTO sas_super_admin_options (option_name, option_value) VALUES ('sms_bridge_price', '$sms_bridge_price') ON DUPLICATE KEY UPDATE option_value='$sms_bridge_price'");

        $_SESSION["page_alert"] = "App service prices updated successfully";
        header("Location: BillingPackages.php");
        exit();
    }

    // Fetch package for editing
    $edit_package = null;
    if(isset($_GET['edit_id'])) {
        $edit_id = mysqli_real_escape_string($connection_server, $_GET['edit_id']);
        $result = mysqli_query($connection_server, "SELECT * FROM sas_billing_packages WHERE id='$edit_id'");
        $edit_package = mysqli_fetch_assoc($result);
    }
?>
<!DOCTYPE html>
<head>
    <title>Billing Packages Management</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include("../func/bc-spadmin-header.php"); ?>

    <div class="pagetitle">
        <h1>Billing Packages</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="Dashboard.php">Home</a></li>
                <li class="breadcrumb-item active">Billing Packages</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0 text-primary"><?php echo isset($edit_package) ? 'Edit' : 'Add New'; ?> Package</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="BillingPackages.php">
                            <input type="hidden" name="package_id" value="<?php echo $edit_package['id'] ?? ''; ?>">
                            <div class="mb-3">
                                <label for="name" class="form-label small fw-bold text-muted text-uppercase">Package Name</label>
                                <input type="text" class="form-control rounded-3" id="name" name="name" value="<?php echo $edit_package['name'] ?? ''; ?>" placeholder="e.g. Monthly Basic" required>
                            </div>
                            <div class="mb-3">
                                <label for="package_type" class="form-label small fw-bold text-muted text-uppercase">Package Type</label>
                                <select class="form-select rounded-3" id="package_type" name="package_type" required onchange="toggleDuration(this.value)">
                                    <option value="subscription" <?php echo (isset($edit_package) && $edit_package['package_type'] == 'subscription') ? 'selected' : ''; ?>>Recurring Subscription</option>
                                    <option value="one-off" <?php echo (isset($edit_package) && $edit_package['package_type'] == 'one-off') ? 'selected' : ''; ?>>ONE-OFF PAYMENT</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="price" class="form-label small fw-bold text-muted text-uppercase">Price (₦)</label>
                                <input type="number" step="0.01" class="form-control rounded-3" id="price" name="price" value="<?php echo $edit_package['price'] ?? ''; ?>" placeholder="0.00" required>
                            </div>
                            <div class="mb-4" id="duration_wrapper">
                                <label for="duration_days" class="form-label small fw-bold text-muted text-uppercase">Duration (Days)</label>
                                <input type="number" class="form-control rounded-3" id="duration_days" name="duration_days" value="<?php echo $edit_package['duration_days'] ?? ''; ?>" placeholder="30" required>
                            </div>

                            <script>
                            function toggleDuration(type) {
                                const wrapper = document.getElementById('duration_wrapper');
                                const input = document.getElementById('duration_days');
                                if(type === 'one-off') {
                                    wrapper.style.opacity = '0.5';
                                    input.value = '9999';
                                    input.readOnly = true;
                                } else {
                                    wrapper.style.opacity = '1';
                                    input.readOnly = false;
                                    if(input.value == '9999') input.value = '30';
                                }
                            }
                            // Initial state
                            window.onload = function() {
                                toggleDuration(document.getElementById('package_type').value);
                            }
                            </script>
                            <button type="submit" name="save_package" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm">
                                <i class="bi bi-save me-1"></i> Save Package
                            </button>
                            <?php if(isset($edit_package)): ?>
                                <a href="BillingPackages.php" class="btn btn-light w-100 rounded-pill mt-2 border">Cancel</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-4 mt-4">
                    <div class="card-header bg-dark py-3 border-0 text-white">
                        <h6 class="mb-0"><i class="bi bi-phone-fill me-2"></i>App Development Fees (One-Off)</h6>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="BillingPackages.php">
                            <?php
                            $apk_price = getSuperAdminOption('apk_development_price', '0');
                            $ios_price = getSuperAdminOption('ios_development_price', '0');
                            $play_price = getSuperAdminOption('playstore_listing_price', '0');
                            $sms_price = getSuperAdminOption('sms_bridge_price', '0');
                            ?>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Android APK Price (₦)</label>
                                <input type="number" name="apk_price" class="form-control rounded-3" value="<?php echo $apk_price; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">iOS App Price (₦)</label>
                                <input type="number" name="ios_price" class="form-control rounded-3" value="<?php echo $ios_price; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Play Store Listing (₦)</label>
                                <input type="number" name="playstore_price" class="form-control rounded-3" value="<?php echo $play_price; ?>" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">SMS Bridge APK (₦)</label>
                                <input type="number" name="sms_bridge_price" class="form-control rounded-3" value="<?php echo $sms_price; ?>" required>
                            </div>
                            <button type="submit" name="update-app-services" class="btn btn-dark w-100 rounded-pill fw-bold shadow-sm">
                                Update App Prices
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                    <div class="card-header bg-white py-4 border-0">
                        <h5 class="fw-bold mb-0 text-primary">Active Billing Packages</h5>
                        <p class="text-muted small mb-0">Configure subscription plans for your vendors</p>
                    </div>
                    <div class="card-body p-0">
                        <?php if(isset($_SESSION['page_alert'])): ?>
                            <div class="px-4 pt-3">
                                <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm" role="alert">
                                    <i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['page_alert']; unset($_SESSION['page_alert']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr class="small text-uppercase text-muted">
                                        <th class="ps-4">#</th>
                                        <th>Package Name</th>
                                        <th>Type</th>
                                        <th>Price</th>
                                        <th>Duration</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                        $result = mysqli_query($connection_server, "SELECT * FROM sas_billing_packages ORDER BY id DESC");
                                        $count = 1;
                                        while($row = mysqli_fetch_assoc($result)):
                                    ?>
                                    <tr>
                                        <td class="ps-4 text-muted"><?php echo $count++; ?></td>
                                        <td><div class="fw-bold text-dark"><?php echo htmlspecialchars($row['name']); ?></div></td>
                                        <td>
                                            <?php if(($row['package_type'] ?? '') == 'one-off'): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">One-Off</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3">Subscription</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="fw-bold text-primary">₦<?php echo number_format($row['price'], 2); ?></span></td>
                                        <td>
                                            <?php if(($row['package_type'] ?? '') == 'one-off'): ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3">Lifetime</span>
                                            <?php else: ?>
                                                <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3"><?php echo htmlspecialchars($row['duration_days']); ?> Days</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group btn-group-sm">
                                                <a href="BillingPackages.php?edit_id=<?php echo $row['id']; ?>" class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                                <a href="BillingPackages.php?delete_id=<?php echo $row['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete this package?');" title="Delete"><i class="bi bi-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include("../func/bc-spadmin-footer.php"); ?>
</body>
</html>