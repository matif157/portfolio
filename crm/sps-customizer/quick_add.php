<?php
/**
 * Header Quick Action — create an Invoice or an Estimate without leaving the
 * page you are on.
 *
 * This one file is the whole feature. It has two modes:
 *
 *   1. INCLUDED (from includes/header.php) — emits the CSS + JS that builds
 *      the two SweetAlert create forms and takes over the "Add Invoice" and
 *      "Add Quotation" Quick Action items. It intercepts those clicks in the
 *      capture phase, so header.php keeps its original handlers untouched and
 *      needs no edits beyond including this file.
 *
 *   2. CALLED DIRECTLY (quick_add.php?what=…) — a small JSON endpoint for the
 *      lookups the popups need:
 *        ?what=meta        -> { services: [...], providers: [...] }
 *        ?what=client&id=N -> { client: {...} }   (estimate autofill)
 *
 * The popups collect the SAME field names the on-page modals post and submit
 * to those same pages (client_billing.php / quotations.php). Invoice and
 * quotation creation is NOT reimplemented here — PDF generation, line items
 * and activity logging stay in the one handler that already owns them, so the
 * header path can never drift from the module path.
 */

$QA_DIRECT = isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);

if ($QA_DIRECT) {
    /* ---------------------------------------------------------------- */
    /*  JSON endpoint mode                                              */
    /* ---------------------------------------------------------------- */
    include __DIR__ . '/includes/db.php';
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['user_type']) || !isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Not logged in.']);
        exit;
    }

    $what = isset($_GET['what']) ? $_GET['what'] : 'meta';

    if ($what === 'client') {
        $member_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($member_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing client id.']);
            exit;
        }

        $stmt = mysqli_prepare(
            $con,
            "SELECT member_id, name, mname, lname, bus_name, dba,
                    cell_ph, phone, prm_email, sec_email,
                    city, hcity, state, hstate, zip, hzip
             FROM members
             WHERE member_id = ?
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, 'i', $member_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Client not found.']);
            exit;
        }

        /* Mirrors the fallbacks the on-page estimate modal uses. */
        $fullName = trim($row['name'] . ' ' . $row['mname'] . ' ' . $row['lname']);
        echo json_encode([
            'client' => [
                'id'    => (int) $row['member_id'],
                'name'  => $fullName !== '' ? $fullName : ($row['bus_name'] ?: $row['dba']),
                'phone' => $row['cell_ph'] !== '' ? $row['cell_ph'] : $row['phone'],
                'email' => $row['prm_email'] !== '' ? $row['prm_email'] : $row['sec_email'],
                'city'  => $row['city'] !== '' ? $row['city'] : $row['hcity'],
                'state' => $row['state'] !== '' ? $row['state'] : $row['hstate'],
                'zip'   => $row['zip'] !== '' ? $row['zip'] : $row['hzip'],
            ],
        ]);
        exit;
    }

    /* what=meta */
    $services = [];
    $res = mysqli_query($con, "SELECT membership_id, membership_name, membership_price
                               FROM memberships
                               ORDER BY membership_name ASC");
    while ($row = mysqli_fetch_assoc($res)) {
        $services[] = [
            'id'    => (int) $row['membership_id'],
            'name'  => $row['membership_name'],
            'price' => $row['membership_price'],
        ];
    }

    $providers = [];
    $res = mysqli_query($con, "SELECT user_id, user_name FROM users ORDER BY user_name ASC");
    while ($row = mysqli_fetch_assoc($res)) {
        $providers[] = [
            'id'   => (int) $row['user_id'],
            'name' => $row['user_name'],
        ];
    }

    echo json_encode(['services' => $services, 'providers' => $providers]);
    exit;
}
?>
<style>
    /* ===== Quick Action create popups ===== */
    .qa-form-swal.swal2-popup {
        max-width: 96vw;
        padding: 0;
        border: 1px solid #d7dae3;
        border-radius: 0;
        box-shadow: 0 24px 64px rgba(16, 24, 40, .22);
        background: #fff;
    }

    .qa-form-swal .swal2-title {
        display: block;
        width: 100%;
        margin: 0;
        padding: 15px 56px 15px 22px;
        border-radius: 0;
        background: #252161;
        color: #fff;
        font-size: 1rem;
        font-weight: 600;
        text-align: left;
    }

    .qa-form-swal .swal2-close {
        position: absolute;
        top: 0;
        right: 0;
        width: 52px;
        height: 51px;
        margin: 0;
        border-radius: 0;
        color: rgba(255, 255, 255, .85);
        font-size: 1.5rem;
    }

    .qa-form-swal .swal2-close:hover,
    .qa-form-swal .swal2-close:focus {
        background: rgba(255, 255, 255, .12);
        color: #fff;
        box-shadow: none;
    }

    .qa-form-swal .swal2-html-container {
        max-height: 70vh;
        margin: 0;
        padding: 20px 22px;
        overflow-x: hidden;
        overflow-y: auto;
        color: #1f2430;
        font-size: .875rem;
        text-align: left;
    }

    .qa-form-swal .swal2-actions {
        width: 100%;
        margin: 0;
        padding: 13px 22px;
        gap: 10px;
        border-top: 1px solid #e4e7ee;
        background: #f7f8fb;
        justify-content: flex-end;
    }

    .qa-form-swal .swal2-styled {
        margin: 0;
        padding: .5rem 1.15rem;
        border-radius: 0;
        font-size: .875rem;
        font-weight: 500;
        box-shadow: none;
    }

    .qa-form-swal .swal2-confirm {
        background: #252161;
    }

    .qa-form-swal .swal2-cancel {
        background: #fff;
        border: 1px solid #ccd1dc;
        color: #384151;
    }

    /* --- fields --- */
    .qa-f-label {
        display: block;
        margin-bottom: 4px;
        color: #384151;
        font-size: .8rem;
        font-weight: 600;
    }

    .qa-f-label .qa-req {
        color: #dc3545;
    }

    .qa-form-swal input[type=text],
    .qa-form-swal input[type=number],
    .qa-form-swal input[type=email],
    .qa-form-swal input[type=date],
    .qa-form-swal select,
    .qa-form-swal textarea {
        width: 100%;
        padding: 7px 10px;
        border: 1px solid #ccd1dc;
        border-radius: 0;
        background: #fff;
        color: #1f2430;
        font-size: .85rem;
        line-height: 1.4;
        outline: none;
        box-shadow: none;
    }

    .qa-form-swal input:focus,
    .qa-form-swal select:focus,
    .qa-form-swal textarea:focus {
        border-color: #252161;
        box-shadow: 0 0 0 2px rgba(37, 33, 97, .12);
    }

    .qa-row {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 12px;
    }

    .qa-col {
        flex: 1 1 0;
        min-width: 150px;
    }

    .qa-sec {
        margin: 16px 0 8px;
        padding-bottom: 6px;
        border-bottom: 1px solid #e9ebf1;
        color: #252161;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    /* --- client search --- */
    .qa-pick-wrap {
        position: relative;
    }

    .qa-pick-results {
        position: absolute;
        z-index: 5;
        left: 0;
        right: 0;
        max-height: 220px;
        overflow-y: auto;
        border: 1px solid #ccd1dc;
        border-top: 0;
        background: #fff;
        box-shadow: 0 10px 24px rgba(16, 24, 40, .14);
    }

    .qa-pick-row {
        padding: 8px 10px;
        border-bottom: 1px solid #f0f1f5;
        cursor: pointer;
        font-size: .82rem;
    }

    .qa-pick-row:hover {
        background: #f3f4f9;
    }

    .qa-pick-row .qa-pick-bus {
        color: #6b7280;
        font-size: .75rem;
    }

    .qa-pick-empty {
        padding: 9px 10px;
        color: #6b7280;
        font-size: .8rem;
    }

    .qa-chosen {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 8px 10px;
        border: 1px solid #ccd1dc;
        background: #f7f8fb;
        font-size: .85rem;
    }

    .qa-chosen b {
        color: #252161;
    }

    /* --- line items --- */
    .qa-line {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 8px;
        padding: 10px;
        border: 1px solid #e4e7ee;
        border-left: 3px solid #252161;
        background: #fafbfd;
    }

    .qa-line .qa-i-svc {
        flex: 2 1 190px;
    }

    .qa-line .qa-i-desc {
        flex: 3 1 220px;
    }

    .qa-line .qa-i-price {
        flex: 0 1 120px;
    }

    .qa-line .qa-i-del {
        display: flex;
        align-items: flex-end;
    }

    .qa-line .qa-i-del button {
        padding: 7px 11px;
        border: 1px solid #f0b6bd;
        background: #fff;
        color: #dc3545;
        cursor: pointer;
        line-height: 1;
    }

    .qa-line .qa-i-del button:hover {
        background: #dc3545;
        border-color: #dc3545;
        color: #fff;
    }

    .qa-empty-lines {
        padding: 14px;
        border: 1px dashed #ccd1dc;
        background: #fafbfd;
        color: #6b7280;
        font-size: .82rem;
        text-align: center;
    }

    /* --- totals --- */
    .qa-totals {
        margin-top: 14px;
        padding: 12px;
        border: 1px solid #e4e7ee;
        background: #f7f8fb;
    }

    .qa-totals .qa-row:last-child {
        margin-bottom: 0;
    }

    .qa-grand input {
        font-weight: 700;
        color: #252161;
    }

    .qa-err {
        margin-bottom: 12px;
        padding: 9px 11px;
        border: 1px solid #f5c2c7;
        background: #fdf2f3;
        color: #b02a37;
        font-size: .82rem;
    }

    @media (max-width: 575.98px) {
        .qa-form-swal .swal2-html-container {
            padding: 15px;
        }

        .qa-form-swal .swal2-actions {
            padding: 11px 15px;
        }
    }
</style>

<script>
    (function () {
        "use strict";

        /* ================================================================
           Lookup data — fetched once per page, and only when a popup is
           actually opened, so no page pays for it up front.
        ================================================================ */
        var metaPromise = null;
        function qaMeta() {
            if (!metaPromise) {
                metaPromise = $.getJSON("quick_add.php?what=meta");
            }
            return metaPromise;
        }

        function esc(s) {
            return $("<div>").text(s == null ? "" : s).html();
        }

        function money(n) {
            var v = parseFloat(n);
            return isNaN(v) ? 0 : Math.round(v * 100) / 100;
        }

        function closeQuickActionMenu() {
            $("#quickActionDropdown").hide();
            $("#quickActionBtn").removeClass("active");
            $("#qaMorePanel").removeClass("show");
            $("#qaMoreToggle").removeClass("active");
        }

        function permissionDenied() {
            closeQuickActionMenu();
            Swal.fire({
                icon: "error",
                title: "Permission Denied",
                text: "You do not have permission to perform this action. Please contact your administrator.",
                confirmButtonColor: "#6fd943"
            });
        }

        /* ================================================================
           Line items — shared by both popups. Both handlers read the same
           service[] / description[] / unit_price[] arrays.
        ================================================================ */
        function itemsMarkup(services) {
            var opts = services.map(function (s) {
                return '<option value="' + esc(s.name) + '" data-price="' + esc(s.price) + '">' +
                    esc(s.name) + "</option>";
            }).join("");

            return '' +
                '<div class="qa-sec">Services</div>' +
                '<div class="qa-row">' +
                '  <div class="qa-col" style="flex:3 1 260px;">' +
                '    <label class="qa-f-label">Add a service</label>' +
                '    <select class="qa-svc-pick"><option value="">Select a service…</option>' + opts + "</select>" +
                "  </div>" +
                '  <div class="qa-col" style="flex:0 1 150px;display:flex;align-items:flex-end;">' +
                '    <button type="button" class="swal2-styled qa-add-line" ' +
                '            style="margin:0;width:100%;background:#252161;border-radius:0;padding:8px 10px;font-size:.82rem;">' +
                "      + Add service</button>" +
                "  </div>" +
                "</div>" +
                '<div class="qa-lines"></div>';
        }

        function totalsMarkup() {
            return '' +
                '<div class="qa-totals">' +
                '  <div class="qa-row">' +
                '    <div class="qa-col"><label class="qa-f-label">Sub Total</label>' +
                '      <input type="number" step="0.01" class="qa-sub" value="0" readonly></div>' +
                '    <div class="qa-col"><label class="qa-f-label">Discount</label>' +
                '      <input type="number" step="0.01" class="qa-disc" value="0" min="0"></div>' +
                '    <div class="qa-col qa-grand"><label class="qa-f-label">Grand Total</label>' +
                '      <input type="number" step="0.01" class="qa-grandtotal" value="0" readonly></div>' +
                "  </div>" +
                "</div>";
        }

        function bindItems($root) {
            var $items = $root.find(".qa-lines");

            function refreshEmpty() {
                if ($items.children(".qa-line").length === 0) {
                    if (!$items.children(".qa-empty-lines").length) {
                        $items.html('<div class="qa-empty-lines">No services added yet — pick one above.</div>');
                    }
                } else {
                    $items.children(".qa-empty-lines").remove();
                }
            }

            function recalc() {
                var sub = 0;
                $items.find(".qa-i-price input").each(function () {
                    sub += money($(this).val());
                });
                sub = money(sub);
                var disc = money($root.find(".qa-disc").val());
                if (disc < 0) disc = 0;
                $root.find(".qa-sub").val(sub.toFixed(2));
                /* Same rule the on-page modals use: grand = sub - discount. */
                $root.find(".qa-grandtotal").val(money(sub - disc).toFixed(2));
            }

            $root.find(".qa-add-line").on("click", function () {
                var $sel = $root.find(".qa-svc-pick");
                var name = $sel.val();
                if (!name) { $sel.focus(); return; }
                var price = $sel.find("option:selected").data("price");
                $items.append(
                    '<div class="qa-line">' +
                    '  <div class="qa-i-svc"><label class="qa-f-label">Service</label>' +
                    '    <input type="text" value="' + esc(name) + '"></div>' +
                    '  <div class="qa-i-desc"><label class="qa-f-label">Description</label>' +
                    '    <textarea rows="1"></textarea></div>' +
                    '  <div class="qa-i-price"><label class="qa-f-label">Price</label>' +
                    '    <input type="number" step="0.01" min="0" value="' + esc(price || 0) + '"></div>' +
                    '  <div class="qa-i-del"><button type="button" title="Remove">&times;</button></div>' +
                    "</div>"
                );
                $sel.val("");
                refreshEmpty();
                recalc();
            });

            $items.on("click", ".qa-i-del button", function () {
                $(this).closest(".qa-line").remove();
                refreshEmpty();
                recalc();
            });

            $items.on("input change", ".qa-i-price input", recalc);
            $root.find(".qa-disc").on("input change", recalc);

            refreshEmpty();
            recalc();

            /* Hand the line items back as the POST arrays the handlers expect. */
            $root.data("qaCollect", function (fd) {
                var ok = false;
                $items.find(".qa-line").each(function () {
                    var $r = $(this);
                    fd.append("service[]", $r.find(".qa-i-svc input").val());
                    fd.append("description[]", $r.find(".qa-i-desc textarea").val());
                    fd.append("unit_price[]", $r.find(".qa-i-price input").val());
                    ok = true;
                });
                fd.append("sub_total", $root.find(".qa-sub").val());
                fd.append("discount", $root.find(".qa-disc").val() || "0");
                fd.append("grand_total", $root.find(".qa-grandtotal").val());
                return ok;
            });
        }

        /* Client search — same endpoint the header search bar already calls. */
        function bindClientSearch($root, onPick) {
            var $input = $root.find(".qa-pick-input");
            var $out = $root.find(".qa-pick-results");
            var timer;

            $input.on("input", function () {
                clearTimeout(timer);
                var q = $(this).val();
                if (q.trim().length < 2) { $out.empty().hide(); return; }
                timer = setTimeout(function () {
                    $.ajax({
                        url: "search_clients.php",
                        type: "POST",
                        data: { search: q, profile_status: 1, search_by: "all" },
                        success: function (html) {
                            var $links = $("<div>").html(html).find("a.search-result-link");
                            $out.empty().show();
                            if (!$links.length) {
                                $out.html('<div class="qa-pick-empty">No clients found.</div>');
                                return;
                            }
                            $links.each(function () {
                                var id = $(this).data("member-id");
                                var nm = $(this).find("strong").eq(0).text().trim();
                                var bus = $(this).find("strong").eq(1).text().trim();
                                $('<div class="qa-pick-row"></div>')
                                    .append($("<div>").text(nm))
                                    .append(bus ? $('<div class="qa-pick-bus"></div>').text(bus) : "")
                                    .on("click", function () {
                                        $out.empty().hide();
                                        $input.val("");
                                        onPick(id, nm);
                                    })
                                    .appendTo($out);
                            });
                        }
                    });
                }, 300);
            });
        }

        /* Submit to the page that already owns the create logic, then read its
           own success banner back out of the response. */
        function postForm(url, fd, successNeedle) {
            return fetch(url, { method: "POST", body: fd, credentials: "same-origin" })
                .then(function (r) {
                    if (!r.ok) throw new Error("Server returned " + r.status + ".");
                    return r.text();
                })
                .then(function (html) {
                    if (html.indexOf(successNeedle) === -1) {
                        throw new Error("The server did not confirm creation. Open the module and check before retrying.");
                    }
                    return true;
                });
        }

        function showError($root, message) {
            var $box = $root.find(".qa-err");
            if (!$box.length) { $box = $('<div class="qa-err"></div>').prependTo($root); }
            $box.text(message);
            $root.closest(".swal2-html-container").scrollTop(0);
        }

        function doneDialog(title, text, url, openLabel) {
            Swal.fire({
                icon: "success",
                title: title,
                text: text,
                showCancelButton: true,
                confirmButtonText: openLabel,
                cancelButtonText: "Stay here",
                confirmButtonColor: "#252161",
                customClass: { popup: "qa-form-swal" }
            }).then(function (r) {
                if (r.isConfirmed) window.location.href = url;
            });
        }

        /* ================================================================
           ADD INVOICE  (client is chosen first, then the whole invoice is
           filled in and created from here)
        ================================================================ */
        function openInvoiceForm(clientId, clientName) {
            clientId = parseInt(clientId, 10);
            if (!clientId) return;

            Swal.fire({ title: "Loading…", didOpen: function () { Swal.showLoading(); }, allowOutsideClick: false });

            qaMeta().done(function (meta) {
                var provOpts = (meta.providers || []).map(function (p) {
                    return '<option value="' + esc(p.id) + '">' + esc(p.name) + "</option>";
                }).join("");

                Swal.fire({
                    title: "Add New Invoice",
                    html: '' +
                        '<div class="qa-formroot">' +
                        '  <div class="qa-chosen"><span>Client: <b>' + esc(clientName || ("#" + clientId)) + "</b></span></div>" +
                        itemsMarkup(meta.services || []) +
                        '  <div class="qa-sec">Invoice details</div>' +
                        '  <div class="qa-row">' +
                        '    <div class="qa-col"><label class="qa-f-label">Service Provider <span class="qa-req">*</span></label>' +
                        '      <select class="qa-provider"><option value="">Select service provider…</option>' + provOpts + "</select></div>" +
                        '    <div class="qa-col"><label class="qa-f-label">Due Date <span class="qa-req">*</span></label>' +
                        '      <input type="date" class="qa-due"></div>' +
                        "  </div>" +
                        totalsMarkup() +
                        "</div>",
                    width: "820px",
                    showCancelButton: true,
                    showCloseButton: true,
                    allowOutsideClick: false,
                    heightAuto: false,
                    confirmButtonText: "Create Invoice",
                    cancelButtonText: "Cancel",
                    customClass: { popup: "qa-form-swal" },
                    didOpen: function (popup) { bindItems($(popup).find(".qa-formroot")); },
                    preConfirm: function () {
                        var $root = $(Swal.getPopup()).find(".qa-formroot");
                        var provider = $root.find(".qa-provider").val();
                        var due = $root.find(".qa-due").val();

                        var fd = new FormData();
                        var hasItems = $root.data("qaCollect")(fd);

                        if (!hasItems) { showError($root, "Add at least one service before creating the invoice."); return false; }
                        if (!provider) { showError($root, "Select a service provider."); return false; }
                        if (!due) { showError($root, "Pick a due date."); return false; }

                        fd.append("Service_Provider", provider);
                        fd.append("billing_cycle", due);
                        fd.append("createinvoice", "1");

                        Swal.showLoading();
                        return postForm("client_billing.php?id=" + clientId, fd, "Invoice created successfully")
                            .catch(function (err) {
                                Swal.hideLoading();
                                showError($root, err.message);
                                return false;
                            });
                    }
                }).then(function (res) {
                    if (!res.isConfirmed || res.value === false) return;
                    doneDialog(
                        "Invoice created",
                        "The invoice was created for " + (clientName || ("client #" + clientId)) + ".",
                        "client_billing.php?id=" + clientId,
                        "Open client billing"
                    );
                });
            }).fail(function () {
                Swal.fire({ icon: "error", title: "Could not load services", text: "Please try again." });
            });
        }

        /* ================================================================
           ADD ESTIMATE  (carries its own client fields, so no picker step)
        ================================================================ */
        function openEstimateForm() {
            Swal.fire({ title: "Loading…", didOpen: function () { Swal.showLoading(); }, allowOutsideClick: false });

            qaMeta().done(function (meta) {
                Swal.fire({
                    title: "Add New Estimate",
                    html: '' +
                        '<div class="qa-formroot">' +
                        '  <div class="qa-sec">Client</div>' +
                        '  <div class="qa-row"><div class="qa-col qa-pick-wrap">' +
                        '    <label class="qa-f-label">Search an existing client (optional — fills the fields below)</label>' +
                        '    <input type="text" class="qa-pick-input" placeholder="Type at least 2 characters…" autocomplete="off">' +
                        '    <div class="qa-pick-results" style="display:none;"></div>' +
                        '    <input type="hidden" class="qa-member-id" value="0">' +
                        "  </div></div>" +
                        '  <div class="qa-row">' +
                        '    <div class="qa-col"><label class="qa-f-label">Name <span class="qa-req">*</span></label>' +
                        '      <input type="text" class="qa-name"></div>' +
                        '    <div class="qa-col"><label class="qa-f-label">Phone <span class="qa-req">*</span></label>' +
                        '      <input type="text" class="qa-phone"></div>' +
                        '    <div class="qa-col"><label class="qa-f-label">Email</label>' +
                        '      <input type="email" class="qa-email"></div>' +
                        "  </div>" +
                        '  <div class="qa-row">' +
                        '    <div class="qa-col"><label class="qa-f-label">City</label><input type="text" class="qa-city"></div>' +
                        '    <div class="qa-col"><label class="qa-f-label">State</label><input type="text" class="qa-state"></div>' +
                        '    <div class="qa-col"><label class="qa-f-label">Zip</label><input type="text" class="qa-zip"></div>' +
                        "  </div>" +
                        itemsMarkup(meta.services || []) +
                        totalsMarkup() +
                        "</div>",
                    width: "820px",
                    showCancelButton: true,
                    showCloseButton: true,
                    allowOutsideClick: false,
                    heightAuto: false,
                    confirmButtonText: "Create Estimate",
                    cancelButtonText: "Cancel",
                    customClass: { popup: "qa-form-swal" },
                    didOpen: function (popup) {
                        var $root = $(popup).find(".qa-formroot");
                        bindItems($root);
                        bindClientSearch($root, function (id) {
                            $.getJSON("quick_add.php?what=client&id=" + id).done(function (d) {
                                var c = d.client || {};
                                $root.find(".qa-member-id").val(c.id || 0);
                                $root.find(".qa-name").val(c.name || "");
                                $root.find(".qa-phone").val(c.phone || "");
                                $root.find(".qa-email").val(c.email || "");
                                $root.find(".qa-city").val(c.city || "");
                                $root.find(".qa-state").val(c.state || "");
                                $root.find(".qa-zip").val(c.zip || "");
                            });
                        });
                    },
                    preConfirm: function () {
                        var $root = $(Swal.getPopup()).find(".qa-formroot");
                        var name = $.trim($root.find(".qa-name").val());
                        var phone = $.trim($root.find(".qa-phone").val());

                        var fd = new FormData();
                        var hasItems = $root.data("qaCollect")(fd);

                        if (!hasItems) { showError($root, "Add at least one service before creating the estimate."); return false; }
                        if (!name) { showError($root, "Enter the client name."); return false; }
                        if (!phone) { showError($root, "Enter a phone number."); return false; }

                        fd.append("member_id", $root.find(".qa-member-id").val() || "0");
                        fd.append("name", name);
                        fd.append("phonecell", phone);
                        fd.append("email", $root.find(".qa-email").val());
                        fd.append("city", $root.find(".qa-city").val());
                        fd.append("state", $root.find(".qa-state").val());
                        fd.append("zip", $root.find(".qa-zip").val());
                        fd.append("createinvoice", "1");

                        Swal.showLoading();
                        return postForm("quotations.php", fd, "Quotation Has Been Sent Successfully")
                            .catch(function (err) {
                                Swal.hideLoading();
                                showError($root, err.message);
                                return false;
                            });
                    }
                }).then(function (res) {
                    if (!res.isConfirmed || res.value === false) return;
                    doneDialog("Estimate created", "The estimate has been created.", "quotations.php", "Open estimates");
                });
            }).fail(function () {
                Swal.fire({ icon: "error", title: "Could not load services", text: "Please try again." });
            });
        }

        /* ================================================================
           Take over the two Quick Action items.

           Captured on the way DOWN, so header.php's own delegated (bubble
           phase) handlers never run for these two — which is why header.php
           needs no changes beyond including this file.
        ================================================================ */
        function clientPicker(onPick) {
            Swal.fire({
                title: "Select a Client",
                html: '<input type="text" id="qaClientSearch" class="swal2-input qa-client-search-input" ' +
                    'placeholder="Search by name, business, phone...">' +
                    '<div id="qaClientResults" class="qa-client-results"></div>',
                width: 440,
                showConfirmButton: false,
                showCloseButton: true,
                customClass: { popup: "qa-picker-popup" },
                didOpen: function () {
                    var $input = $("#qaClientSearch");
                    var $results = $("#qaClientResults");
                    var timer;

                    $input.on("input", function () {
                        clearTimeout(timer);
                        var q = $(this).val();
                        if (q.trim().length < 2) {
                            $results.html('<div class="qa-client-empty">Type at least 2 characters to search.</div>');
                            return;
                        }
                        timer = setTimeout(function () {
                            $.ajax({
                                url: "search_clients.php",
                                type: "POST",
                                data: { search: q, profile_status: 1, search_by: "all" },
                                success: function (html) {
                                    var $links = $("<div>").html(html).find("a.search-result-link");
                                    $results.empty();
                                    if (!$links.length) {
                                        $results.html('<div class="qa-client-empty">No clients found.</div>');
                                        return;
                                    }
                                    $links.each(function () {
                                        var id = $(this).data("member-id");
                                        var nm = $(this).find("strong").eq(0).text().trim();
                                        var bus = $(this).find("strong").eq(1).text().trim();
                                        var $row = $('<div class="qa-client-row"></div>').append($("<strong></strong>").text(nm));
                                        if (bus) $row.append($('<div class="qa-client-row-bus"></div>').text(bus));
                                        $row.on("click", function () {
                                            Swal.close();
                                            onPick(id, nm);
                                        });
                                        $results.append($row);
                                    });
                                }
                            });
                        }, 350);
                    });

                    $results.html('<div class="qa-client-empty">Type at least 2 characters to search.</div>');
                    $input.trigger("focus");
                }
            });
        }

        function takeOver(e) {
            if (e.type === "keypress" && e.which !== 13 && e.keyCode !== 13) return;

            var el = e.target && e.target.closest
                /* .qa-item here is header.php's own menu-item class — not one
                   of this file's .qa-line form classes. Don't rename it. */
                ? e.target.closest("#qaAddInvoiceBtn, #quickActionDropdown a.qa-item[href^='quotations.php']")
                : null;
            if (!el) return;

            e.preventDefault();
            e.stopPropagation();

            /* Same client-side guard header.php applies; the target page still
               owns real enforcement. */
            if (el.getAttribute("data-allowed") === "0") {
                permissionDenied();
                return;
            }

            closeQuickActionMenu();

            if (el.id === "qaAddInvoiceBtn") {
                clientPicker(openInvoiceForm);
            } else {
                openEstimateForm();
            }
        }

        document.addEventListener("click", takeOver, true);
        document.addEventListener("keypress", takeOver, true);
    })();
</script>
