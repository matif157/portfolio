<?php
/*  SPS ASSISTANT SETTINGS  ─  admin hub
    -------------------------------------------------------------------------
    Configure the two backends the SPS Assistant can run on: Google Gemini
    (online) and a local LLM via Ollama (offline). Control what the assistant
    is allowed to do: read data, write data, OCR, downloads. Includes
    per-backend "Test" buttons. Settings are stored in settings_marker (key/value).
    ------------------------------------------------------------------------- */
include "includes/db.php";
include "includes/auth.php";
require_once "includes/roles.php";
include "includes/ai_helper.php";

/* --- Admin gate --- */
if ($usertype != '1') {
    header("Location: index.php");
    exit;
}

$msg = '';
$msgType = '';

/* --- Save --- */
if (isset($_POST['ai_save'])) {
    $apiKey = trim($_POST['ai_api_key'] ?? '');
    if ($apiKey === '' || strpos($apiKey, '*') !== false) {
        /* keep existing key if user left it masked/blank */
        $apiKey = ai_get('ai_api_key', '');
    }
    ai_set('ai_api_key', $apiKey);
    ai_set('ai_model', trim($_POST['ai_model'] ?? 'gemini-3.6-flash'));
    ai_set('ai_enabled', (isset($_POST['ai_enabled']) && $_POST['ai_enabled'] === '1') ? '1' : '0');
    ai_set('ai_allow_read', (isset($_POST['ai_allow_read']) && $_POST['ai_allow_read'] === '1') ? '1' : '0');
    ai_set('ai_allow_write', (isset($_POST['ai_allow_write']) && $_POST['ai_allow_write'] === '1') ? '1' : '0');
    ai_set('ai_require_confirm', (isset($_POST['ai_require_confirm']) && $_POST['ai_require_confirm'] === '1') ? '1' : '0');
    ai_set('ai_allow_ocr', (isset($_POST['ai_allow_ocr']) && $_POST['ai_allow_ocr'] === '1') ? '1' : '0');
    ai_set('ai_allow_download', (isset($_POST['ai_allow_download']) && $_POST['ai_allow_download'] === '1') ? '1' : '0');
    ai_set('ai_mode', (isset($_POST['ai_mode']) && $_POST['ai_mode'] === 'offline') ? 'offline' : 'online');
    ai_set('ai_ollama_url', trim($_POST['ai_ollama_url'] ?? 'http://localhost:11434'));
    ai_set('ai_ollama_model', trim($_POST['ai_ollama_model'] ?? 'mistral'));
    $groqKey = trim($_POST['ai_groq_key'] ?? '');
    if ($groqKey === '' || strpos($groqKey, '*') !== false) {
        $groqKey = ai_get('ai_groq_key', '');
    }
    ai_set('ai_groq_key', $groqKey);
    ai_set('ai_groq_model', trim($_POST['ai_groq_model'] ?? 'llama-3.3-70b-versatile'));
    ai_set('ai_status_gemini_at', '0');
    ai_set('ai_status_groq_at', '0');
    $msg = 'SPS Assistant settings saved.';
    $msgType = 'success';
}

/* --- Test --- */
$testResult = null;
if (isset($_POST['ai_test'])) {
    $testResult = ai_mode() === 'offline' ? ollama_test() : gemini_test();
    if (!isset($testResult['success'])) { $testResult = array('success' => false, 'message' => $testResult['message'] ?? ($testResult['error'] ?? 'Unknown error')); }
}
$ollamaTest = null;
if (isset($_POST['ai_ollama_test'])) {
    $ollamaTest = ollama_test();
}
$groqTest = null;
if (isset($_POST['ai_groq_test'])) {
    $groqTest = groq_test();
    if (!isset($groqTest['success'])) { $groqTest = array('success' => false, 'message' => $groqTest['message'] ?? ($groqTest['error'] ?? 'Unknown error')); }
}

