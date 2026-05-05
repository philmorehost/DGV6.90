<?php session_start();
include("../func/bc-config.php");

$ref = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["ref"] ?? "")));

if (empty($ref)) {
    header("Location: NINCardHistory.php");
    exit();
}

$record = mysqli_fetch_assoc(mysqli_query($connection_server, "SELECT * FROM sas_nin_card_requests WHERE reference='$ref' AND vendor_id='".$get_logged_user_details["vendor_id"]."' AND user_id='".$get_logged_user_details["id"]."' LIMIT 1"));

if (!$record) {
    $_SESSION["product_purchase_response"] = "Record not found.";
    header("Location: NINCardHistory.php");
    exit();
}

$fullname = trim($record['firstname'] . ' ' . $record['middlename'] . ' ' . $record['lastname']);
$dob_formatted = '';
if (!empty($record['birthdate'])) {
    $ts = strtotime($record['birthdate']);
    $dob_formatted = $ts ? date('d-M-Y', $ts) : $record['birthdate'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>NIN Slip — <?php echo htmlspecialchars($fullname ?: $record['nin_input']); ?></title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f4f8; font-family: Arial, Helvetica, sans-serif; }

        .nin-slip {
            width: 85.6mm;
            min-height: 53.98mm;
            background: linear-gradient(135deg, #1a5276 0%, #117a65 100%);
            color: #fff;
            border-radius: 8px;
            padding: 10px 12px 10px 12px;
            position: relative;
            box-shadow: 0 8px 24px rgba(0,0,0,0.25);
            overflow: hidden;
            margin: 0 auto;
        }
        .nin-slip::before {
            content: '';
            position: absolute;
            top: -20px; right: -20px;
            width: 100px; height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.07);
        }
        .nin-slip::after {
            content: '';
            position: absolute;
            bottom: -30px; left: -10px;
            width: 120px; height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
        }
        .slip-header { display: flex; align-items: center; gap: 6px; margin-bottom: 8px; }
        .slip-header .badge-logo { width: 28px; height: 28px; }
        .slip-header h6 { font-size: 9px; font-weight: 700; margin: 0; letter-spacing: 0.5px; text-transform: uppercase; color: #a8e6cf; }
        .slip-header span { font-size: 7px; color: rgba(255,255,255,0.7); display: block; }
        .slip-body { display: flex; gap: 10px; align-items: flex-start; }
        .slip-photo {
            width: 44px; height: 52px;
            border-radius: 4px;
            border: 2px solid rgba(255,255,255,0.5);
            object-fit: cover;
            flex-shrink: 0;
            background: rgba(255,255,255,0.1);
        }
        .slip-photo-placeholder {
            width: 44px; height: 52px;
            border-radius: 4px;
            border: 2px solid rgba(255,255,255,0.4);
            flex-shrink: 0;
            background: rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: rgba(255,255,255,0.5);
        }
        .slip-info { flex: 1; min-width: 0; }
        .slip-name { font-size: 10px; font-weight: 700; margin-bottom: 4px; letter-spacing: 0.3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .slip-row { font-size: 7.5px; margin-bottom: 2px; color: rgba(255,255,255,0.85); }
        .slip-row span { font-weight: 600; color: #fff; }
        .slip-nin { font-size: 11px; font-weight: 700; letter-spacing: 2px; margin-top: 5px; color: #a8e6cf; }
        .slip-footer { font-size: 6.5px; color: rgba(255,255,255,0.55); margin-top: 6px; text-align: center; }

        /* Print area */
        .print-wrapper {
            max-width: 700px;
            margin: 30px auto;
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .action-bar { text-align: center; margin-bottom: 24px; }

        @media print {
            body { background: none; }
            .no-print { display: none !important; }
            .print-wrapper { box-shadow: none; margin: 0; padding: 10mm; }
            .nin-slip { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="print-wrapper">
        <div class="action-bar no-print">
            <button onclick="window.print()" class="btn btn-success me-2 rounded-pill px-4">
                <i class="bi bi-printer me-1"></i> Print Slip
            </button>
            <a href="NINCard.php" class="btn btn-outline-secondary rounded-pill px-4">
                New Request
            </a>
            <a href="NINCardHistory.php" class="btn btn-outline-primary rounded-pill px-4 ms-2">
                History
            </a>
        </div>

        <h6 class="text-center text-muted small mb-4 no-print">
            Reference: <strong><?php echo htmlspecialchars($record['reference']); ?></strong>
            &nbsp;|&nbsp; Generated: <?php echo date('d M Y, H:i', strtotime($record['date_created'])); ?>
            &nbsp;|&nbsp; Fee paid: ₦<?php echo number_format($record['price'], 2); ?>
        </h6>

        <!-- The NIN Slip Card -->
        <div class="nin-slip">
            <div class="slip-header">
                <svg class="badge-logo" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="28" height="28" rx="6" fill="rgba(255,255,255,0.15)"/>
                    <path d="M9 8h10v2H9V8zm0 4h10v2H9v-2zm0 4h6v2H9v-2z" fill="white"/>
                    <circle cx="20" cy="18" r="3" fill="#a8e6cf"/>
                </svg>
                <div>
                    <h6>Federal Republic of Nigeria</h6>
                    <span>National Identity Management Commission (NIMC)</span>
                    <span>Digital Identity Slip</span>
                </div>
            </div>

            <div class="slip-body">
                <?php if (!empty($record['photo_data'])): ?>
                    <img class="slip-photo"
                         src="data:image/jpeg;base64,<?php echo htmlspecialchars($record['photo_data']); ?>"
                         alt="Photo">
                <?php else: ?>
                    <div class="slip-photo-placeholder">&#128100;</div>
                <?php endif; ?>

                <div class="slip-info">
                    <div class="slip-name"><?php echo htmlspecialchars($fullname ?: '—'); ?></div>

                    <?php if (!empty($dob_formatted)): ?>
                    <div class="slip-row">DOB: <span><?php echo htmlspecialchars($dob_formatted); ?></span></div>
                    <?php endif; ?>

                    <?php if (!empty($record['gender'])): ?>
                    <div class="slip-row">Gender: <span><?php echo htmlspecialchars($record['gender']); ?></span></div>
                    <?php endif; ?>

                    <?php if (!empty($record['phone'])): ?>
                    <div class="slip-row">Phone: <span><?php echo htmlspecialchars($record['phone']); ?></span></div>
                    <?php endif; ?>

                    <?php if (!empty($record['state_of_origin'])): ?>
                    <div class="slip-row">State of Origin: <span><?php echo htmlspecialchars($record['state_of_origin']); ?></span></div>
                    <?php endif; ?>

                    <?php if (!empty($record['address'])): ?>
                    <div class="slip-row" style="font-size:7px;">Address: <span><?php echo htmlspecialchars(mb_substr($record['address'], 0, 55)); ?></span></div>
                    <?php endif; ?>

                    <div class="slip-nin"><?php echo chunk_split($record['nin_input'], 4, ' '); ?></div>
                </div>
            </div>

            <div class="slip-footer">
                This is a digital NIN slip generated via a licensed data aggregator. Not an official government card.
                &nbsp;|&nbsp; <?php echo date('d/m/Y'); ?>
            </div>
        </div>

        <p class="text-center text-muted small mt-4 no-print">
            <i class="bi bi-info-circle"></i>
            This digital NIN slip is for personal identification reference only.
            Always carry an official government-issued ID for formal purposes.
        </p>
    </div>
</body>
</html>
