<?php
/**
 * GRAVITY CRM - AI Assistant
 * Phase 1: Foundation
 * 
 * Yahan AI Assistant interface hai.
 * AI chat, voice, aur configuration options available hain.
 * Phase 4 mein actual AI model integration hoga.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('ai', 'view');

$user = current_user();
$page_title = 'AI Assistant';
$active_page = 'ai';

// Get AI settings
$ai_provider = get_setting('ai_provider', 'openai');
$ai_model = get_setting('ai_model', 'gpt-4o-mini');
$ai_confidence = get_setting('ai_confidence_threshold', '0.70');

// Get recent AI interactions
$recent_interactions = db_query('SELECT * FROM ai_interactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10', [$user['id']]);
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-robot"></i> AI Assistant</h1>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-chat-dots"></i> AI Chat
            </div>
            <div class="card-body">
                <div id="aiChatMessages" style="height:400px;overflow-y:auto;display:flex;flex-direction:column;gap:0.75rem;margin-bottom:1rem;">
                    <div class="ai-message bot" style="max-width:85%;padding:0.6rem 0.9rem;border-radius:12px;font-size:0.9rem;background:#f3f4f6;align-self:flex-start;">
                        Hello! I'm your GRAVITY AI Assistant. How can I help you today?
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <input type="text" id="aiChatInput" class="form-control-custom" placeholder="Ask me anything..." style="margin-bottom:0;" onkeydown="if(event.key==='Enter')sendAIChat()">
                    <button class="btn-primary-custom" onclick="sendAIChat()"><i class="bi bi-send"></i></button>
                    <button class="btn-secondary-custom" onclick="startVoiceAssistant()" title="Voice Assistant"><i class="bi bi-mic"></i></button>
                </div>
            </div>
        </div>
        
        <!-- Recent AI Interactions -->
        <div class="card">
            <div class="card-header">Recent AI Interactions</div>
            <div class="card-body">
                <?php if (empty($recent_interactions)): ?>
                    <p class="text-muted">No AI interactions yet.</p>
                <?php else: ?>
                    <?php foreach ($recent_interactions as $interaction): ?>
                        <div class="mb-3 p-3" style="border:1px solid #e5e7eb;border-radius:8px;">
                            <div class="d-flex justify-content-between">
                                <strong>You:</strong>
                                <small class="text-muted"><?= e(time_ago($interaction['created_at'])) ?></small>
                            </div>
                            <p class="mb-2"><?= e(truncate($interaction['user_input'] ?? '', 100)) ?></p>
                            <strong>AI:</strong>
                            <p class="mb-0"><?= e(truncate($interaction['ai_response'] ?? '', 150)) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">AI Configuration</div>
            <div class="card-body">
                <p><strong>Provider:</strong> <?= e($ai_provider) ?></p>
                <p><strong>Model:</strong> <?= e($ai_model) ?></p>
                <p><strong>Confidence Threshold:</strong> <?= e($ai_confidence) ?></p>
                <p class="text-muted">AI model configuration is managed in Settings.</p>
                <a href="settings.php" class="btn-secondary-custom btn-sm"><i class="bi bi-gear"></i> Configure</a>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">AI Capabilities</div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Search clients</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> View CRM statistics</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Check my tasks</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Find client information</li>
                    <li class="mb-2"><i class="bi bi-hourglass-split text-warning"></i> Service workflows (Phase 3)</li>
                    <li class="mb-2"><i class="bi bi-hourglass-split text-warning"></i> Email generation (Phase 4)</li>
                    <li class="mb-2"><i class="bi bi-hourglass-split text-warning"></i> Document AI (Phase 4)</li>
                    <li class="mb-2"><i class="bi bi-hourglass-split text-warning"></i> Voice assistant (Phase 4)</li>
                </ul>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">Security</div>
            <div class="card-body">
                <p class="text-muted">
                    <i class="bi bi-shield-lock"></i> AI respects your role permissions.
                    If you cannot access a module, the AI will not reveal that information.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function sendAIChat() {
    const input = document.getElementById('aiChatInput');
    const message = input.value.trim();
    if (!message) return;
    
    const messages = document.getElementById('aiChatMessages');
    messages.innerHTML += '<div class="ai-message user" style="max-width:85%;padding:0.6rem 0.9rem;border-radius:12px;font-size:0.9rem;background:var(--primary);color:#fff;align-self:flex-end;">' + escapeHtml(message) + '</div>';
    input.value = '';
    
    fetch('ai_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'message=' + encodeURIComponent(message) + '&csrf_token=' + encodeURIComponent('<?= csrf_token() ?>')
    })
    .then(r => r.json())
    .then(data => {
        messages.innerHTML += '<div class="ai-message bot" style="max-width:85%;padding:0.6rem 0.9rem;border-radius:12px;font-size:0.9rem;background:#f3f4f6;align-self:flex-start;white-space:pre-line;">' + escapeHtml(data.response || 'I could not process that request.') + '</div>';
        messages.scrollTop = messages.scrollHeight;
    })
    .catch(() => {
        messages.innerHTML += '<div class="ai-message bot" style="max-width:85%;padding:0.6rem 0.9rem;border-radius:12px;font-size:0.9rem;background:#f3f4f6;align-self:flex-start;">Sorry, I encountered an error. Please try again.</div>';
    });
}

function startVoiceAssistant() {
    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        alert('Voice recognition is not supported in this browser. Please use Chrome or Edge.');
        return;
    }
    
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const recognition = new SpeechRecognition();
    recognition.lang = 'en-US';
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;
    
    recognition.onresult = function(event) {
        const transcript = event.results[0][0].transcript;
        document.getElementById('aiChatInput').value = transcript;
        sendAIChat();
    };
    
    recognition.onerror = function(event) {
        alert('Voice recognition error: ' + event.error);
    };
    
    recognition.start();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/footer.php'; ?>