$cfg = array();
$cfg['api_key'] = ai_get('ai_api_key', '');
$cfg['model'] = ai_get('ai_model', 'gemini-3.6-flash');
$cfg['enabled'] = ai_get('ai_enabled', '1') === '1';
$cfg['allow_read'] = ai_get('ai_allow_read', '1') === '1';
$cfg['allow_write'] = ai_get('ai_allow_write', '1') === '1';
$cfg['require_confirm'] = ai_get('ai_require_confirm', '1') === '1';
$cfg['allow_ocr'] = ai_get('ai_allow_ocr', '1') === '1';
$cfg['allow_download'] = ai_get('ai_allow_download', '1') === '1';
$cfg['mode'] = ai_mode();
$cfg['ollama_url'] = ai_get('ai_ollama_url', 'http://localhost:11434');
$cfg['ollama_model'] = ai_get('ai_ollama_model', 'mistral');
$cfg['groq_key'] = ai_get('ai_groq_key', '');
$cfg['groq_model'] = ai_get('ai_groq_model', 'llama-3.3-70b-versatile');
$cfg['key_masked'] = ($cfg['api_key'] === '') ? '' : (substr($cfg['api_key'], 0, 6) . str_repeat('*', max(0, strlen($cfg['api_key']) - 10)) . substr($cfg['api_key'], -4));
$cfg['groq_masked'] = ($cfg['groq_key'] === '') ? '' : (substr($cfg['groq_key'], 0, 6) . str_repeat('*', max(0, strlen($cfg['groq_key']) - 10)) . substr($cfg['groq_key'], -4));
function ai_chk($on) { return $on ? ' checked' : ''; }
$backend = ai_backend_status();
$ollamaModels = ollama_models_list();
$haveOllamaModels = is_array($ollamaModels) && !isset($ollamaModels['error']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Syed Professional Services - SPS Assistant Settings</title>
    <?php include "includes/links.php"; ?>
    <style>
        .ai-hero {
            background: linear-gradient(120deg, #0f172a 0%, #1456b8 70%, #3b82f6 100%);
            color: #fff; border-radius: 14px; padding: 20px 26px; margin-bottom: 20px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, .3);
        }
        .ai-hero h3 { margin: 0; }
        .ai-hero p { margin: 6px 0 0; opacity: .92; }
        .switch { position: relative; display: inline-block; width: 52px; height: 28px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; inset: 0; background: #cbd5e1; transition: .3s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 22px; width: 22px; left: 3px; top: 3px; background: #fff; transition: .3s; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,.3); }
        .switch input:checked + .slider { background: #1456b8; }
        .switch input:checked + .slider:before { transform: translateX(24px); }
        .ai-toggle { display: flex; justify-content: space-between; align-items: center; padding: 10px 4px; }
    </style>
</head>

<body class="theme-3">
    <div class="loader-bg">
        <div class="loader-track"><div class="loader-fill"></div></div>
    </div>
    <?php include "includes/sidebar.php"; ?>
    <?php include "includes/header.php"; ?>

    <div class="dash-container">
        <div class="dash-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="page-header-title"><h4 class="m-b-10">SPS Assistant Settings</h4></div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">SPS Assistant Settings</li>
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

            <?php if (is_array($testResult)) { ?>
                <?php if (!empty($testResult['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <b>Test successful.</b> <?= htmlspecialchars($testResult['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <b>Test failed.</b> <?= htmlspecialchars($testResult['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            <?php } ?>

            <?php if (is_array($ollamaTest)) { ?>
                <?php if (!empty($ollamaTest['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <b>Local LLM OK.</b> <?= htmlspecialchars($ollamaTest['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <b>Local LLM unreachable.</b> <?= htmlspecialchars($ollamaTest['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            <?php } ?>

            <?php if (is_array($groqTest)) { ?>
                <?php if (!empty($groqTest['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <b>Groq OK.</b> <?= htmlspecialchars($groqTest['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <b>Groq unreachable.</b> <?= htmlspecialchars($groqTest['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            <?php } ?>

            <div class="ai-hero">
                <h3><i class="ti ti-robot"></i> SPS Assistant</h3>
                <p>Your embedded CRM copilot. <b>Online</b> mode runs on Google Gemini (cloud) with an automatic free <b>Groq</b> cloud fallback — no heavy local PC needed. <b>Offline</b> mode uses your own LLM via Ollama (this PC or another PC on your network). Ask about your CRM data, read images/PDFs (OCR), generate Excel/PDF/docs, and insert data — always with your confirmation.</p>
                <div class="mt-2">
                    <span class="badge" style="background:<?= $backend['gemini_ok'] ? '#22c55e' : '#94a3b8' ?>; color:#fff">Gemini&nbsp;<?= $backend['gemini_ok'] ? 'UP' : 'DOWN' ?></span>
                    <span class="badge ms-2" style="background:<?= $backend['groq_ok'] ? '#22c55e' : '#94a3b8' ?>; color:#fff">Groq fallback&nbsp;<?= $backend['groq_ok'] ? 'UP' : 'DOWN' ?></span>
                    <span class="badge ms-2" style="background:<?= $backend['ollama_ok'] ? '#22c55e' : '#94a3b8' ?>; color:#fff">Ollama&nbsp;<?= $backend['ollama_ok'] ? 'UP' : 'DOWN' ?></span>
                    <span class="badge ms-2 text-uppercase" style="background:<?= $cfg['mode'] === 'offline' ? '#8b5cf6' : '#1456b8' ?>; color:#fff">Current: <?= $cfg['mode'] ?></span>
                </div>
            </div>

            <form method="post">
                <div class="card">
                    <div class="card-header"><h5>Mode</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="ai-toggle">
                                    <div><b>Online — Cloud (Gemini + Groq)</b><div class="text-muted small">Runs on Google's servers — no heavy PC needed. Gemini first, Groq as automatic fallback.</div></div>
                                    <label class="switch"><input type="radio" name="ai_mode" value="online"<?= $cfg['mode'] === 'online' ? ' checked' : '' ?>><span class="slider"></span></label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="ai-toggle">
                                    <div><b>Offline — Your own LLM via Ollama</b><div class="text-muted small">Private / no internet. Server can be this PC or another PC on your network. Configure below.</div></div>
                                    <label class="switch"><input type="radio" name="ai_mode" value="offline"<?= $cfg['mode'] === 'offline' ? ' checked' : '' ?>><span class="slider"></span></label>
                                </div>
                            </div>
                        </div>
                        <div class="form-text mt-2"><?= htmlspecialchars($backend['message']) ?> Chat automatically falls back between Gemini, Groq and Ollama in that order.</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5>Online Backend — Google Gemini</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-7 mb-3">
                                <label class="form-label"><b>Gemini API Key</b></label>
                                <input type="text" class="form-control" name="ai_api_key"
                                       value="<?= htmlspecialchars($cfg['key_masked']) ?>"
                                       placeholder="Paste your Google Gemini / AI Studio API key" autocomplete="off">
                                <div class="form-text">Stored securely in the app (masked). Get one at <a href="https://aistudio.google.com/apikey" target="_blank" rel="noreferrer">aistudio.google.com/apikey</a>.</div>
                            </div>
                            <div class="col-md-5 mb-3">
                                <label class="form-label"><b>Model</b></label>
                                <input type="text" class="form-control" name="ai_model" value="<?= htmlspecialchars($cfg['model']) ?>" list="aiModels">
                                <datalist id="aiModels">
                                    <option value="gemini-3.6-flash">
                                    <option value="gemini-2.0-flash">
                                    <option value="gemini-2.0-flash-lite">
                                </datalist>
                                <div class="form-text">Vision-capable models (e.g. gemini-3.6-flash) enable image OCR.</div>
                            </div>
                        </div>
                        <button type="submit" name="ai_test" value="1" class="btn btn-outline-primary" <?= $cfg['api_key'] === '' ? 'disabled title="Add an API key first"' : '' ?>>
                            <i class="ti ti-flask"></i> Test Gemini
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>Offline Backend — Your LLM via Ollama</h5>
                        <span class="badge text-bg-<?= $haveOllamaModels ? 'success' : 'secondary' ?> ms-2"><?= $haveOllamaModels ? 'Ollama is running' : 'Ollama not detected' ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-7 mb-3">
                                <label class="form-label"><b>Server URL</b></label>
                                <input type="text" class="form-control" name="ai_ollama_url" value="<?= htmlspecialchars($cfg['ollama_url']) ?>" placeholder="http://localhost:11434">
                                <div class="form-text">Local default is <code>http://localhost:11434</code>. For a <b>low-spec PC</b>, run Ollama on another machine and point here, e.g. <code>http://192.168.1.50:11434</code> — on that PC start Ollama with <code>OLLAMA_HOST=0.0.0.0:11434 ollama serve</code>.</div>
                            </div>
                            <div class="col-md-5 mb-3">
                                <label class="form-label"><b>Model</b></label>
                                <input type="text" class="form-control" name="ai_ollama_model" value="<?= htmlspecialchars($cfg['ollama_model']) ?>" list="ollamaModels">
                                <datalist id="ollamaModels">
                                    <option value="llama3.2:1b">
                                    <option value="qwen2.5:1.5b">
                                    <option value="qwen2.5:3b">
                                    <option value="llama3.2">
                                    <option value="mistral">
                                    <option value="llama3.1:8b">
                                    <?php if ($haveOllamaModels && !empty($ollamaModels['models'])) { ?>
                                        <?php foreach ($ollamaModels['models'] as $om) { echo '<option value="' . htmlspecialchars($om['name']) . '">'; } ?>
                                    <?php } ?>
                                </datalist>
                                <div class="form-text"><?= $haveOllamaModels ? 'Installed models appear in the dropdown.' : 'Install Ollama and run "ollama pull llama3.2" (or a 1B model on low-spec PCs) to see models here.' ?></div>
                            </div>
                        </div>
                        <button type="submit" name="ai_ollama_test" value="1" class="btn btn-outline-secondary">
                            <i class="ti ti-flask"></i> Test Ollama
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>Cloud Fallback — Groq (free, optional)</h5>
                        <span class="badge text-bg-<?= $backend['groq_key_set'] ? ($backend['groq_ok'] ? 'success' : 'danger') : 'secondary' ?> ms-2"><?= $backend['groq_key_set'] ? ($backend['groq_ok'] ? 'UP' : 'DOWN') : 'Not configured' ?></span>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Groq runs Llama models on <b>their</b> servers at high speed and has a generous <b>free tier</b> — no hardware requirements. When Online mode is active and Gemini is down/quota-exhausted, the assistant automatically uses Groq. Add a key and it is fully optional. Get a free key at <a href="https://console.groq.com/keys" target="_blank" rel="noreferrer">console.groq.com/keys</a>.</p>
                        <div class="row">
                            <div class="col-md-7 mb-3">
                                <label class="form-label"><b>Groq API Key</b></label>
                                <input type="text" class="form-control" name="ai_groq_key"
                                       value="<?= htmlspecialchars($cfg['groq_masked']) ?>"
                                       placeholder="gsk_..." autocomplete="off">
                            </div>
                            <div class="col-md-5 mb-3">
                                <label class="form-label"><b>Model</b></label>
                                <input type="text" class="form-control" name="ai_groq_model" value="<?= htmlspecialchars($cfg['groq_model']) ?>" list="groqModels">
                                <datalist id="groqModels">
                                    <option value="llama-3.3-70b-versatile">
                                    <option value="llama-3.1-8b-instant">
                                    <option value="llama-3.3-70b-versatile">
                                    <option value="llama3-70b-8192">
                                    <option value="mixtral-8x7b-32768">
                                    <option value="gemma2-9b-it">
                                </datalist>
                            </div>
                        </div>
                        <button type="submit" name="ai_groq_test" value="1" class="btn btn-outline-primary" <?= $cfg['groq_key'] === '' ? 'disabled title="Add a Groq API key first"' : '' ?>>
                            <i class="ti ti-flask"></i> Test Groq
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5>Capabilities</h5></div>
                    <div class="card-body">
                        <div class="ai-toggle">
                            <div><b>Enable SPS Assistant</b><div class="text-muted small">Turn the global assistant chat on/off.</div></div>
                            <label class="switch"><input type="checkbox" name="ai_enabled" value="1"<?= ai_chk($cfg['enabled']) ?>><span class="slider"></span></label>
                        </div>
                        <hr>
                        <div class="ai-toggle">
                            <div><b>Allow reading data</b><div class="text-muted small">Let the AI query CRM data (read-only, role-gated by 'AI use' permission).</div></div>
                            <label class="switch"><input type="checkbox" name="ai_allow_read" value="1"<?= ai_chk($cfg['allow_read']) ?>><span class="slider"></span></label>
                        </div>
                        <div class="ai-toggle">
                            <div><b>Allow inserting / creating data</b><div class="text-muted small">Let the AI create clients, projects, notes, etc. Requires the 'AI write' permission.</div></div>
                            <label class="switch"><input type="checkbox" name="ai_allow_write" value="1"<?= ai_chk($cfg['allow_write']) ?>><span class="slider"></span></label>
                        </div>
                        <div class="ai-toggle">
                            <div><b>Require confirmation before inserting</b><div class="text-muted small">Show a preview ("I'm adding X to field Y — verify") and wait for your approval before any insert.</div></div>
                            <label class="switch"><input type="checkbox" name="ai_require_confirm" value="1"<?= ai_chk($cfg['require_confirm']) ?>><span class="slider"></span></label>
                        </div>
                        <div class="ai-toggle">
                            <div><b>Allow image OCR</b><div class="text-muted small">Upload an image in chat to extract fields (name, phone, etc.) and optionally create a client.</div></div>
                            <label class="switch"><input type="checkbox" name="ai_allow_ocr" value="1"<?= ai_chk($cfg['allow_ocr']) ?>><span class="slider"></span></label>
                        </div>
                        <div class="ai-toggle">
                            <div><b>Allow downloads (Excel / PDF / docs)</b><div class="text-muted small">Generate and download Excel (CSV), PDF, and doc/note files from query results.</div></div>
                            <label class="switch"><input type="checkbox" name="ai_allow_download" value="1"<?= ai_chk($cfg['allow_download']) ?>><span class="slider"></span></label>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5>Permissions</h5></div>
                    <div class="card-body">
                        <p>Who can use the SPS Assistant is controlled under <a href="manage_roles.php"><b>Roles &amp; Permissions</b></a> &rarr; <b>SPS Assistant</b>:</p>
                        <ul class="mb-0">
                            <li><b>Use AI chat &amp; read data</b> <code>ai.use</code> — lets a role open the chat and run read-only queries.</li>
                            <li><b>AI insert / create data</b> <code>ai.write</code> — lets a role create data (still passes through preview + confirm unless disabled above).</li>
                        </ul>
                        <div class="form-text mt-2">Administrators always have both capabilities.</div>
                    </div>
                </div>

                <button type="submit" name="ai_save" value="1" class="btn btn-primary">
                    <i class="ti ti-device-floppy"></i> Save SPS Assistant Settings
                </button>
            </form>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>
</body>

</html>
