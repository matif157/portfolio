<?php
/*  CLIENT FIELD NOTES – multi-field version
    -------------------------------------- */

if (!isset($con)) {
    die('Include this file AFTER $con is available');
}

/* ---------- 1. AJAX ENDPOINT (MUST BE FIRST) ---------- */
if (isset($_POST['action']) && $_POST['action'] === 'save_field_note') {
    header('Content-Type: application/json');

    $mid = (int) ($_POST['member_id'] ?? 0);
    $type = in_array($_POST['field_type'], [
        'email',
        'sec_email',
        'phone',
        'work_phone',
        'overseas_phone'
    ]) ? $_POST['field_type'] : '';

    $val = trim($_POST['field_value'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $uid = (int) ($_SESSION['user_id'] ?? 0);

    if (!$mid || !$type || $val === '' || $note === '') {
        echo json_encode(['success' => false]);
        exit;
    }

    $stmt = $con->prepare("
        INSERT INTO client_field_notes
        (member_id, field_type, field_value, note, created_by)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE note=VALUES(note), updated_at=NOW()
    ");
    $stmt->bind_param("isssi", $mid, $type, $val, $note, $uid);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => $ok]);
    exit;
}

/* ---------- 2. DB TABLE ---------- */
$con->query("CREATE TABLE IF NOT EXISTS `client_field_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `field_type` enum('email','sec_email','phone','work_phone','overseas_phone') NOT NULL,
  `field_value` varchar(255) NOT NULL,
  `note` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq` (`member_id`,`field_type`,`field_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

/* ---------- 3. HELPERS ---------- */
function getClientFieldNote($con, $mid, $type, $val)
{
    $stmt = $con->prepare("
        SELECT note FROM client_field_notes
        WHERE member_id=? AND field_type=? AND field_value=?
    ");
    $stmt->bind_param("iss", $mid, $type, $val);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r ? $r['note'] : '';
}
?>

<?php if (!isset($_POST['action'])): ?>
<style>
.field-note-wrapper {
    position: relative;
    display: inline-block
}

.field-note-popup {
    position: absolute;
    top: 100%;
    left: 0;
    margin-top: 6px;
    background: #fff;
    border: 1px solid #e2e6ea;
    border-radius: 6px;
    padding: 10px 12px;
    box-shadow: 0 6px 14px rgba(0, 0, 0, .12);
    z-index: 1050;
    min-width: 240px;
    display: none;
    font-size: 12px;
    color: #333
}

.field-note-popup .note-content {
    margin-bottom: 8px;
    padding: 6px 8px;
    background: linear-gradient(90deg, #1a1d3d 0%, #14396a 100%);
    border-radius: 4px;
    color: #fff;
    min-height: 40px;
    white-space: pre-wrap
}

.field-note-icon {
    color: #6FD943;
    margin-left: 4px;
    cursor: pointer
}

.field-note-popup .btn-primary {
    background: linear-gradient(90deg, #1a1d3d 0%, #14396a 100%);
    border-color: transparent;
}

.field-note-popup .btn-primary:hover {
    background: linear-gradient(90deg, #14162e 0%, #0f2c50 100%);
    border-color: transparent;
}

.hero-field-note {
    color: inherit;
}

.hero-field-note i:first-child {
    opacity: .96;
    font-size: 1rem;
    margin-right: 8px;
}

.hero-field-note .field-note-icon {
    color: #e2bd44;
    font-size: .85rem;
}

.has-note::after {
    content: '';
    width: 6px;
    height: 6px;
    background: #fff;
    border-radius: 50%;
    position: absolute;
    right: -6px;
    top: 2px
}
</style>

<script>
function cf_toggle(el) {
    const popup = el.nextElementSibling;
    document.querySelectorAll('.field-note-popup').forEach(p => p.style.display = 'none');
    popup.style.display = popup.style.display === 'block' ? 'none' : 'block';
}

function cf_edit(type, val) {
    const noteEl = el_note(type, val);
    const cur = noteEl.innerText.trim();
    Swal.fire({
        title: 'Add / Edit note',
        input: 'textarea',
        inputValue: cur === 'No note added yet.' ? '' : cur,
        showCancelButton: true
    }).then(r => {
        if (r.isConfirmed) cf_save(type, val, r.value);
    });
}

function cf_save(type, val, note) {
    fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'save_field_note',
                member_id: <?= (int) $client_id ?>,
                field_type: type,
                field_value: val,
                note: note
            })
        })
        .then(r => r.json())
        .then(j => {
            if (j.success) {
                el_note(type, val).innerText = note || 'No note added yet.';
                Swal.fire({
                    toast: true,
                    icon: 'success',
                    title: 'Saved',
                    timer: 1200,
                    showConfirmButton: false
                });
            }
        });
}

function el_note(type, val) {
    return document
        .querySelector(`[data-cf-type="${type}"][data-cf-value="${val}"]`)
        .parentElement.querySelector('.note-content');
}

document.addEventListener('click', e => {
    if (!e.target.closest('.field-note-wrapper')) {
        document.querySelectorAll('.field-note-popup').forEach(p => p.style.display = 'none');
    }
});
</script>
<?php endif; ?>

<?php
/* ---------- 4. RENDER ---------- */
function cf_render_field($con, $mid, $type, $value, $label, $mailto = false)
{
    $value = $value ?? '';
    $note = getClientFieldNote($con, $mid, $type, $value);
    $has = $note ? 'has-note' : '';
    $out = htmlspecialchars($value);
    if ($mailto)
        $out = "<a href='mailto:$out'>$out</a>";
 
    echo <<<HTML
<div class="info text-sm {$has}">
<strong>{$label}:</strong>
<span class="field-note-wrapper">
<span data-cf-type="{$type}" data-cf-value="{$value}">{$out}</span>
<i class="fas fa-sticky-note field-note-icon" onclick="cf_toggle(this)"></i>
<div class="field-note-popup">
<div class="note-content">{$note}</div>
<button class="btn btn-sm btn-primary" onclick="cf_edit('{$type}','{$value}')">Add / Edit</button>
</div>
</span>
</div>
HTML;
}

/* Compact inline variant for the hero card: icon + value + note toggle,
   with a hover tooltip on the value naming the field. */
function cf_render_hero_field($con, $mid, $type, $value, $iconClass, $title, $mailto = false)
{
    $value = $value ?? '';
    if ($value === '') return '';

    $note = getClientFieldNote($con, $mid, $type, $value);
    $has = $note ? 'has-note' : '';
    $out = htmlspecialchars($value);
    if ($mailto) {
        $out = "<a href='mailto:$out'>$out</a>";
    }
    $valAttr   = htmlspecialchars($value, ENT_QUOTES);
    $titleAttr = htmlspecialchars($title, ENT_QUOTES);
    $noteHtml  = htmlspecialchars($note);

    return <<<HTML
<span class="field-note-wrapper hero-field-note {$has}">
<i class="{$iconClass}"></i><span data-cf-type="{$type}" data-cf-value="{$valAttr}" title="{$titleAttr}">{$out}</span>
<i class="fas fa-sticky-note field-note-icon" onclick="cf_toggle(this)" title="Add / view note"></i>
<div class="field-note-popup">
<div class="note-content">{$noteHtml}</div>
<button type="button" class="btn btn-sm btn-primary" onclick="cf_edit('{$type}','{$valAttr}')">Add / Edit</button>
</div>
</span>
HTML;
}