<?php
/*  CURSOR EFFECT SETTINGS  ─  admin hub
    -------------------------------------------------------------------------
    Lets an administrator turn the context-aware custom cursor on/off and
    choose its style, color and size. The chosen settings are stored in the
    settings_marker table and read on every page via includes/sidebar.php.
    ------------------------------------------------------------------------- */
include "includes/db.php";
include "includes/auth.php";

/* --- Admin gate --- */
if ($usertype != '1') {
    header("Location: index.php");
    exit;
}

$msg = '';
$msgType = '';

/* --- Current settings (with defaults) --- */
function cursor_get($con, $key, $default = '')
{
    $key = mysqli_real_escape_string($con, $key);
    $q = mysqli_query($con, "SELECT marker_value FROM settings_marker WHERE marker_key='$key'");
    if ($q && mysqli_num_rows($q) > 0) {
        $r = mysqli_fetch_assoc($q);
        return $r['marker_value'];
    }
    return $default;
}

function cursor_set($con, $key, $value)
{
    $key = mysqli_real_escape_string($con, $key);
    $value = mysqli_real_escape_string($con, $value);
    mysqli_query($con, "INSERT INTO settings_marker (marker_key, marker_value) VALUES ('$key', '$value')
                        ON DUPLICATE KEY UPDATE marker_value = VALUES(marker_value)");
}

/* --- Save --- */
if (isset($_POST['cursor_save'])) {
    $enabled = (isset($_POST['cursor_enabled']) && $_POST['cursor_enabled'] === '1') ? '1' : '0';
    $style = in_array($_POST['cursor_style'] ?? '', array('dot', 'ring', 'mix'), true) ? $_POST['cursor_style'] : 'dot';
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['cursor_color'] ?? '') ? $_POST['cursor_color'] : '#1456b8';
    $size = max(4, min(24, (int) ($_POST['cursor_size'] ?? 10)));
    cursor_set($con, 'cursor_enabled', $enabled);
    cursor_set($con, 'cursor_style', $style);
    cursor_set($con, 'cursor_color', $color);
    cursor_set($con, 'cursor_size', (string) $size);
    $msg = 'Cursor settings saved.';
    $msgType = 'success';
}

