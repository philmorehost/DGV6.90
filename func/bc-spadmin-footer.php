
<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.16/dist/sweetalert2.all.min.js"></script>

<?php if(isset($_SESSION["product_purchase_response"])){ ?>


<script>
  Swal.fire ('Message!', '<?php echo $_SESSION["product_purchase_response"]; ?>', 'success') ;
            //Swal.fire({ position:'top-end',type:'',title:'Oops', text: 'kindly fill all form', showConfirmButton:1,timer:2500 });
          setTimeout(() => {
                fetch('/func/unset-product-response.php')
                    .then(response => response.text());
            }, 1000); // 3 seconds

</script>


	<!-- <div style="text-align: center; max-height: 40%;" id="customAlertDiv" class="bg-2 box-shadow m-z-index-2 s-z-index-2 m-scroll-auto s-scroll-auto m-block-dp s-block-dp m-position-fix s-position-fix m-top-20 s-top-30 br-radius-5px m-width-60 s-width-26 m-height-auto s-height-auto m-padding-lt-1 s-padding-lt-1 m-padding-rt-1 s-padding-rt-1 m-padding-tp-5 s-padding-tp-1 m-padding-bm-5 s-padding-bm-1 m-margin-lt-19 s-margin-lt-26 m-margin-bm-2 s-margin-bm-2">
	<span style="user-select: auto; word-break: break-word;" class="color-10 text-bold-500 m-font-size-15 s-font-size-18">
		<?php echo $_SESSION["product_purchase_response"]; ?>
	</span><br/>
	<button style="text-align: center; user-select: auto;" onclick="customDismissPop();" onkeypress="keyCustomDismissPop(event);" class="button-box br-radius-50 onhover-bg-color-10 a-cursor color-2 bg-10 m-font-size-10 s-font-size-10 br-style-tp-0 m-inline-dp s-inline-block-dp m-bottom-0 s-bottom-5 m-position-sti s-position-sti m-width-30 s-width-30 m-height-auto s-height-auto m-margin-tp-1 s-margin-tp-1 m-margin-bm-2 s-margin-bm-2 m-margin-lt-0 s-margin-lt-0 m-margin-rt-0 s-margin-rt-0 m-padding-tp-5 s-padding-tp-5 m-padding-bm-5 s-padding-bm-5 m-padding-lt-5 s-padding-lt-5 m-padding-rt-5 s-padding-rt-5">
		DISMISS
	</button>
</div>
<script>
	function customDismissPop(){
		var customAlertDiv = document.getElementById("customAlertDiv");
		setTimeout(function(){
			customAlertDiv.style.display = "none";
		}, 300);
	}
	
	document.addEventListener("keydown", function(event){
		if(event.keyCode === 13){
			//prevent enter key default function
			event.preventDefault();
			var customAlertDiv = document.getElementById("customAlertDiv");
			setTimeout(function(){
				customAlertDiv.style.display = "none";
			}, 300);
		}
	});
	
	clearProductResponse();
	function clearProductResponse(){
		var productHttp = new XMLHttpRequest();
        productHttp.open("GET", "../unset-product.php");
        productHttp.setRequestHeader("Content-Type", "application/json");
        // productHttp.onload = function(){
        //     alert(productHttp.status);
        // }
        productHttp.send();
	}
</script> -->
<?php } ?>

<div class="w-100 mh-25 mt-3 d-block d-xl-none d-md-none d-lg-none" style="height: 80px;"></div>
</main>

<div class="bg-white w-100 mh-25 py-2 position-fixed border bottom-0 d-flex flex-row justify-items-center justify-content-between d-block d-xl-none d-md-none d-lg-none" style="height: 80px; z-index: 1000;">
  
  <div class="col-2 d-flex flex-column align-items-center">
      <button class="btn btn-secondary rounded-circle mb-1" page-name="Vendors.php">
        <a href="<?php echo $web_http_host; ?>/bc-spadmin/Vendors.php" class="text-decoration-none">
          <i class="bi bi-people text-white"></i>
        </a>
      </button>
      <span class="text-dark" style="font-size: 10px; font-weight: 600;">Vendors</span>
  </div>

<div class="col-2 d-flex flex-column align-items-center">
      <button class="btn btn-secondary rounded-circle mb-1" page-name="AllSubscriptions.php">
        <a href="<?php echo $web_http_host; ?>/bc-spadmin/AllSubscriptions.php" class="text-decoration-none">
          <i class="bi bi-card-list text-white"></i>
        </a>
      </button>
      <span class="text-dark" style="font-size: 10px; font-weight: 600;">Subs</span>
  </div>

<div class="col-2 d-flex flex-column align-items-center">
      <button class="btn btn-primary rounded-circle mb-1" page-name="Dashboard.php" style="width: 50px; height: 50px; margin-top: -15px; border: 4px solid white; box-shadow: 0 -5px 10px rgba(0,0,0,0.1);">
        <a href="<?php echo $web_http_host; ?>/bc-spadmin/Dashboard.php" class="text-decoration-none">
          <i class="bi bi-grid text-white"></i>
        </a>
      </button>
      <span class="text-dark" style="font-size: 10px; font-weight: 600;">Home</span>
  </div>

