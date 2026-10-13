<?php
include "includes/db.php";
include "includes/auth.php";

// Get business ID from URL
$business_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch business details
$business = [];
if ($business_id) {
    $query = "SELECT ab.*, u.user_name AS creator_name 
              FROM addibusiness ab
              LEFT JOIN users u ON ab.user_id = u.user_id
              WHERE ab.addibusiness_id = $business_id";
    $result = mysqli_query($con, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $business = mysqli_fetch_assoc($result);
    }
}

// Fetch linked members for this business
$linkedMembers = [];
if ($business_id) {
    $query = "SELECT m.*, bma.created_at AS linked_since 
              FROM business_member_assoc bma
              JOIN members m ON bma.member_id = m.member_id
              WHERE bma.business_id = $business_id";
    $result = mysqli_query($con, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $linkedMembers[] = $row;
        }
    }
}

// Format dates
function formatDate($dateString) {
    if (!$dateString || $dateString == '0000-00-00') return 'N/A';
    return date('M d, Y', strtotime($dateString));
}
?>
<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
    <title>Syed Professional Services - Business Details</title>
    <!-- Fixed: Added Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <?php include "includes/links.php"; ?>
    <style>
        :root {
            --theme-primary: #6FD943;
            --theme-light: #e9f8e4;
            --theme-dark: #4a9e2c;
        }
        
        .business-header {
            background: linear-gradient(135deg, #f8f9fa 0%, var(--theme-light) 100%);
            border-radius: 10px;
            padding: 25px 30px;
            margin-bottom: 25px;
            border-left: 5px solid var(--theme-primary);
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .business-icon {
            width: 90px;
            height: 90px;
            background-color: var(--theme-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            margin-right: 25px;
        }
        
        .info-badge {
            background-color: var(--theme-light);
            color: var(--theme-dark);
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .detail-card {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            border: none;
        }
        
        .detail-card .card-header {
            background-color: #fff;
            padding: 15px 20px;
            border-bottom: 1px solid #eaeaea;
            font-weight: 600;
            color: #333;
            font-size: 1.1rem;
        }
        
        .detail-card .card-body {
            padding: 20px;
        }
        
        .detail-row {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            width: 40%;
            font-weight: 600;
            color: #555;
            font-size: 0.95rem;
        }
        
        .detail-value {
            width: 60%;
            color: #333;
            font-size: 0.95rem;
        }
        
        .address-card {
            background-color: #f9fbf8;
            border-radius: 8px;
            padding: 15px;
            border-left: 3px solid var(--theme-primary);
            margin-bottom: 15px;
        }
        
        .member-card {
            transition: all 0.3s ease;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 15px;
            border: 1px solid #eaeaea;
        }
        
        .member-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.08);
            border-color: var(--theme-primary);
        }
        
        .member-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #e9f8e4;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--theme-primary);
            font-weight: bold;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            color: #666;
            font-weight: 500;
            margin-bottom: 20px;
            transition: all 0.2s;
        }
        
        .back-link:hover {
            color: var(--theme-primary);
            text-decoration: none;
        }
        
        .section-title {
            color: var(--theme-primary);
            padding-bottom: 8px;
            border-bottom: 2px solid var(--theme-light);
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-inactive {
            background-color: #ffebee;
            color: #c62828;
        }
        
        @media (max-width: 768px) {
            .detail-row {
                flex-direction: column;
            }
            .detail-label, .detail-value {
                width: 100%;
            }
            .detail-label {
                margin-bottom: 5px;
            }
        }
    </style>
</head>

<body class="theme-3">
    <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <?php include "includes/sidebar.php"; ?>
    <?php include "includes/header.php"; ?>
    
    <!-- [ Main Content ] start -->
    <div class="dash-container">
        <div class="dash-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="page-header-title">
                                <h4 class="m-b-10">Business Details</h4>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="businesses.php">Businesses</a></li>
                                <li class="breadcrumb-item">Business Details</li>
                            </ul>
                        </div>
                        <div class="col">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <a href="javascript:history.back()" class="back-link">
                        <i class="fas fa-arrow-left me-2"></i> Back to Previous Page
                    </a>
                    
                    <?php if(empty($business)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i> Business not found!
                        </div>
                    <?php else: ?>
                        <div class="business-header d-flex align-items-center">
                            <div class="business-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <h2 class="mb-2"><?= htmlspecialchars($business['bus_name']) ?></h2>
                                    <span class="info-badge">
                                        <?= $business['status'] == 1 ? 'Active Business' : 'Inactive Business' ?>
                                    </span>
                                </div>
                                <div class="text-muted mb-2"><i class="fas fa-hashtag me-1"></i> Business ID: #<?= $business['addibusiness_id'] ?></div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="detail-card">
                                    <div class="card-header">
                                        <i class="fas fa-info-circle me-2 text-primary"></i> Business Information
                                    </div>
                                    <div class="card-body">
                                        <div class="detail-row">
                                            <div class="detail-label">President Name</div>
                                            <div class="detail-value"><?= htmlspecialchars($business['president_name']) ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Business Start Date</div>
                                            <div class="detail-value"><?= formatDate($business['bus_start_date']) ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Incorporation Date</div>
                                            <div class="detail-value"><?= formatDate($business['incorp_date']) ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Incorporation State</div>
                                            <div class="detail-value"><?= htmlspecialchars($business['incorp_state']) ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Year End Date</div>
                                            <div class="detail-value"><?= htmlspecialchars($business['date_year_end']) ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Tax Due Date</div>
                                            <div class="detail-value"><?= htmlspecialchars($business['tax_due_date']) ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Client Since</div>
                                            <div class="detail-value"><?= formatDate($business['client_since']) ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-card">
                                    <div class="card-header">
                                        <i class="fas fa-address-book me-2 text-primary"></i> Contact Information
                                    </div>
                                    <div class="card-body">
                                        <div class="detail-row">
                                            <div class="detail-label">President DOB</div>
                                            <div class="detail-value"><?= formatDate($business['president_dob']) ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Responsible Person</div>
                                            <div class="detail-value"><?= htmlspecialchars($business['res_person']) ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Other Contact 1</div>
                                            <div class="detail-value"><?= htmlspecialchars($business['other_con_1']) ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Other Contact 2</div>
                                            <div class="detail-value"><?= htmlspecialchars($business['other_con_2']) ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">EIN</div>
                                            <div class="detail-value"><?= htmlspecialchars($business['ein']) ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">SSN</div>
                                            <div class="detail-value"><?= htmlspecialchars($business['ssn']) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-6">
                                <div class="detail-card">
                                    <div class="card-header">
                                        <i class="fas fa-map-marker-alt me-2 text-primary"></i> Address Information
                                    </div>
                                    <div class="card-body">
                                        <h6 class="section-title">Business Address</h6>
                                        <div class="address-card">
                                            <div><?= htmlspecialchars($business['bus_street']) ?></div>
                                            <div><?= htmlspecialchars($business['bus_city']) ?>, <?= htmlspecialchars($business['bus_state']) ?> <?= htmlspecialchars($business['bus_zip']) ?></div>
                                        </div>
                                        
                                        <h6 class="section-title">Mailing Address</h6>
                                        <div class="address-card">
                                            <div><?= htmlspecialchars($business['mail_street']) ?></div>
                                            <div><?= htmlspecialchars($business['mail_city']) ?>, <?= htmlspecialchars($business['mail_state']) ?> <?= htmlspecialchars($business['mail_zip']) ?></div>
                                        </div>
                                        
                                        <h6 class="section-title">Principal Place of Business</h6>
                                        <div class="address-card">
                                            <div><?= htmlspecialchars($business['prm_per_street']) ?></div>
                                            <div><?= htmlspecialchars($business['prm_per_city']) ?>, <?= htmlspecialchars($business['prm_per_state']) ?> <?= htmlspecialchars($business['prm_per_zip']) ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="detail-card">
                                    <div class="card-header">
                                        <i class="fas fa-users me-2 text-primary"></i> Associated Members
                                    </div>
                                    <div class="card-body">
                                        <?php if(empty($linkedMembers)): ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-user-friends fa-2x text-muted mb-3"></i>
                                                <p class="text-muted">No members linked to this business</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="row">
                                                <?php foreach ($linkedMembers as $member): 
                                                    $initials = substr($member['name'], 0, 1) . substr($member['lname'], 0, 1);
                                                ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="member-card p-3">
                                                        <div class="d-flex align-items-center">
                                                            <div class="member-avatar me-3">
                                                                <?= $initials ?>
                                                            </div>
                                                            <div>
                                                                <h6 class="mb-1"><?= htmlspecialchars($member['name'] . ' ' . $member['lname']) ?></h6>
                                                                <p class="text-muted mb-1"><?= htmlspecialchars($member['occupation']) ?></p>
                                                                <div class="d-flex align-items-center">
                                                                    <span class="status-badge status-active">Active</span>
                                                                    <small class="text-muted ms-2">Since <?= formatDate($member['linked_since']) ?></small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="detail-card">
                                    <div class="card-header">
                                        <i class="fas fa-file-alt me-2 text-primary"></i> Additional Information
                                    </div>
                                    <div class="card-body">
                                        <div class="detail-row">
                                            <div class="detail-label">Created By</div>
                                            <div class="detail-value">
                                                <?php if(!empty($business['creator_name'])): ?>
                                                    <?= htmlspecialchars($business['creator_name']) ?>
                                                <?php else: ?>
                                                    User #<?= $business['user_id'] ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Creation Date</div>
                                            <div class="detail-value"><?= formatDate($business['bus_time']) ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Status</div>
                                            <div class="detail-value">
                                                <span class="status-badge <?= $business['status'] == 1 ? 'status-active' : 'status-inactive' ?>">
                                                    <?= $business['status'] == 1 ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>
</body>
</html>