<?php
include "includes/db.php";
$id = $_POST["main_id"];

$sub_total = $_POST['sub_total'];
$discount = $_POST['discount'];
$grand_total = $_POST['grand_total'];

$service_query = mysqli_query($con, "SELECT * FROM memberships where membership_id='$id'");
$c = 1;
while ($fetch_services = mysqli_fetch_array($service_query)) {
    ?>

    <div class="row mb-2 invoice-item-row"
        style="background: #e9e9e9;padding: 10px 0px;border-radius: 4px;border-bottom: 2px dashed #b9b9b9;position:relative;">
        <div class="col-md-4">
            <label class="form-label">Service</label>
            <input class="form-control" required="required" name="service[]" type="text" placeholder="Unit Price"
                value="<?php echo $fetch_services['membership_name']; ?>">
        </div>
        <div class="col-md-5">
            <label class="form-label">Description</label>
            <textarea placeholder="Description" name="description[]" class="form-control"
                rows="1"></textarea>
        </div>
        <div class="col-md-2">
            <label class="form-label">Price</label>
            <input class="form-control sum" required="required" name="unit_price[]" type="number" placeholder="Unit Price"
                min="1" oninput="validatePrice(this); calculateSum()"
                value="<?php echo $price = $fetch_services['membership_price']; ?>">
        </div>

        <div class="col-md-1 d-flex align-items-end justify-content-start">
            <button type="button" class="btn btn-sm btn-danger remove-invoice-item" onclick="removeInvoiceItem(this)"
                title="Remove this service">
                <i class="ti ti-trash"></i>
            </button>
        </div>
    </div>
    <script>
        function validatePrice(input) {
            if (input.value == 0) {
                input.setCustomValidity("Price must be greater than 0");
                input.reportValidity(); // shows the message in the field
            } else {
                input.setCustomValidity(""); // clear error if valid
            }
        }
    </script>

    <script>
        function removeInvoiceItem(button) {
            var row = button.closest('.invoice-item-row');
            row.remove();
            if (typeof calculateSum === 'function') {
                calculateSum();
            }
        }
    </script>


    <?php
    if ($price == "" || $price == NULL) {
        $price = 0;
    }
    if ($sub_total == "" || $sub_total == NULL) {
        $sub_total = 0;
    }
    $total = $sub_total + $price;
    $grandtotal = $total - $discount;

    ?>
    <script type="text/javascript">
        var totalval = "<?php echo $total ?>";
        var grandtotalval = "<?php echo $grandtotal ?>";
        console.log(grandtotalval);
        document.getElementById("sub_total").value = totalval;
        document.getElementById("grand_total").value = grandtotalval;
    </script>
<?php } ?>