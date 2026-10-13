<?php $pageTitle = $pageTitle ?? 'Dashboard'; ?>
<div class="topbar">
    <div class="page-title"><?= htmlspecialchars($pageTitle) ?></div>
    <div class="right">
        <span id="device-clock" class="pill clock" title="Biometric device time" hidden>
            <i data-lucide="clock"></i><span class="clock-time">--:--:--</span>
        </span>
        <span id="device-pill" class="pill">
            <span class="dot"></span><span class="label">Checking…</span>
        </span>
        <button class="btn-icon" id="tour-start-btn" type="button" title="Take a guided tour" aria-label="Take a guided tour">
            <i data-lucide="compass"></i>
        </button>
        <button class="btn-icon" id="theme-toggle" type="button" title="Toggle theme" aria-label="Toggle theme">
            <i data-lucide="sun"></i>
        </button>
    </div>
</div>
<script defer src="<?= BASE_URL ?>/assets/js/tour.js?v=<?= @filemtime(__DIR__ . '/../assets/js/tour.js') ?>"></script>
