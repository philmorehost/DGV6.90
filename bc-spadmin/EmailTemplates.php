<?php session_start();
    include("../func/bc-spadmin-config.php");
    
    if(isset($_POST["update-template"])){
        $subject = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["subject"])));
        $body = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["body"])));
        $email_type = mysqli_real_escape_string($connection_server, trim(strip_tags(strtolower($_POST["type"]))));
        
        if(!empty($subject) && !empty($body) && !empty($email_type)){
            $template_details = mysqli_query($connection_server, "SELECT * FROM sas_super_admin_email_templates WHERE email_type='$email_type'");
            if(mysqli_num_rows($template_details) == 1){
                mysqli_query($connection_server, "UPDATE sas_super_admin_email_templates SET subject='$subject', body='$body' WHERE email_type='$email_type'");
                $_SESSION["product_purchase_response"] = "Email Template Updated Successfully";
            }else{
                if(mysqli_num_rows($template_details) > 1){
                    $_SESSION["product_purchase_response"] = "Duplicated Details";
                }else{
                    mysqli_query($connection_server, "INSERT INTO sas_super_admin_email_templates (email_type, subject, body) VALUES ('$email_type', '$subject', '$body')");
                    $_SESSION["product_purchase_response"] = "Email Template Created Successfully";
                }
            }
        }else{
            if(empty($subject)){
                $_SESSION["product_purchase_response"] = "Subject Field Empty";
            }else if(empty($body)){
                $_SESSION["product_purchase_response"] = "Body Field Empty";
            }else if(empty($email_type)){
                $_SESSION["product_purchase_response"] = "Email Type Field Empty";
            }
        }
        header("Location: EmailTemplates.php");
        exit();
    }

?>
<!DOCTYPE html>
<head>
    <title>Email Template</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    
          <!-- Vendor CSS Files -->
  <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets-2/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="../assets-2/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="../assets-2/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="../assets-2/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="../assets-2/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="../assets-2/css/style.css" rel="stylesheet">

</head>
<body>
    <?php include("../func/bc-spadmin-header.php"); ?>    
    <div class="pagetitle">
      <h1>EMAIL TEMPLATE</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Email Template</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <div class="row g-4">
        <div class="col-lg-12">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white py-4 border-0 d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3"><i class="bi bi-envelope-paper text-dark-primary fs-4"></i></div>
                    <h5 class="fw-bold mb-0 text-primary">System Email Templates</h5>
                </div>
                <div class="card-body p-0">
                    <div class="nav nav-tabs nav-tabs-bordered d-flex overflow-auto flex-nowrap" id="emailTabs" role="tablist">
                        <?php
                        $templates = [
                            'vendor-reg' => 'Registration',
                            'vendor-log' => 'Login Alert',
                            'vendor-pass-update' => 'Password Reset',
                            'vendor-account-update' => 'Profile Update',
                            'vendor-account-recovery' => 'Recovery Code',
                            'vendor-account-status' => 'Account Status',
                            'vendor-transactions' => 'Admin Trx',
                            'vendor-funding' => 'Credit/Debit',
                            'vendor-refund' => 'Refund',
                            'vendor-welcome-activated' => 'Welcome/Activation'
                        ];
                        $first_t = true;
                        foreach($templates as $key => $label): ?>
                            <button class="nav-link flex-fill <?php echo $first_t ? 'active' : ''; ?> small fw-bold" data-bs-toggle="tab" data-bs-target="#tab-<?php echo $key; ?>"><?php echo $label; ?></button>
                        <?php $first_t = false; endforeach; ?>
                    </div>

                    <div class="tab-content p-4">
                        <?php
                        $first_t = true;
                        foreach($templates as $key => $label):
                            $placeholders = [];
                            if($key == 'vendor-reg') $placeholders = ['{firstname}', '{lastname}', '{email}', '{phone}', '{address}', '{website}'];
                            if($key == 'vendor-log') $placeholders = ['{firstname}', '{lastname}', '{email}', '{ip_address}'];
                            if($key == 'vendor-pass-update') $placeholders = ['{firstname}', '{lastname}'];
                            if($key == 'vendor-account-update') $placeholders = ['{firstname}', '{lastname}', '{email}', '{phone}', '{address}', '{website}'];
                            if($key == 'vendor-account-recovery') $placeholders = ['{firstname}', '{lastname}', '{recovery_code}'];
                            if($key == 'vendor-account-status') $placeholders = ['{firstname}', '{lastname}', '{account_status}'];
                            if($key == 'vendor-transactions') $placeholders = ['{admin_firstname}', '{admin_lastname}', '{email}', '{firstname}', '{balance_before}', '{balance_after}', '{amount}', '{description}', '{type}'];
                            if($key == 'vendor-funding') $placeholders = ['{firstname}', '{lastname}', '{balance_before}', '{balance_after}', '{amount}', '{description}', '{type}'];
                            if($key == 'vendor-refund') $placeholders = ['{firstname}', '{lastname}', '{amount}', '{description}'];
                            if($key == 'vendor-welcome-activated') $placeholders = ['{firstname}', '{lastname}', '{expiry_date}', '{domain_nameservers}', '{domain_ip_address}', '{domain_registrar_url}'];
                        ?>
                        <div class="tab-pane fade <?php echo $first_t ? 'show active' : ''; ?>" id="tab-<?php echo $key; ?>">
                            <div class="bg-light p-3 rounded-4 mb-4">
                                <label class="form-label small fw-bold text-muted mb-2">AVAILABLE PLACEHOLDERS</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach($placeholders as $p): ?>
                                        <code class="bg-white px-2 py-1 border rounded small text-primary"><?php echo $p; ?></code>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <form method="post">
                                <input type="hidden" name="type" value="<?php echo $key; ?>">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">EMAIL SUBJECT</label>
                                    <input name="subject" type="text" value="<?php echo getSuperAdminEmailTemplate($key, 'subject'); ?>" class="form-control rounded-3" required />
                                </div>
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-muted">EMAIL BODY (HTML ALLOWED)</label>
                                    <textarea name="body" class="form-control rounded-4" rows="12" required><?php echo getSuperAdminEmailTemplate($key, 'body'); ?></textarea>
                                </div>
                                <button name="update-template" type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                                    <i class="bi bi-save me-2"></i> Update <?php echo $label; ?> Template
                                </button>
                            </form>
                        </div>
                        <?php $first_t = false; endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </section>
        
    <?php include("../func/bc-spadmin-footer.php"); ?>
    
</body>
</html>
