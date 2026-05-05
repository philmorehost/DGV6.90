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

        .nin-card {
            width: 85.6mm;
            height: 53.98mm;
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            margin: 20px auto;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            background-size: cover;
            background-position: center;
            color: #000;
        }
        .nin-card.front { background-image: url('../asset/NIN-front.png'); background-color: #fff; }
        .nin-card.back { background-image: url('../asset/NIN-back.png'); background-color: #e8f5e9; }

        .card-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            padding: 8px 12px;
        }

        .header-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .coa-logo { width: 35px; height: auto; }
        .nimc-logo { width: 35px; height: auto; }
        .header-titles { text-align: right; line-height: 1.1; }
        .header-titles h1 { font-size: 8px; font-weight: 900; margin: 0; color: #1e4d2b; text-transform: uppercase; }
        .header-titles p { font-size: 6px; margin: 0; color: #333; font-weight: 600; }
        .header-titles .slip-type { font-size: 7px; color: #2e7d32; font-weight: 800; margin-top: 1px; }

        .body-section { display: flex; margin-top: 5px; }
        .details-col { flex: 1; padding-right: 10px; }
        .photo-col { width: 65px; text-align: right; }

        .full-name { font-size: 10.5px; font-weight: 800; color: #000; margin-bottom: 6px; text-transform: uppercase; }
        .info-row { font-size: 7.5px; margin-bottom: 2px; color: #444; font-weight: 600; }
        .info-row span { color: #000; font-weight: 800; margin-left: 3px; }

        .id-photo {
            width: 60px; height: 75px;
            border-radius: 4px;
            border: 1.5px solid #ccc;
            object-fit: cover;
            background: #f8f9fa;
        }
        .photo-placeholder {
            width: 60px; height: 75px;
            border-radius: 4px;
            border: 1.5px solid #ccc;
            background: #eee;
            display: flex; align-items: center; justify-content: center;
            font-size: 30px; color: #ccc;
        }

        .nin-section {
            position: absolute;
            bottom: 12px;
            left: 12px;
            font-family: 'Courier New', Courier, monospace;
        }
        .nin-label { font-size: 9px; font-weight: 800; color: #1e4d2b; margin-bottom: -2px; }
        .nin-number { font-size: 16px; font-weight: 900; color: #1e4d2b; letter-spacing: 2px; }

        /* Back styling */
        .disclaimer-box {
            text-align: center;
            padding: 15px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .disclaimer-box h2 { font-size: 16px; font-weight: 900; margin-bottom: 2px; letter-spacing: 2px; }
        .disclaimer-box i { font-size: 10px; margin-bottom: 10px; display: block; }
        .disclaimer-box p { font-size: 7px; line-height: 1.4; margin-bottom: 8px; font-weight: 600; }
        .disclaimer-box .caution { font-size: 11px; font-weight: 900; margin: 5px 0; color: #000; }

        /* Print area */
        .print-wrapper {
            max-width: 800px;
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
            .print-wrapper { box-shadow: none; margin: 0; padding: 0; width: 100%; max-width: none; }
            .nin-card { box-shadow: none; margin: 10mm auto; break-inside: avoid; }
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

        <!-- FRONT OF CARD -->
        <div class="nin-card front">
            <div class="card-overlay">
                <div class="header-section">
                    <img src="../asset/nigeria-coa.svg" class="coa-logo">
                    <img src="../asset/nimc-logo.png" class="nimc-logo">
                    <div class="header-titles">
                        <h1>Federal Republic of Nigeria</h1>
                        <p>National Identity Management Commission (NIMC)</p>
                        <div class="slip-type">Digital Identity Slip</div>
                    </div>
                </div>

                <div class="body-section">
                    <div class="details-col">
                        <div class="full-name"><?php echo htmlspecialchars($fullname ?: '—'); ?></div>

                        <?php if (!empty($dob_formatted)): ?>
                        <div class="info-row">DOB: <span><?php echo htmlspecialchars($dob_formatted); ?></span></div>
                        <?php endif; ?>

                        <?php if (!empty($record['gender'])): ?>
                        <div class="info-row">Gender: <span><?php echo htmlspecialchars(strtoupper($record['gender'])); ?></span></div>
                        <?php endif; ?>

                        <div class="info-row">Nationality: <span>NGA</span></div>

                        <?php if (!empty($record['phone'])): ?>
                        <div class="info-row">Phone: <span><?php echo htmlspecialchars($record['phone']); ?></span></div>
                        <?php endif; ?>

                        <?php
                        $address = $record['address'] ?? '';
                        if (!empty($address) && stripos($address, 'not provided') === false):
                        ?>
                        <div class="info-row">Address: <span style="font-size: 6.5px;"><?php echo htmlspecialchars(mb_substr($address, 0, 50)); ?></span></div>
                        <?php endif; ?>
                    </div>
                    <div class="photo-col">
                        <?php if (!empty($record['photo_data'])): ?>
                            <img class="id-photo"
                                 src="data:image/jpeg;base64,<?php echo htmlspecialchars($record['photo_data']); ?>"
                                 alt="Photo">
                        <?php else: ?>
                            <div class="photo-placeholder">&#128100;</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="nin-section">
                    <div class="nin-label">NIN:</div>
                    <div class="nin-number"><?php echo chunk_split($record['nin_input'], 4, ' '); ?></div>
                </div>
            </div>
        </div>

        <!-- BACK OF CARD -->
        <div class="nin-card back">
            <div class="disclaimer-box">
                <h2>DISCLAIMER</h2>
                <i>Trust, but verify</i>
                <p>Kindly ensure each time this ID is presented, that you verify the credentials using a Government-APPROVED verification resource. The details on the front of this NIN Slip must EXACTLY match the verification result.</p>
                <div class="caution">CAUTION!</div>
                <p>If this NIN was not issued to the person on the front of this document, please DO NOT attempt to scan, photocopy or replicate the personal data contained herein.</p>
                <p>You are only permitted to scan the barcode for the purpose of identity verification.</p>
                <p style="font-size: 6px; margin-top: 5px;">The FEDERAL GOVERNMENT of NIGERIA assumes no responsibility if you accept any variance in the scan result or do not scan the 2D barcode overleaf</p>
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
