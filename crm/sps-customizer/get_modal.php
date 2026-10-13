<?php
// get_modals.php
require_once 'includes/db.php'; // Adjust to your database connection file
require_once 'includes/customizer.php';

$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
$modal_type = isset($_POST['modal_type']) ? $_POST['modal_type'] : '';
$current_user_type = isset($_POST['current_user_type']) ? intval($_POST['current_user_type']) : 0;
$current_user_id = isset($_POST['current_user_id']) ? intval($_POST['current_user_id']) : 0;

if (!$project_id || !$modal_type) {
    echo 'Invalid request';
    exit;
}

// Fetch project details
$project_query = "SELECT p.*, m.name AS client_name, pr.membership_name AS product_name, u.user_name AS assigned_to 
                 FROM projects p
                 JOIN members m ON p.mem_id = m.member_id
                 JOIN memberships pr ON p.prod_id = pr.membership_id
                 JOIN users u ON p.assign_to = u.user_id
                 WHERE p.order_id = '$project_id'";
$project_result = mysqli_query($con, $project_query);
$project_row = mysqli_fetch_assoc($project_result);

if (!$project_row) {
    echo 'Project not found';
    exit;
}

function getInitials($name)
{
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper($word[0]);
        }
    }
    return $initials;
}

if ($modal_type === 'complete') {
    ?>
    <div class="modal fade" id="completeModal<?= $project_row['order_id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Complete Project #<?= $project_row['order_id'] ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="project_id" value="<?= $project_row['order_id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Completion Comment</label>
                            <textarea class="form-control" name="completion_comment" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="complete_project" class="btn btn-success">Complete</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
} elseif ($modal_type === 'edit') {
    // Customizer: system-field override map for this module (label/max/required).
    $cuzOv = array();
    if (function_exists('customizer_fields_for_module')) {
        foreach (customizer_fields_for_module($con, 'projects') as $_fk => $_fd) {
            $cuzOv[$_fk] = array(
                'label' => $_fd['label'],
                'req'   => (int) $_fd['is_required'] === 1,
                'max'   => (int) $_fd['max_length'],
            );
        }
    }
    // Helper to echo an override label (fall back to given default).
    $cuzLabel = function ($key, $fallback) use ($cuzOv) {
        return htmlspecialchars(!empty($cuzOv[$key]['label']) ? $cuzOv[$key]['label'] : $fallback);
    };
    $cuzMax = function ($key, $defaultMax = '') use ($cuzOv) {
        return (!empty($cuzOv[$key]['max'])) ? ' maxlength="' . (int) $cuzOv[$key]['max'] . '"' : $defaultMax;
    };
    $cuzReq = function ($key, $defaultReq = '') use ($cuzOv) {
        return (!empty($cuzOv[$key]['req'])) ? ' required' : $defaultReq;
    };
    ?>
    <div class="modal fade" id="changeModal<?= $project_row['order_id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Project #<?= $project_row['order_id'] ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="project_id" value="<?= $project_row['order_id'] ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3" data-cpc-field="project_title">
                                <label class="form-label"><?= $cuzLabel('project_title', 'Project Title') ?></label>
                                <input type="text" class="form-control" name="project_title"<?= $cuzReq('project_title') ?> <?= $cuzMax('project_title') ?>
                                    value="<?= htmlspecialchars($project_row['project_title']) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <input type="text" class="form-control"
                                    value="<?= ucfirst($project_row['project_status']) ?>" readonly>
                            </div>
                        </div>
                        <div class="mb-3" data-cpc-field="project_description">
                            <label class="form-label"><?= $cuzLabel('project_description', 'Description') ?></label>
                            <textarea class="form-control" name="project_description"<?= $cuzReq('project_description') ?>
                                rows="3"><?= htmlspecialchars($project_row['project_description']) ?></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3" data-cpc-field="start_date">
                                <label class="form-label"><?= $cuzLabel('start_date', 'Start Date') ?></label>
                                <input type="date" class="form-control" name="start_date"<?= $cuzReq('start_date') ?>
                                    value="<?= $project_row['start_date'] ?>">
                            </div>
                            <div class="col-md-6 mb-3" data-cpc-field="exp_date">
                                <label class="form-label"><?= $cuzLabel('exp_date', 'Due Date') ?></label>
                                <input type="date" class="form-control" name="exp_date"<?= $cuzReq('exp_date') ?>
                                    value="<?= $project_row['exp_date'] ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3" data-cpc-field="assign_to">
                                <label class="form-label"><?= $cuzLabel('assign_to', 'Assignee') ?></label>
                                <select class="form-select select2" name="new_assignee"<?= $cuzReq('assign_to') ?>>                                    <option value="">Select Assignee</option>
                                    <?php
                                    $users_query = "SELECT user_id, user_name FROM users";
                                    $users_result = mysqli_query($con, $users_query);
                                    while ($user = mysqli_fetch_assoc($users_result)): ?>
                                        <option value="<?= $user['user_id'] ?>" <?= ($user['user_id'] == $project_row['assign_to']) ? 'selected' : '' ?>>
                                            <?= $user['user_name'] ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3" data-cpc-field="mem_id">
                                <label class="form-label"><?= $cuzLabel('mem_id', 'Client') ?></label>
                                <select class="form-select select2" name="new_client"<?= $cuzReq('mem_id') ?>>
                                    <option value="">Select Client</option>
                                    <?php
                                    $clients_query = "SELECT member_id, name FROM members";
                                    $clients_result = mysqli_query($con, $clients_query);
                                    while ($client = mysqli_fetch_assoc($clients_result)): ?>
                                        <option value="<?= $client['member_id'] ?>"
                                            <?= ($client['member_id'] == $project_row['mem_id']) ? 'selected' : '' ?>>
                                            <?= $client['name'] ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3" data-cpc-field="prod_id">
                                <label class="form-label"><?= $cuzLabel('prod_id', 'Product') ?></label>
                                <select class="form-select select2" name="new_product"<?= $cuzReq('prod_id') ?>>
                                    <option value="">Select Product</option>
                                    <?php
                                    $products_query = "SELECT membership_id, membership_name FROM memberships";
                                    $products_result = mysqli_query($con, $products_query);
                                    while ($product = mysqli_fetch_assoc($products_result)): ?>
                                        <option value="<?= $product['membership_id'] ?>"
                                            <?= ($product['membership_id'] == $project_row['prod_id']) ? 'selected' : '' ?>>
                                            <?= $product['membership_name'] ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <?php
                        // Customizer: render custom (EAV) fields with existing values.
                        $cuzEditValues = function_exists('customizer_record_values')
                            ? customizer_record_values($con, 'projects', (int) $project_row['order_id'])
                            : array();
                        $cuzEditBlock = function_exists('customizer_render_custom_fields_block')
                            ? customizer_render_custom_fields_block($con, 'projects', $cuzEditValues)
                            : '';
                        ?>
                        <?php if ($cuzEditBlock !== ''): ?>
                            <div class="row mt-2">
                                <?= $cuzEditBlock ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="change_assignee" class="btn btn-primary"
                            style="background-color: #6fd943 !important; border-color: #6fd943 !important;">Save
                            Changes</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
} elseif ($modal_type === 'delete') {
    ?>
    <div class="modal fade" id="deleteModal<?= $project_row['order_id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Project #<?= $project_row['order_id'] ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="project_id" value="<?= $project_row['order_id'] ?>">
                        <p>Are you sure you want to delete this project? This action cannot be undone.</p>
                        <p><strong>Title:</strong> <?= htmlspecialchars($project_row['project_title']) ?></p>
                        <p><strong>Client:</strong> <?= htmlspecialchars($project_row['client_name']) ?></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_project" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
} elseif ($modal_type === 'comments') {
    ?>
    <div class="modal fade" id="commentsModal<?= $project_row['order_id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0" style="box-shadow: 0 5px 20px rgba(0,0,0,0.15); border-radius: 10px;">
                <div class="modal-header text-white" style="background:rgba(112, 217, 67, 0.88) !important;">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-comments me-2"></i>
                        Comments for Project #<?= $project_row['order_id'] ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <?php
                    $comments_query = "SELECT pc.*, u.user_name 
                     FROM project_comments pc
                     LEFT JOIN users u ON pc.user_id = u.user_id
                     WHERE pc.project_id = '$project_id' 
                     ORDER BY pc.created_at DESC";
                    $comments_result = mysqli_query($con, $comments_query);

                    if (mysqli_num_rows($comments_result) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php while ($comment_row = mysqli_fetch_assoc($comments_result)):
                                $isCurrentUser = ($comment_row['user_id'] == $current_user_id);
                                ?>
                                <div class="list-group-item border-0 py-2"
                                    style="border-bottom:1px solid #f0f0f0!important;font-size:.9rem">
                                    <div class="row g-0 align-items-start">

                                        <!-- 1. Name -->
                                        <div class="col-3 text-break">
                                            <strong class="<?= $isCurrentUser ? 'text-primary' : 'text-dark' ?>">
                                                <?= htmlspecialchars($comment_row['user_name'] ?? 'User ' . $comment_row['user_id']) ?>
                                            </strong>
                                        </div>

                                        <!-- 2. Comment -->
                                        <div class="col-6 px-2 text-break text-dark">
                                            <?= htmlspecialchars($comment_row['comment']) ?>
                                        </div>

                                        <!-- 3. Date -->
                                        <div class="col-3 text-end text-muted" style="font-size:.75rem">
                                            <?= date('M j, g:i A', strtotime($comment_row['created_at'])) ?>
                                        </div>

                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5" style="">
                            <i class="far fa-comment-dots fa-4x mb-3" style="color: #e0e0e0;"></i>
                            <h5 class="text-muted">No comments yet</h5>
                            <p class="text-muted">Be the first to comment!</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer bg-light" style="border-radius: 0 0 10px 10px;">
                    <button type="button" class="btn btn-outline-primary rounded-pill px-4" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
} elseif ($modal_type === 'history') {

    // --- Creator (projects.user_id) + fallback to earliest CREATE log -----------
    $creator_name = 'Unknown';
    $created_at   = null;
    $creator_id   = (int) ($project_row['user_id'] ?? 0);
    if ($creator_id) {
        $cu = mysqli_fetch_assoc(mysqli_query($con, "SELECT user_name FROM users WHERE user_id = $creator_id LIMIT 1"));
        if ($cu) {
            $creator_name = $cu['user_name'];
        }
    }

    $create_log = mysqli_fetch_assoc(mysqli_query(
        $con,
        "SELECT al.created_at, u.user_name
         FROM activity_logs al
         LEFT JOIN users u ON al.user_id = u.user_id
         WHERE al.object_type = 'project' AND al.object_id = '$project_id' AND al.action = 'CREATE'
         ORDER BY al.created_at ASC LIMIT 1"
    ));
    if ($create_log) {
        $created_at = $create_log['created_at'];
        if (!empty($create_log['user_name'])) {
            $creator_name = $create_log['user_name'];
        }
    }

    // --- Full activity timeline for this project --------------------------------
    $history_result = mysqli_query(
        $con,
        "SELECT al.action, al.description, al.created_at, u.user_name
         FROM activity_logs al
         LEFT JOIN users u ON al.user_id = u.user_id
         WHERE al.object_type = 'project' AND al.object_id = '$project_id'
         ORDER BY al.created_at DESC, al.log_id DESC"
    );

    $action_meta = [
        'CREATE'  => ['#1e9e5a', 'fa-plus', 'Created'],
        'UPDATE'  => ['#252161', 'fa-pen', 'Updated'],
        'DELETE'  => ['#dc3545', 'fa-trash', 'Deleted'],
        'RESTORE' => ['#17a2b8', 'fa-rotate-left', 'Restored'],
        'ARCHIVE' => ['#6c757d', 'fa-box-archive', 'Archived'],
    ];

    // Friendly, parsed rendering of a log description
    if (!function_exists('historyFormatDescription')) {
        function historyFormatDescription($action, $desc, $projectId)
        {
            $desc = trim((string) $desc);
            $generic = ["Updated project #$projectId details/assignment", "Updated project #$projectId details"];
            if ($desc === '' || in_array($desc, $generic, true)) {
                return '<span class="hist-generic">Project details / assignment were updated</span>';
            }

            $parts = array_filter(array_map('trim', explode(';', $desc)));
            if (count($parts) <= 1 && $action === 'CREATE') {
                return '<span class="hist-plain">' . htmlspecialchars($desc) . '</span>';
            }

            $out = '<ul class="hist-changes">';
            foreach ($parts as $p) {
                if (preg_match('/^(.*?)\s+from\s+(.*?)\s+to\s+(.*)$/i', $p, $m)) {
                    $out .= '<li>'
                        . '<span class="hist-label">' . htmlspecialchars(trim($m[1])) . '</span>'
                        . '<span class="hist-old">' . htmlspecialchars(trim($m[2])) . '</span>'
                        . '<i class="fas fa-arrow-right hist-arrow"></i>'
                        . '<span class="hist-new">' . htmlspecialchars(trim($m[3])) . '</span>'
                        . '</li>';
                } elseif (preg_match('/^(.*?\bchanged)\s+to\s+(.*)$/i', $p, $m)) {
                    $out .= '<li>'
                        . '<span class="hist-label">' . htmlspecialchars(trim($m[1])) . '</span>'
                        . '<span class="hist-new">' . htmlspecialchars(trim($m[2])) . '</span>'
                        . '</li>';
                } else {
                    $out .= '<li><span class="hist-plain">' . htmlspecialchars($p) . '</span></li>';
                }
            }
            $out .= '</ul>';
            return $out;
        }
    }

    if (!function_exists('historyRelativeTime')) {
        function historyRelativeTime($datetime)
        {
            $ts = strtotime($datetime);
            if (!$ts) {
                return '';
            }
            $diff = time() - $ts;
            if ($diff < 0) {
                return date('M j, Y', $ts);
            }
            if ($diff < 60) {
                return 'just now';
            }
            if ($diff < 3600) {
                return floor($diff / 60) . ' min ago';
            }
            if ($diff < 86400) {
                return floor($diff / 3600) . ' hr ago';
            }
            if ($diff < 2592000) {
                return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
            }
            return date('M j, Y', $ts);
        }
    }

    $created_initials  = getInitials($creator_name);
    $assignee_name     = $project_row['assigned_to'] ?? 'Unassigned';
    $assignee_initials = getInitials($assignee_name);
    ?>
    <div class="modal fade" id="historyModal<?= $project_row['order_id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0" style="box-shadow:0 10px 40px rgba(37,33,97,.22);border-radius:14px;overflow:hidden;">
                <style>
                    #historyModal<?= $project_row['order_id'] ?> .hist-summary-card {
                        background: #fff; border: 1px solid #ececf5; border-radius: 10px; padding: 14px 16px; height: 100%;
                    }
                    #historyModal<?= $project_row['order_id'] ?> .hist-summary-label {
                        font-size: .68rem; letter-spacing: .05em; text-transform: uppercase; color: #9a98b5; font-weight: 600; margin-bottom: 6px;
                    }
                    #historyModal<?= $project_row['order_id'] ?> .hist-avatar {
                        display: inline-flex; align-items: center; justify-content: center;
                        width: 30px; height: 30px; border-radius: 50%; flex: 0 0 30px;
                        background: #252161; color: #fff; font-size: .72rem; font-weight: 700; letter-spacing: .02em;
                    }
                    #historyModal<?= $project_row['order_id'] ?> .hist-avatar.gold { background: #C9A227; }
                    #historyModal<?= $project_row['order_id'] ?> .hist-name { font-weight: 600; color: #252161; font-size: .92rem; }
                    #historyModal<?= $project_row['order_id'] ?> .hist-date-text { font-weight: 600; color: #34324f; font-size: .9rem; }

                    #historyModal<?= $project_row['order_id'] ?> .hist-timeline { position: relative; margin: 0; padding: 0 0 0 6px; }
                    #historyModal<?= $project_row['order_id'] ?> .hist-row { position: relative; padding: 0 0 18px 40px; }
                    #historyModal<?= $project_row['order_id'] ?> .hist-row:before {
                        content: ''; position: absolute; left: 14px; top: 28px; bottom: -4px; width: 2px; background: #e7e7f1;
                    }
                    #historyModal<?= $project_row['order_id'] ?> .hist-row:last-child:before { display: none; }
                    #historyModal<?= $project_row['order_id'] ?> .hist-dot {
                        position: absolute; left: 0; top: 2px; width: 30px; height: 30px; border-radius: 50%;
                        display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: .75rem;
                        box-shadow: 0 0 0 4px #fff;
                    }
                    #historyModal<?= $project_row['order_id'] ?> .hist-card {
                        background: #fbfbfe; border: 1px solid #ececf5; border-radius: 10px; padding: 10px 14px;
                    }
                    #historyModal<?= $project_row['order_id'] ?> .hist-action-badge {
                        font-size: .68rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase;
                        padding: 2px 9px; border-radius: 20px; color: #fff;
                    }
                    #historyModal<?= $project_row['order_id'] ?> .hist-time { font-size: .72rem; color: #9a98b5; white-space: nowrap; }
                    #historyModal<?= $project_row['order_id'] ?> .hist-actor { font-size: .78rem; color: #6b6986; margin-top: 6px; }
                    #historyModal<?= $project_row['order_id'] ?> .hist-actor i { color: #b9b7cf; }

                    #historyModal<?= $project_row['order_id'] ?> .hist-changes { list-style: none; margin: 8px 0 0; padding: 0; }
                    #historyModal<?= $project_row['order_id'] ?> .hist-changes li {
                        display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 3px 0; font-size: .86rem; color: #34324f;
                    }
                    #historyModal<?= $project_row['order_id'] ?> .hist-label { font-weight: 600; color: #252161; }
                    #historyModal<?= $project_row['order_id'] ?> .hist-old {
                        background: #f1eef0; color: #9a4b5b; text-decoration: line-through; padding: 1px 8px; border-radius: 6px;
                    }
                    #historyModal<?= $project_row['order_id'] ?> .hist-new {
                        background: #e9f6ee; color: #1e7a45; font-weight: 600; padding: 1px 8px; border-radius: 6px;
                    }
                    #historyModal<?= $project_row['order_id'] ?> .hist-arrow { color: #C9A227; font-size: .72rem; }
                    #historyModal<?= $project_row['order_id'] ?> .hist-generic { color: #6b6986; font-style: italic; font-size: .88rem; }
                    #historyModal<?= $project_row['order_id'] ?> .hist-plain { color: #34324f; font-size: .9rem; }
                </style>
                <div class="modal-header text-white" style="background:#252161 !important;border:0;">
                    <h5 class="modal-title fw-bold" style="color:#fff !important;">
                        <i class="fas fa-history me-2" style="color:#fff !important;"></i>
                        Project #<?= $project_row['order_id'] ?> &middot; History
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="background:#f6f6fb;">

                    <!-- Summary -->
                    <div class="row g-2 mb-4">
                        <div class="col-md-4">
                            <div class="hist-summary-card">
                                <div class="hist-summary-label">Created By</div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="hist-avatar"><?= htmlspecialchars($created_initials) ?></span>
                                    <span class="hist-name"><?= htmlspecialchars($creator_name) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="hist-summary-card">
                                <div class="hist-summary-label">Created On</div>
                                <div class="hist-date-text">
                                    <i class="far fa-calendar me-1" style="color:#C9A227;"></i>
                                    <?= $created_at ? date('M j, Y', strtotime($created_at)) : 'N/A' ?>
                                </div>
                                <div class="text-muted" style="font-size:.74rem;">
                                    <?= $created_at ? date('g:i A', strtotime($created_at)) : '' ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="hist-summary-card">
                                <div class="hist-summary-label">Assigned To</div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="hist-avatar gold"><?= htmlspecialchars($assignee_initials) ?></span>
                                    <span class="hist-name"><?= htmlspecialchars($assignee_name) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-3" style="color:#252161;">
                        <i class="fas fa-history me-1"></i> Activity Timeline
                    </h6>

                    <?php if ($history_result && mysqli_num_rows($history_result) > 0): ?>
                        <div class="hist-timeline">
                            <?php while ($h = mysqli_fetch_assoc($history_result)):
                                $meta  = $action_meta[$h['action']] ?? ['#252161', 'fa-circle-dot', ucfirst(strtolower($h['action']))];
                                $color = $meta[0];
                                $icon  = $meta[1];
                                $label = $meta[2];
                                $actor = $h['user_name'] ?? 'System';
                                ?>
                                <div class="hist-row">
                                    <span class="hist-dot" style="background:<?= $color ?>;">
                                        <i class="fas <?= $icon ?>"></i>
                                    </span>
                                    <div class="hist-card">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="hist-action-badge" style="background:<?= $color ?>;"><?= htmlspecialchars($label) ?></span>
                                            <span class="hist-time" title="<?= date('M j, Y g:i A', strtotime($h['created_at'])) ?>">
                                                <i class="far fa-clock me-1"></i><?= historyRelativeTime($h['created_at']) ?>
                                            </span>
                                        </div>
                                        <div class="mt-2">
                                            <?= historyFormatDescription($h['action'], $h['description'], $project_id) ?>
                                        </div>
                                        <div class="hist-actor">
                                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($actor) ?>
                                            <span class="text-muted">&middot; <?= date('M j, Y g:i A', strtotime($h['created_at'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-history fa-4x mb-3" style="color:#d8d7e8;"></i>
                            <h5 class="text-muted">No history recorded yet</h5>
                            <p class="text-muted">Updates and reassignments will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer bg-light" style="border-radius: 0 0 10px 10px;">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>