<div class="col-2 d-flex flex-column align-items-center">
      <button class="btn btn-secondary rounded-circle mb-1" page-name="CreateAPI.php">
        <a href="<?php echo $web_http_host; ?>/bc-spadmin/CreateAPI.php" class="text-decoration-none">
          <i class="bi bi-plus-circle text-white"></i>
        </a>
      </button>
      <span class="text-dark" style="font-size: 10px; font-weight: 600;">Add API</span>
  </div>

  <div class="col-2 d-flex flex-column align-items-center">
      <button class="btn btn-secondary rounded-circle mb-1" page-name="VendorRegistrations.php">
        <a href="<?php echo $web_http_host; ?>/bc-spadmin/VendorRegistrations.php" class="text-decoration-none">
          <i class="bi bi-person-plus text-white"></i>
        </a>
      </button>
      <span class="text-dark" style="font-size: 10px; font-weight: 600;">Pending</span>
  </div>

</div>

<script src="/jsfile/bc-custom-all.js"></script>
<script src="/jsfile/bc-custom-vendor.js"></script>
<script src="/jsfile/bc-custom-super.js"></script>

  <!-- Vendor JS Files -->
  <script src="../assets-2/vendor/apexcharts/apexcharts.min.js"></script>
  <script src="../assets-2/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="../assets-2/vendor/chart.js/chart.umd.js"></script>
  <script src="../assets-2/vendor/echarts/echarts.min.js"></script>
  <script src="../assets-2/vendor/quill/quill.js"></script>
  <script src="../assets-2/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="../assets-2/vendor/tinymce/tinymce.min.js"></script>
  <script src="../assets-2/vendor/php-email-form/validate.js"></script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <!-- Template Main JS File -->
  <script src="../assets-2/js/main.js"></script>
  <script src="../jsfile/bc-guide.js"></script>

<!-- Transaction Details Modal -->
<div class="modal fade" id="trxDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="fw-bold">Transaction Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4" id="trx-receipt-content">
        <div class="text-center mb-4">
            <img src="<?php echo $web_http_host; ?>/uploaded-image/<?php echo str_replace(['.', ':'], '-', $_SERVER['HTTP_HOST']) . '_'; ?>logo.png" style="max-height: 50px;" class="mb-2" onerror="this.src='/assets-2/img/logo.png'">
            <h6 class="text-muted small"><?php echo $_SERVER['HTTP_HOST']; ?></h6>
        </div>
        <div id="trx-details-list">
            <!-- Dynamic Content -->
        </div>
      </div>
      <div class="modal-footer border-0 flex-column gap-2 pt-0">
        <div id="trx-actions" class="w-100 mb-2"></div>
        <div class="d-flex w-100 gap-2">
            <button type="button" class="btn btn-outline-primary flex-grow-1 rounded-3" onclick="shareTrx('image')"><i class="bi bi-image me-2"></i>Image</button>
            <button type="button" class="btn btn-outline-danger flex-grow-1 rounded-3" onclick="shareTrx('pdf')"><i class="bi bi-file-pdf me-2"></i>PDF</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
    function showTransactionDetails(ref, adminView = false) {
        const modal = new bootstrap.Modal(document.getElementById('trxDetailModal'));
        const container = document.getElementById('trx-details-list');
        const actions = document.getElementById('trx-actions');

        container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
        actions.innerHTML = '';
        modal.show();

        fetch(`../web/finance-ajax.php?action=get_transaction_details&reference=${encodeURIComponent(ref)}${adminView ? '&admin=1' : ''}`)
            .then(r => r.json())
            .then(res => {
                if(res.status == 'success') {
                    let html = '';
                    for(const [label, value] of Object.entries(res.data)) {
                        if(!value || value == '0.00' || value == '₦0.00') continue;
                        html += `
                            <div class="d-flex justify-content-between mb-2 pb-2 border-bottom border-light">
                                <span class="text-muted small">${label}</span>
                                <span class="fw-bold small text-end">${value}</span>
                            </div>
                        `;
                    }
                    container.innerHTML = html || '<div class="text-center text-muted py-3">No details available.</div>';
                    actions.innerHTML = res.actions || '';
                } else {
                    container.innerHTML = `<div class="alert alert-danger">${res.message || 'Could not load transaction details.'}</div>`;
                }
            })
            .catch(err => {
                container.innerHTML = `<div class="alert alert-warning">Unable to load details. Please try again.<br><small class="text-muted">${err.message || ''}</small></div>`;
            });
    }

    async function shareTrx(type) {
        const content = document.getElementById('trx-receipt-content');
        if (type === 'image') {
            const canvas = await html2canvas(content);
            const link = document.createElement('a');
            link.download = 'Receipt-' + Date.now() + '.png';
            link.href = canvas.toDataURL();
            link.click();
        } else if (type === 'pdf') {
            const { jsPDF } = window.jspdf;
            const canvas = await html2canvas(content);
            const imgData = canvas.toDataURL('image/png');
            const pdf = new jsPDF('p', 'mm', 'a4');
            const imgProps = pdf.getImageProperties(imgData);
            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
            pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
            pdf.save('Receipt-' + Date.now() + '.pdf');
        }
    }
</script>

<?php mysqli_close($connection_server); ?>