$curEnabled = cursor_get($con, 'cursor_enabled', '0') === '1';
$curStyle = cursor_get($con, 'cursor_style', 'dot');
$curColor = cursor_get($con, 'cursor_color', '#1456b8');
$curSize = (int) cursor_get($con, 'cursor_size', '10');
$swatchOn = $curEnabled ? ' checked' : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Syed Professional Services - Cursor Settings</title>
    <?php include "includes/links.php"; ?>
    <style>
        :root {
            --cur-primary: <?= htmlspecialchars($curColor) ?>;
            --cur-primary-soft: rgba(20, 86, 184, .08);
            --cur-border: #e8ecf1;
            --cur-text: #334155;
        }

        .cur-card { padding: 6px 10px 18px; }
        .cur-hero {
            background: linear-gradient(120deg, #1456b8 0%, #1d6fd8 60%, #3b82f6 100%);
            color: #fff; border-radius: 14px; padding: 20px 26px; margin-bottom: 20px;
            box-shadow: 0 8px 20px rgba(20, 86, 184, .25);
        }
        .cur-hero h3 { margin: 0; }
        .cur-hero p { margin: 6px 0 0; opacity: .92; }
        .cur-preview {
            border: 2px dashed var(--cur-border); border-radius: 12px; padding: 26px; text-align: center;
            background: #fafbfd; color: var(--cur-text);
        }
        .cur-style-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .cur-style {
            border: 2px solid var(--cur-border); border-radius: 12px; padding: 14px; text-align: center;
            cursor: pointer; transition: .15s; background: #fff;
        }
        .cur-style:hover { border-color: var(--cur-primary); }
        .cur-style.sel { border-color: var(--cur-primary); background: var(--cur-primary-soft); }
        .cur-style .dot, .cur-style .dotw, .cur-style .mix {
            margin: 0 auto 8px; border-radius: 50%;
        }
        .cur-style .dot { width: 16px; height: 16px; background: var(--cur-primary); }
        .cur-style .dotw { width: 16px; height: 16px; background: var(--cur-primary); box-shadow: 0 0 0 4px rgba(20,86,184,.18); }
        .cur-style .mix { position: relative; width: 26px; height: 26px; }
        .cur-style .mix i { position: absolute; display: block; }
        .cur-style .mix .c1 { width: 9px; height: 9px; background: var(--cur-primary); border-radius: 50%; left: 1px; top: 1px; }
        .cur-style .mix .c2 { width: 24px; height: 24px; border: 2px solid var(--cur-primary); border-radius: 50%; left: 0; top: 0; opacity: .6; }
        .switch { position: relative; display: inline-block; width: 52px; height: 28px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; inset: 0; background: #cbd5e1; transition: .3s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 22px; width: 22px; left: 3px; top: 3px; background: #fff; transition: .3s; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,.3); }
        .switch input:checked + .slider { background: var(--cur-primary); }
        .switch input:checked + .slider:before { transform: translateX(24px); }
        .cur-color-row { display: flex; align-items: center; gap: 12px; }
        input[type=color].cur-color { width: 52px; height: 38px; border: 1px solid var(--cur-border); border-radius: 8px; background: #fff; padding: 2px; cursor: pointer; }
        .cur-range { width: 100%; }
    </style>
</head>

<body class="theme-3" data-cur-preview="1">
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <?php include "includes/sidebar.php"; ?>
    <?php include "includes/header.php"; ?>

    <div class="dash-container">
        <div class="dash-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="page-header-title">
                                <h4 class="m-b-10">Cursor Settings</h4>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">Cursor Settings</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($msg !== '') { ?>
                <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php } ?>

            <div class="cur-hero">
                <h3><i class="ti ti-mouse-2"></i> Custom Cursor Effect</h3>
                <p>Turn on the context-aware cursor for the whole app and style it to match your theme.</p>
            </div>

            <form method="post" id="curForm">
                <div class="card">
                    <div class="card-header"><h5>Enable / Disable</h5></div>
                    <div class="card-body cur-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <b>Enable custom cursor</b>
                                <div class="text-muted small">Replaces the system cursor with a styled one app-wide.</div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="cursor_enabled" value="1"<?= $swatchOn ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5>Cursor Style</h5></div>
                    <div class="card-body cur-card">
                        <div class="cur-style-grid">
                            <div class="cur-style<?= $curStyle === 'dot' ? ' sel' : '' ?>" data-style="dot">
                                <div class="dot"></div><b>Dot</b>
                            </div>
                            <div class="cur-style<?= $curStyle === 'ring' ? ' sel' : '' ?>" data-style="ring">
                                <div class="dotw"></div><b>Dot + Ring</b>
                            </div>
                            <div class="cur-style<?= $curStyle === 'mix' ? ' sel' : '' ?>" data-style="mix">
                                <div class="mix"><i class="c1"></i><i class="c2"></i></div><b>Mix</b>
                            </div>
                        </div>
                        <input type="hidden" name="cursor_style" id="curStyle" value="<?= htmlspecialchars($curStyle) ?>">
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5>Color &amp; Size</h5></div>
                    <div class="card-body cur-card">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label"><b>Color</b></label>
                                <div class="cur-color-row">
                                    <input type="color" class="cur-color" name="cursor_color" id="curColor" value="<?= htmlspecialchars($curColor) ?>">
                                    <code id="curColorHex"><?= htmlspecialchars($curColor) ?></code>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><b>Size &mdash; <span id="curSizeVal"><?= (int) $curSize ?></span>px</b></label>
                                <input type="range" min="4" max="24" class="cur-range" name="cursor_size" id="curSize" value="<?= (int) $curSize ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5>Live Preview</h5></div>
                    <div class="card-body">
                        <div class="cur-preview">Move your cursor around here — the effect updates live as you change the options above, then press Save to apply it to the whole app.</div>
                    </div>
                </div>

                <button type="submit" name="cursor_save" value="1" class="btn btn-primary">
                    <i class="ti ti-device-floppy"></i> Save Cursor Settings
                </button>
            </form>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>

    <script>
        (function () {
            // Style selector
            var styleBoxes = document.querySelectorAll('.cur-style');
            var styleInput = document.getElementById('curStyle');
            var sel = document.querySelector('.cur-style.sel');
            function highlight() {
                styleBoxes.forEach(function (b) { b.classList.toggle('sel', b.dataset.style === styleInput.value); });
            }
            styleBoxes.forEach(function (b) {
                b.addEventListener('click', function () { styleInput.value = b.dataset.style; highlight(); preview(); });
            });

            // Color + size live updaters
            var colorInput = document.getElementById('curColor');
            var colorHex = document.getElementById('curColorHex');
            var sizeInput = document.getElementById('curSize');
            var sizeVal = document.getElementById('curSizeVal');
            colorInput.addEventListener('input', function () {
                colorHex.textContent = colorInput.value;
                document.documentElement.style.setProperty('--cur-primary', colorInput.value);
                preview();
            });
            sizeInput.addEventListener('input', function () {
                sizeVal.textContent = sizeInput.value;
                preview();
            });
            if (sel) highlight();

            // Live preview (local cursor that follows the form's current values)
            window.__curEnabled = function () { return document.querySelector('[name=cursor_enabled]').checked; };
            window.__curStyle = function () { return styleInput.value; };
            window.__curColor = function () { return colorInput.value; };
            window.__curSize = function () { return parseInt(sizeInput.value, 10) || 10; };
        })();

        var __curDot = null, __curRing = null, __curRaf = null, __curTs = 0, __curX = 0, __curY = 0;
        function cursorApply() {
            var en = window.__curEnabled ? window.__curEnabled() : true;
            var style = window.__curStyle ? window.__curStyle() : 'dot';
            var color = window.__curColor ? window.__curColor() : '#1456b8';
            var size = window.__curSize ? window.__curSize() : 10;

            if (!document.getElementById('curDot')) {
                __curDot = document.createElement('div'); __curDot.id = 'curDot';
                __curRing = document.createElement('div'); __curRing.id = 'curRing';
                document.body.appendChild(__curDot); document.body.appendChild(__curRing);
            }
            __curDot.style.cssText = 'position:fixed;top:0;left:0;width:' + size + 'px;height:' + size + 'px;margin:-' + (size / 2) + 'px 0 0 -' + (size / 2) + 'px;border-radius:50%;background:' + color + ';pointer-events:none;z-index:99999;transform:translate(0,0)';
            __curRing.style.cssText = 'position:fixed;top:0;left:0;width:' + (size * 1.8) + 'px;height:' + (size * 1.8) + 'px;margin:-' + (size * 0.9) + 'px 0 0 -' + (size * 0.9) + 'px;border-radius:50%;border:2px solid ' + color + ';pointer-events:none;z-index:99999;opacity:.55;transition:transform .12s ease-out;transform:translate(0,0)';
            __curDot.style.display = en ? 'block' : 'none';
            __curRing.style.display = en && style !== 'dot' ? 'block' : 'none';

            if (en) document.body.style.cursor = 'none'; else document.body.style.cursor = 'default';
        }
        function preview() { cursorApply(); }
        document.addEventListener('mousemove', function (e) {
            if (!__curDot) return;
            __curX = e.clientX; __curY = e.clientY;
            if (!__curRaf) {
                __curRaf = requestAnimationFrame(function () {
                    var x = __curX, y = __curY;
                    __curDot.style.transform = 'translate(' + x + 'px,' + y + 'px)';
                    setTimeout(function () {
                        if (__curRing) __curRing.style.transform = 'translate(' + x + 'px,' + y + 'px)';
                    }, 40);
                    __curRaf = null;
                });
            }
        });
        document.addEventListener('DOMContentLoaded', cursorApply);
        if (document.readyState !== 'loading') cursorApply();
    </script>
</body>

</html>
