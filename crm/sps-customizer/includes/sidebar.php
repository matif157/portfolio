<style type="text/css">
    .dash-submenu {
        display: none;
    }

    .dash-item.active .dash-submenu {
        display: block;
    }

    .dash-sidebar .main-logo {
        justify-content: center;
        min-height: 100px;
        max-height: 100px;
        min-width: 236px;
    }

    .ps__thumb-y {
        display: none;
    }

    .dash-sidebar .main-logo a img {
        width: fit-content;
        height: 188px;
        object-fit: contain;
        max-width: -webkit-fill-available;
        max-width: -moz-available;
        max-height: -webkit-fill-available;
        max-height: -moz-available;
        margin: 0 auto;
        display: block;
    }

    .tooltip1 {
        position: relative;
        display: inline-block;
        opacity: 1 !important;
        border-bottom: 1px dotted black;
    }

    .tooltip1 .tooltiptext1 {
        visibility: hidden;
        width: 125px;
        background-color: #555;
        color: #fff;
        text-align: center;
        border-radius: 6px;
        padding: 5px 0;
        position: absolute;
        z-index: 1;
        bottom: 125%;
        left: 50%;
        margin-left: -60px;
        font-size: 12px;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .tooltip1:hover .tooltiptext1 {
        visibility: visible;
        opacity: 1;
    }

    body.sidebar-collapsed .dash-sidebar {
        width: 80px;
        min-width: 80px;
        z-index: 1020;
    }

    body.sidebar-collapsed .dash-sidebar .main-logo {
        min-width: 80px;
        padding: 0 10px;
    }

    body.sidebar-collapsed .dash-sidebar .main-logo a.b-brand {
        display: none !important;
    }

    body.sidebar-collapsed .dash-sidebar .main-logo a.b-brand-collapsed {
        display: flex !important;
        align-items: center;
        justify-content: center;
        width: 150%;
        height: 100%;
        margin-left: 20px;
    }

    body:not(.sidebar-collapsed) .dash-sidebar .main-logo a.b-brand-collapsed {
        display: none !important;
    }

    body.sidebar-collapsed .dash-sidebar .main-logo a.b-brand-collapsed img {
        width: 100px !important;
        height: 100px !important;
        object-fit: contain;
        border-radius: 8px;
    }

    body.sidebar-collapsed .dash-sidebar .dash-mtext,
    body.sidebar-collapsed .dash-sidebar .dash-arrow,
    body.sidebar-collapsed .dash-sidebar .badge {
        display: none !important;
    }

    body.sidebar-collapsed .dash-sidebar .dash-submenu {
        display: none !important;
    }

    body.sidebar-collapsed .dash-sidebar .dash-link {
        justify-content: center;
        padding: 10px;
        text-align: center;
    }

    body.sidebar-collapsed .dash-sidebar .dash-micon {
        margin-right: 0 !important;
        font-size: 22px;
        display: block;
    }

    body.sidebar-collapsed .dash-container {
        margin-left: 80px;
        width: calc(100% - 80px);
    }

    body.sidebar-collapsed .dash-sidebar::-webkit-scrollbar {
        width: 0px;
        background: transparent;
    }

    /* ==========================================
       NEW: Sidebar Header & Toggle Styles
       ========================================== */

    .sidebar-header {
        height: 50px;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 15px;
        border-bottom: 1px solid #f0f0f0;
        background: #fff;
        box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
    }

    #sidebar-toggle {
        position: relative;
        top: auto;
        right: auto;
        z-index: 1000;
        background: #fff;
        border: 1px solid #e1e1e1;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
        color: #000;
        transition: all 0.3s;
    }

    #sidebar-toggle:hover {
        background: #f8f9fa;
        transform: scale(1.05);
    }

    /* Center the toggle when sidebar is collapsed */
    body.sidebar-collapsed .sidebar-header {
        justify-content: center;
        padding-right: 0;
    }

    body.sidebar-collapsed .sidebar-header #sidebar-toggle {
        margin: 0;
    }

    /* ========================================== */

    .sidebar-popover {
        position: fixed;
        left: 80px;
        background: #fff;
        border: 1px solid #e1e1e1;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        z-index: 9999 !important;
        border-radius: 4px;
        padding: 0;
        display: none;
        min-width: 240px;
        max-width: 300px;
        white-space: nowrap;
    }

    .sidebar-popover::before {
        content: "";
        position: absolute;
        top: 15px;
        left: -6px;
        width: 12px;
        height: 12px;
        background: #fff;
        transform: rotate(45deg);
        border-left: 1px solid #e1e1e1;
        border-bottom: 1px solid #e1e1e1;
    }

    .sidebar-popover .dash-link {
        padding: 12px 20px;
        display: flex;
        align-items: center;
        color: #333 !important;
        text-decoration: none;
        text-align: left;
        justify-content: flex-start;
        transition: background 0.2s;
        font-size: 14px;
        border-bottom: 1px solid #f9f9f9;
        opacity: 1 !important;
        visibility: visible !important;
        width: 100%;
        box-sizing: border-box;
    }

    .sidebar-popover .dash-link:last-child {
        border-bottom: none;
    }

    .sidebar-popover .dash-link i {
        margin-right: 12px;
        font-size: 16px;
        color: #555;
        width: 20px;
        text-align: center;
    }

    .sidebar-popover .dash-link:hover {
        background: #f8f9fa;
        color: #007bff !important;
    }

    .sidebar-popover .dash-link:hover i {
        color: #007bff;
    }

    .sidebar-popover .popover-header {
        font-weight: 700;
        padding: 12px 20px;
        border-bottom: 1px solid #eee;
        background: #f1f1f1;
        color: #000 !important;
        font-size: 15px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .dash-sidebar,
    .dash-container,
    .dash-micon {
        transition: all 0.3s ease;
    }

    #see-more-trigger {
        color: #f5a623 !important;
        font-weight: 600;
    }

    #see-more-trigger .dash-micon i,
    #see-more-trigger .dash-arrow i {
        color: #f5a623 !important;
    }

    #see-more-trigger:hover {
        background: rgba(245, 166, 35, 0.1) !important;
    }

    /* Non-active (default) sidebar items shown with low-opacity navy background */
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item>.dash-link,
    .dash-sidebar .dash-navbar>.dash-item>.dash-link {
        background: rgba(37, 33, 97, 0.12) !important;
        color: #252161 !important;
    }

    .dash-sidebar.light-sidebar .dash-navbar>.dash-item>.dash-link .dash-mtext,
    .dash-sidebar .dash-navbar>.dash-item>.dash-link .dash-mtext,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item>.dash-link .dash-micon i,
    .dash-sidebar .dash-navbar>.dash-item>.dash-link .dash-micon i,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item>.dash-link .dash-micon svg,
    .dash-sidebar .dash-navbar>.dash-item>.dash-link .dash-micon svg {
        color: #252161 !important;
    }

    /* Override theme green (#0CAF60) with logo color (#252161) on active/hover only */
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item.active>.dash-link,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:active>.dash-link,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:focus>.dash-link,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:hover>.dash-link,
    .dash-sidebar .dash-navbar>.dash-item.active>.dash-link,
    .dash-sidebar .dash-navbar>.dash-item:active>.dash-link,
    .dash-sidebar .dash-navbar>.dash-item:focus>.dash-link,
    .dash-sidebar .dash-navbar>.dash-item:hover>.dash-link {
        background: linear-gradient(141.55deg, #252161 3.46%, #252161 99.86%), #252161 !important;
        color: #fff !important;
        box-shadow: 0 5px 7px -1px rgba(37, 33, 97, 0.3) !important;
    }

    .dash-sidebar.light-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-mtext,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:active>.dash-link .dash-mtext,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:focus>.dash-link .dash-mtext,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:hover>.dash-link .dash-mtext,
    .dash-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-mtext,
    .dash-sidebar .dash-navbar>.dash-item:active>.dash-link .dash-mtext,
    .dash-sidebar .dash-navbar>.dash-item:focus>.dash-link .dash-mtext,
    .dash-sidebar .dash-navbar>.dash-item:hover>.dash-link .dash-mtext {
        color: #fff !important;
    }

    /* Active/hover icon stays navy so it shows inside its white icon box */
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-micon i,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:active>.dash-link .dash-micon i,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:focus>.dash-link .dash-micon i,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:hover>.dash-link .dash-micon i,
    .dash-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-micon i,
    .dash-sidebar .dash-navbar>.dash-item:active>.dash-link .dash-micon i,
    .dash-sidebar .dash-navbar>.dash-item:focus>.dash-link .dash-micon i,
    .dash-sidebar .dash-navbar>.dash-item:hover>.dash-link .dash-micon i,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-micon svg,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:active>.dash-link .dash-micon svg,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:focus>.dash-link .dash-micon svg,
    .dash-sidebar.light-sidebar .dash-navbar>.dash-item:hover>.dash-link .dash-micon svg,
    .dash-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-micon svg,
    .dash-sidebar .dash-navbar>.dash-item:active>.dash-link .dash-micon svg,
    .dash-sidebar .dash-navbar>.dash-item:focus>.dash-link .dash-micon svg,
    .dash-sidebar .dash-navbar>.dash-item:hover>.dash-link .dash-micon svg {
        color: #252161 !important;
    }

    .dash-sidebar.light-sidebar .dash-item .dash-submenu .dash-item::before,
    .dash-sidebar .dash-item .dash-submenu .dash-item::before {
        border-right-color: #252161 !important;
    }

    .dash-sidebar.light-sidebar .dash-item .dash-submenu .dash-item.active>.dash-link,
    .dash-sidebar.light-sidebar .dash-item .dash-submenu .dash-item:active>.dash-link,
    .dash-sidebar.light-sidebar .dash-item .dash-submenu .dash-item:focus>.dash-link,
    .dash-sidebar.light-sidebar .dash-item .dash-submenu .dash-item:hover>.dash-link,
    .dash-sidebar .dash-item .dash-submenu .dash-item.active>.dash-link,
    .dash-sidebar .dash-item .dash-submenu .dash-item:active>.dash-link,
    .dash-sidebar .dash-item .dash-submenu .dash-item:focus>.dash-link,
    .dash-sidebar .dash-item .dash-submenu .dash-item:hover>.dash-link {
        color: #252161 !important;
    }

    /* Fix: long labels (e.g. "Product Renewals") left no room for the
       float:right chevron on the same line, so it dropped onto the next
       menu item. Lay the link out as a flex row instead so the arrow
       always stays pinned to the end of its own row. */
    body:not(.sidebar-collapsed) .dash-sidebar .dash-navbar>.dash-item>.dash-link {
        display: flex;
        align-items: center;
    }

    body:not(.sidebar-collapsed) .dash-sidebar .dash-navbar>.dash-item>.dash-link .dash-mtext {
        flex: 1 1 auto;
        min-width: 0;
    }

    body:not(.sidebar-collapsed) .dash-sidebar .dash-navbar>.dash-item>.dash-link .dash-arrow {
        float: none;
        flex-shrink: 0;
        margin-left: 8px;
    }
</style>

<nav class="dash-sidebar light-sidebar transprent-bg">
    <div class="navbar-wrapper">


        <div class="sidebar-header">
            <div id="sidebar-toggle">
                <i class="ti ti-chevron-left" id="toggle-icon"></i>
            </div>
        </div>

        <div class="m-header main-logo" style="position: relative;">
            <a href="index.php" class="b-brand">
                <img src="storage/uploads/logo/logo-dark.png" alt="" class="logo logo-lg" />
            </a>
            <a href="index.php" class="b-brand-collapsed">
                <img src="storage/uploads/logo/logoin.png" alt="" />
            </a>


        </div>

        <div class="navbar-content">
            <ul class="dash-navbar">

                <!--------------------- Start Dashboard ----------------------------------->
                <li class="dash-item dash-hasmenu ">
                    <a href="index.php" class="dash-link ">
                        <span class="dash-micon"><i class="ti ti-home"></i></span>
                        <span class="dash-mtext">Dashboard</span>
                    </a>
                </li>

                <!-- <li class="dash-item dash-hasmenu">
                    <a href="recycle_bin.php" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-trash"></i></span>
                        <span class="dash-mtext">Recycle Bin</span>
                    </a>
                </li> -->



                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 171) { ?>
                    <!--------------------- See More ----------------------------------->
                    <li class="dash-item dash-hasmenu" id="see-more-menu">
                        <a href="#" class="dash-link" id="see-more-trigger">
                            <span class="dash-micon"><i class="ti ti-dots-circle-horizontal"></i></span>
                            <span class="dash-mtext">See More <i class="ti ti-lock" style="font-size:12px;opacity:0.6;"></i></span>
                            <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                        </a>
                        <ul class="dash-submenu">
                            <li class="dash-item">
                                <a href="ai.php" class="dash-link">
                                    <i class="ti ti-brain"></i> AI Assistant
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="bookeeping.php" class="dash-link">
                                    <i class="ti ti-books"></i> Bookkeeping
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="curdir.php" class="dash-link">
                                    <i class="ti ti-folder-cog"></i> Cur Dir
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="Db_Backup_Cli.php" class="dash-link">
                                    <i class="ti ti-database-export"></i> DB Backup
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="dbswitch.php" class="dash-link">
                                    <i class="ti ti-database-cog"></i> DB Switcher
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="dbswitcher_updater.php" class="dash-link">
                                    <i class="ti ti-database-plus"></i> DB Switcher Updater
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="manila_edit.php" class="dash-link">
                                    <i class="ti ti-edit"></i> Manila Edit
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="messanger.php" class="dash-link">
                                    <i class="ti ti-brand-messenger"></i> Messenger
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="queries.php" class="dash-link">
                                    <i class="ti ti-code"></i> Queries
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="recover_missing_invoices.php" class="dash-link">
                                    <i class="ti ti-file-search"></i> Recover Invoices
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="recycle_bin.php" class="dash-link">
                                    <i class="ti ti-trash"></i> Recycle Bin
                                </a>
                            </li>
                            <!-- <li class="dash-item">
                                <a href="reminder_calender.php" class="dash-link">
                                    <i class="ti ti-calendar"></i> Reminder Calendar
                                </a>
                            </li> -->

                            <li class="dash-item">
                                <a href="sip.php" class="dash-link">
                                    <i class="ti ti-phone"></i> SIP
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="stripe_pay_link_gen.php" class="dash-link">
                                    <i class="ti ti-link"></i> Stripe Link Gen
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="stripe_payment_view.php" class="dash-link">
                                    <i class="ti ti-credit-card"></i> Stripe Payments
                                </a>
                            </li>
                        </ul>
                    </li>
                    <!--------------------- See More End ----------------------------------->
                <?php } ?>

                <!--------------------- Active Inactive clients added ----------------------------------->

                <li class="dash-item dash-hasmenu" id="manage-clients-menu">

                    <a href="#" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-users"></i></span>
                        <span class="dash-mtext">Manage Clients</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                    <ul class="dash-submenu">
                        <?php if ($acc_add_client == "Yes") { ?>
                            <li class="dash-item">
                                <a href="add_client.php" class="dash-link">
                                    <i class=""></i> Add New
                                </a>
                            </li>
                        <?php } ?>
                        <?php if ($acc_view_client == "Yes") { ?>
                            <li class="dash-item">
                                <a class="dash-link" href="view_clients.php">
                                    <i class="ti ti-list"></i> View All Clients
                                </a>
                            </li>
                        <?php } ?>
                        <?php if ($acc_view_client == "Yes") { ?>
                            <li class="dash-item">
                                <a class="dash-link" href="view_clients.php?profile_status=1">
                                    <i class="ti ti-user-check"></i> Active Clients
                                </a>
                            </li>
                            <li class="dash-item">
                                <a class="dash-link" href="view_clients.php?profile_status=0">
                                    <i class="ti ti-user-x"></i> Inactive Clients
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                </li>

                <!--------------------- Active Inactive clients end ----------------------------------->

               
                <!-- ========== COMMENTS GROUP ========== -->
                <li class="dash-item dash-hasmenu">
                    <a href="#" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-message-circle"></i></span>
                        <span class="dash-mtext">All Comments</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>

                    <ul class="dash-submenu">
                        <li class="dash-item">
                            <a href="comments.php" class="dash-link">
                                <i class="ti ti-list"></i>Comments
                            </a>
                        </li>
                        <li class="dash-item">
                            <a href="invoice_comments.php" class="dash-link">
                                <i class="ti ti-file-invoice"></i> Inv Comment
                            </a>
                        </li>

                        <li class="dash-item">
                            <a href="general_comments.php" class="dash-link">
                                <i class="ti ti-notes"></i> General
                            </a>
                        </li>
                        <li class="dash-item">
                            <a href="project_comments.php" class="dash-link">
                                <i class="ti ti-briefcase"></i> Project Comments
                            </a>
                        </li>
                        <li class="dash-item">
                            <a href="client_alerts.php" class="dash-link">
                                <i class="ti ti-bell"></i> Client Alerts
                            </a>
                        </li>
                       
                    </ul>
                </li>
                <!-- ========== /COMMENTS GROUP ========== -->

                <!-- ========== COMMUNICATION GROUP ========== -->
                <li class="dash-item dash-hasmenu">
                    <a href="#" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-brand-messenger"></i></span>
                        <span class="dash-mtext">Communication</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                    <ul class="dash-submenu">
                        <li class="dash-item">
                            <a href="comm_hub.php" class="dash-link">
                                <i class="ti ti-messages"></i> Communication
                            </a>
                        </li>
                        <li class="dash-item">
                            <a href="messanger.php" class="dash-link">
                                <i class="ti ti-brand-messenger"></i> Messenger
                            </a>
                        </li>
                    </ul>
                </li>
                <!-- ========== /COMMUNICATION GROUP ========== -->

                <!--------------------- Projects added start ----------------------------------->
                <li class="dash-item dash-hasmenu">
                    <a href="#" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-briefcase"></i></span>
                        <span class="dash-mtext">Projects</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                    <ul class="dash-submenu">
                        <li class="dash-item">
                            <a href="add_new_projects.php" class="dash-link">
                                <i class="ti ti-plus"></i> Add New Project
                            </a>
                        </li>
                        <li class="dash-item">
                            <a class="dash-link" href="show_projects.php">
                                <i class="ti ti-list"></i> View All Projects
                            </a>
                        </li>
                        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == '1') { ?>
                            <li class="dash-item">
                                <a class="dash-link" href="project_invoices.php">
                                    <i class="ti ti-file-invoice"></i> Project Invoices
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                </li>

                <!--------------------- Renewals group ----------------------------------->

                <!--------------------- Projects added end ----------------------------------->

                <?php if ($acc_invoice_and_billing == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="#" class="dash-link">
                            <span class="dash-micon"><i class="ti ti-align-box-center-middle-filled"></i></span>
                            <span class="dash-mtext">Invoice & Billing</span>
                            <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                        </a>
                        <ul class="dash-submenu">
                            <li class="dash-item">
                                <a href="invoice_billings.php" class="dash-link">
                                    <i class="ti ti-list"></i> All Invoices
                                </a>
                            </li>
                            <li class="dash-item">
                                <a href="instant_invoice.php" class="dash-link">
                                    <i class="ti ti-bolt"></i> Instant Invoice
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php } ?>

                <?php if ($acc_invoice_and_billing == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="payment_plan.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-report-money"></i></span><span
                                class="dash-mtext">Payment Plan</span>
                        </a>
                    </li>
                <?php } ?>

                <?php if ($acc_view_client == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="#" class="dash-link">
                            <span class="dash-micon"><svg xmlns="http://www.w3.org/2000/svg"
                                    class="icon icon-tabler icon-tabler-lock-square-rounded-filled" width="24" height="24"
                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <path
                                        d="M12 2c-.218 0 -.432 .002 -.642 .005l-.616 .017l-.299 .013l-.579 .034l-.553 .046c-4.785 .464 -6.732 2.411 -7.196 7.196l-.046 .553l-.034 .579c-.005 .098 -.01 .198 -.013 .299l-.017 .616l-.004 .318l-.001 .324c0 .218 .002 .432 .005 .642l.017 .616l.013 .299l.034 .579l.046 .553c.464 4.785 2.411 6.732 7.196 7.196l.553 .046l.579 .034c.098 .005 .198 .01 .299 .013l.616 .017l.642 .005l.642 -.005l.616 -.017l.299 -.013l.579 -.034l.553 -.046c4.785 -.464 6.732 -2.411 7.196 -7.196l.046 -.553l.034 -.579c.005 -.098 .01 -.198 .013 -.299l.017 -.616l.005 -.642l-.005 -.642l-.017 -.616l-.013 -.299l-.034 -.579l-.046 -.553c-.464 -4.785 -2.411 -6.732 -7.196 -7.196l-.553 -.046l-.579 -.034a28.058 28.058 0 0 0 -.299 -.013l-.616 -.017l-.318 -.004l-.324 -.001zm0 4a3 3 0 0 1 2.995 2.824l.005 .176v1a2 2 0 0 1 1.995 1.85l.005 .15v3a2 2 0 0 1 -1.85 1.995l-.15 .005h-6a2 2 0 0 1 -1.995 -1.85l-.005 -.15v-3a2 2 0 0 1 1.85 -1.995l.15 -.005v-1a3 3 0 0 1 3 -3zm3 6h-6v3h6v-3zm-3 -4a1 1 0 0 0 -.993 .883l-.007 .117v1h2v-1a1 1 0 0 0 -1 -1z"
                                        fill="currentColor" stroke-width="0"></path>
                                </svg></span><span class="dash-mtext">Client Types</span>
                            <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                        </a>
                        <ul class="dash-submenu">
                            <li class="dash-item">
                                <a class="dash-link" href="client_types.php">
                                    <i class="ti ti-list"></i> All Types
                                </a>
                            </li>
                            <?php if ($usertype == '1') { ?>
                                <li class="dash-item">
                                    <a class="dash-link" href="client_type_manual.php">
                                        <i class="ti ti-settings"></i> Type Manual
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </li>
                <?php } ?>

                <?php if ($acc_view_products == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="memberships.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-shopping-cart"></i></span><span class="dash-mtext">View
                                Services</span>
                        </a>
                    </li>
                <?php } ?>

                <?php if ($acc_view_staff == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="users.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-user-plus"></i></span><span class="dash-mtext">Manage
                                Staff</span>
                        </a>
                    </li>
                <?php } ?>

                <?php if ($acc_view_entity == "Yes") { ?>
                    <!-- <li class="dash-item dash-hasmenu">
                        <a href="entity_form.php" class="dash-link ">
                            <span class="dash-micon"><svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-article-filled-filled" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                            <path d="M19 3a3 3 0 0 1 2.995 2.824l.005 .176v12a3 3 0 0 1 -2.824 2.995l-.176 .005h-14a3 3 0 0 1 -2.995 -2.824l-.005 -.176v-12a3 3 0 0 1 2.824 -2.995l.176 -.005h14zm-2 12h-10l-.117 .007a1 1 0 0 0 0 1.986l.117 .007h10l.117 -.007a1 1 0 0 0 0 -1.986l-.117 -.007zm0 -4h-10l-.117 .007a1 1 0 0 0 0 1.986l.117 .007h10l.117 -.007a1 1 0 0 0 0 -1.986l-.117 -.007zm0 -4h-10l-.117 .007a1 1 0 0 0 0 1.986l.117 .007h10l.117 -.007a1 1 0 0 0 0 -1.986l-.117 -.007z" stroke-width="0" fill="currentColor"></path>
                            </svg></span><span class="dash-mtext">Entity Form</span>
                        </a>
                </li> -->
                <?php } ?>


                <?php if ($acc_view_product == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="reminder_calender.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-calendar"></i></span>
                            <span class="dash-mtext">Reminders</span>
                        </a>
                    </li><?php } ?>

                <li class="dash-item dash-hasmenu">
                    <a href="#" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-refresh"></i></span>
                        <span class="dash-mtext">Product Renewals</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                    <ul class="dash-submenu">
                        <li class="dash-item">
                            <a href="ToBeRenewed.php" class="dash-link">
                                <i class="ti ti-clock"></i> To Be Renewed
                            </a>
                        </li>
                        <li class="dash-item">
                            <a href="recently_renewed.php" class="dash-link">
                                <i class="ti ti-check"></i> Recently Renewed
                            </a>
                        </li>
                        <li class="dash-item">
                            <a href="expired_renewal.php" class="dash-link">
                                <i class="ti ti-alert-circle"></i> Expired Renewals
                            </a>
                        </li>
                    </ul>
                </li>

                <?php if ($acc_estimates_and_qutations == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="quotations.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-file-invoice"></i></span><span
                                class="dash-mtext">Estimate/Quotes</span>
                        </a>
                    </li>
                <?php } ?>

                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == "1") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="manage_roles.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-user-shield"></i></span><span
                                class="dash-mtext">Roles &amp; Permissions</span>
                        </a>
                    </li>
                <?php } ?>

                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == "1") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="customizer.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-adjustments-horizontal"></i></span><span
                                class="dash-mtext">Customizer</span>
                        </a>
                    </li>
                <?php } ?>
 <!--------------------- Additional Menu ----------------------------------->
                <li class="dash-item dash-hasmenu">
                    <a href="#" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-apps"></i></span>
                        <span class="dash-mtext">Additional</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                    <ul class="dash-submenu">
                        <li class="dash-item">
                            <a href="stripe_pay_link_gen.php" class="dash-link">
                                <i class="ti ti-link"></i> Stripe Link
                            </a>
                        </li>
                        <li class="dash-item">
                            <a href="bulk_monthly_payer_gen.php" class="dash-link">
                                <i class="ti ti-calendar-dollar"></i> Bulk inv
                            </a>
                        </li>
                        <li class="dash-item">
                            <a href="ach_payment_collector.php" class="dash-link">
                                <i class="ti ti-building-bank"></i> ACH Collector
                            </a>
                        </li>
                        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == "1") { ?>
                            <li class="dash-item">
                                <a href="bulk_email.php" class="dash-link">
                                    <i class="ti ti-mail-forward"></i> Bulk Client Email
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                </li>
                <!--------------------- Additional Menu End ----------------------------------->

                <?php if ($acc_invoice_and_billing == "Yes") { ?>
                    <li class="dash-item dash-hasmenu">
                        <a href="report.php" class="dash-link ">
                            <span class="dash-micon"><i class="ti ti-file-invoice"></i></span><span
                                class="dash-mtext">Report</span>
                        </a>
                    </li>
                <?php } ?>

               


            </ul>

        </div>
    </div>
</nav>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname.split('/').pop();

        const activeLink = document.querySelector(`.dash-link[href="${currentPath}"]`);

        if (activeLink) {
            activeLink.classList.add('active');
            let parent = activeLink.closest('.dash-item.dash-hasmenu');
            if (parent) parent.classList.add('active');
        }

        // ===== See More password protection =====
        const SEE_MORE_AUTH_KEY = 'see_more_authenticated';
        const SEE_MORE_PASSWORD = '@skipper@';

        function isSeeMoreAuthenticated() {
            return sessionStorage.getItem(SEE_MORE_AUTH_KEY) === 'true';
        }

        function promptSeeMorePassword() {
            if (typeof Swal === 'undefined') {
                const pwd = window.prompt('Enter password to access See More:');
                if (pwd === SEE_MORE_PASSWORD) {
                    sessionStorage.setItem(SEE_MORE_AUTH_KEY, 'true');
                    return Promise.resolve(true);
                }
                if (pwd !== null) window.alert('Incorrect password');
                return Promise.resolve(false);
            }
            return Swal.fire({
                title: 'Password Required',
                text: 'Enter password to access See More',
                input: 'password',
                inputAttributes: {
                    autocapitalize: 'off',
                    autocorrect: 'off',
                    autocomplete: 'off',
                    placeholder: 'Password'
                },
                showCancelButton: true,
                confirmButtonText: 'Unlock',
                confirmButtonColor: '#252161',
                preConfirm: (password) => {
                    if (password !== SEE_MORE_PASSWORD) {
                        Swal.showValidationMessage('Incorrect password');
                        return false;
                    }
                    return true;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    sessionStorage.setItem(SEE_MORE_AUTH_KEY, 'true');
                    return true;
                }
                return false;
            });
        }

        const seeMoreTrigger = document.getElementById('see-more-trigger');
        const seeMoreMenu = document.getElementById('see-more-menu');

        if (seeMoreTrigger) {
            seeMoreTrigger.addEventListener('click', function(e) {
                if (isSeeMoreAuthenticated()) return;
                e.preventDefault();
                e.stopImmediatePropagation();
                promptSeeMorePassword().then((unlocked) => {
                    if (!unlocked) return;
                    const parent = seeMoreTrigger.parentElement;
                    if (!document.body.classList.contains('sidebar-collapsed')) {
                        document.querySelectorAll('.dash-item.dash-hasmenu').forEach(item => {
                            if (item !== parent) item.classList.remove('active');
                        });
                        parent.classList.add('active');
                    }
                });
            }, true); // capture phase: runs before the generic toggle handler below
        }

        if (seeMoreMenu) {
            // Block the collapsed-sidebar hover popover from leaking submenu items
            seeMoreMenu.addEventListener('mouseenter', function(e) {
                if (!document.body.classList.contains('sidebar-collapsed')) return;
                if (isSeeMoreAuthenticated()) return;
                e.stopImmediatePropagation();
            }, true);
        }
        // ===== /See More password protection =====

        const menuToggles = document.querySelectorAll('.dash-item.dash-hasmenu > .dash-link');
        menuToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                const parent = this.parentElement;
                const submenu = parent.querySelector('.dash-submenu');

                if (submenu) {
                    e.preventDefault();
                    if (!document.body.classList.contains('sidebar-collapsed')) {
                        document.querySelectorAll('.dash-item.dash-hasmenu').forEach(item => {
                            if (item !== parent) item.classList.remove('active');
                        });
                        parent.classList.toggle('active');
                    }
                }
            });
        });

        const toggleBtn = document.getElementById('sidebar-toggle');
        const toggleIcon = document.getElementById('toggle-icon');
        const body = document.body;
        const SIDEBAR_STATE_KEY = 'sidebar_collapsed_state';

        function setSidebarState(collapsed) {
            if (collapsed) {
                body.classList.add('sidebar-collapsed');
                if (toggleIcon) toggleIcon.classList.replace('ti-chevron-left', 'ti-chevron-right');
            } else {
                body.classList.remove('sidebar-collapsed');
                if (toggleIcon) toggleIcon.classList.replace('ti-chevron-right', 'ti-chevron-left');
            }
        }

        const savedState = localStorage.getItem(SIDEBAR_STATE_KEY);
        if (savedState === 'true') {
            setSidebarState(true);
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const isCollapsed = !body.classList.contains('sidebar-collapsed');
                setSidebarState(isCollapsed);
                localStorage.setItem(SIDEBAR_STATE_KEY, isCollapsed);
            });
        }

        let activePopover = null;
        let popoverTimeout = null;

        const dashItems = document.querySelectorAll('.dash-item');
        dashItems.forEach(item => {
            item.addEventListener('mouseenter', function() {
                if (!body.classList.contains('sidebar-collapsed')) return;

                if (popoverTimeout) {
                    clearTimeout(popoverTimeout);
                    popoverTimeout = null;
                }

                if (activePopover && activePopover.dataset.targetId === item.innerHTML) {
                    return;
                }

                if (activePopover) {
                    activePopover.remove();
                    activePopover = null;
                }

                const rect = item.getBoundingClientRect();
                const submenu = item.querySelector('.dash-submenu');

                const mtext = item.querySelector('.dash-mtext');
                const linkText = mtext ? mtext.textContent.trim() : '';

                const popover = document.createElement('div');
                popover.className = 'sidebar-popover';
                popover.style.top = (rect.top - 5) + 'px';
                popover.style.display = 'block';
                popover.dataset.targetId = item.innerHTML;

                if (submenu) {
                    const header = document.createElement('div');
                    header.className = 'popover-header';
                    header.textContent = linkText;
                    popover.appendChild(header);

                    const links = submenu.querySelectorAll('.dash-link');
                    links.forEach(l => {
                        const clonedLink = l.cloneNode(true);
                        clonedLink.removeAttribute('style');
                        clonedLink.classList.remove('active');

                        const icons = clonedLink.querySelectorAll('i, span, svg');
                        icons.forEach(i => i.style.display = 'inline-block');

                        clonedLink.addEventListener('click', function(e) {
                            if (l.getAttribute('href') !== '#' && !l.getAttribute('onclick')) {
                                return;
                            }

                            e.preventDefault();
                            l.click();
                        });

                        popover.appendChild(clonedLink);
                    });
                } else {
                    popover.innerHTML = `<div class="popover-header" style="border:none; padding-bottom:12px; border-radius:4px;">${linkText}</div>`;
                }

                document.body.appendChild(popover);
                activePopover = popover;


                popover.addEventListener('mouseenter', function() {
                    if (popoverTimeout) clearTimeout(popoverTimeout);
                });


                popover.addEventListener('mouseleave', function() {
                    popoverTimeout = setTimeout(() => {
                        if (activePopover) {
                            activePopover.remove();
                            activePopover = null;
                        }
                    }, 150);
                });
            });


            item.addEventListener('mouseleave', function(e) {
                if (!body.classList.contains('sidebar-collapsed')) return;

                popoverTimeout = setTimeout(() => {
                    if (activePopover) {
                        activePopover.remove();
                        activePopover = null;
                    }
                }, 150);
            });
        });

        window.addEventListener('scroll', function() {
            if (activePopover) {
                activePopover.remove();
                activePopover = null;
            }
        }, true);
    });

    const sidebar = document.querySelector('.dash-sidebar');
    if (sidebar) {
        sidebar.style.overflowY = 'auto';
    }
</script>