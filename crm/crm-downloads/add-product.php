<?php
include "includes/db.php";
$id = $_POST["main_id"];
$client_id = $_POST['client_id'];
$sub_total = $_POST['sub_total'];
$discount = $_POST['discount'];
$grand_total = $_POST['grand_total'];

$service_query = mysqli_query($con, "SELECT * FROM memberships where membership_id='$id'");
$c = 1;
while ($fetch_services = mysqli_fetch_array($service_query)) {
        ?>

        <?php
        $query1 = "SELECT * FROM orders WHERE mem_id='$client_id' AND prod_id='$id'";
        $result = mysqli_query($con, $query1);
        $rows = mysqli_num_rows($result);
        if ($rows >= 1) { ?>

                <div class="row mb-2" id="myTable"
                        style="background: #d34e4e;padding: 10px 0px;border-radius: 4px;border-bottom: 2px dashed #f9f9f9;">
                        <div class="col-md-12">
                                <p style="color:white;margin:0px;">This Product is already associated</p>
                        </div>
                </div>
                <script type="text/javascript">
                        // Get the submit button element by its ID
                        var submitButton = document.getElementById("addassociatedproduct");

                        // Disable the button
                        submitButton.disabled = true;
                </script>

        <?php } else { ?>
                <div class="row mb-2" id="myTable"
                        style="background: #e9e9e9;padding: 10px 0px;border-radius: 4px;border-bottom: 2px dashed #b9b9b9;">
                        <div class="col-md-10">
                                <input type="hidden" name="servicename" value="<?php echo $fetch_services['membership_name']; ?>">
                                <label for="description" class="form-label">Description</label>
                                <textarea placeholder="Description" id="description" name="description" class="form-control"
                                        rows="1"></textarea>
                        </div>
                        <div class="col-md-2">
                                <label for="unit_price" class="form-label">Price</label>
                                <input class="form-control sum" required="required" name="unit_price[]" type="number"
                                        placeholder="Unit Price" onchange="calculateSum()" onkeypress="calculateSum()"
                                        onkeyup="calculateSum()" id="unit_price"
                                        value="<?php echo $price = $fetch_services['membership_price']; ?>">
                        </div>
                </div>

                <?php
                $total = (float) $sub_total + (float) $price;
                $grandtotal = $total - (float) $discount;


                ?>
                <script type="text/javascript">
                        var totalval = "<?php echo $total ?>";
                        var grandtotalval = "<?php echo $grandtotal ?>";
                        console.log(grandtotalval);
                        document.getElementById("sub_total").value = totalval;
                        document.getElementById("grand_total").value = grandtotalval;

                </script>
        <?php }
} ?>