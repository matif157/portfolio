<?php
/**
 * AI Copilot Widget
 * Purpose: Floating developer assistant in the bottom-right corner
 * Usage  : require_once 'ai_copilot_widget.php';
 */
?>

<!-- ====================== AI COPILOT WIDGET ====================== -->
<style>
/* Floating Button */
#ai-copilot-toggle {
    position: fixed;
    bottom: 28px;
    right: 28px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: white;
    border: none;
    font-size: 26px;
    cursor: pointer;
    box-shadow: 0 8px 25px rgba(37, 99, 235, 0.45);
    z-index: 9999;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}
#ai-copilot-toggle:hover {
    transform: scale(1.08);
    box-shadow: 0 12px 30px rgba(37, 99, 235, 0.6);
}

/* Chat Panel */
#ai-copilot-panel {
    position: center;
    bottom: 100px;
    right: 28px;
    width: 300px;
    max-width: 95vw;
    height: 580px;
    background: rgba(15, 23, 42, 0.92);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 20px;
    display: none;
    flex-direction: column;
    z-index: 9998;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
    overflow: hidden;
    font-family: 'Poppins', sans-serif;
}

#ai-copilot-panel.open {
    display: flex;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Header */
.ai-header {
    padding: 16px 20px;
    background: linear-gradient(90deg, #1e293b, #0f172a);
    border-bottom: 1px solid rgba(255,255,255,0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.ai-header h4 {
    margin: 0;
    color: #e2e8f0;
    font-size: 16px;
    font-weight: 600;
}
.ai-header small {
    color: #94a3b8;
    font-size: 12px;
}

/* Messages Area */
#ai-messages {
    flex: 1;
    overflow-y: auto;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.ai-msg {
    max-width: 88%;
    padding: 12px 16px;
    border-radius: 14px;
    font-size: 14px;
    line-height: 1.5;
    word-wrap: break-word;
}
.ai-msg.user {
    align-self: flex-end;
    background: linear-gradient(135deg, #2563eb, #4f46e5);
    color: white;
    border-bottom-right-radius: 4px;
}
.ai-msg.bot {
    align-self: flex-start;
    background: rgba(255,255,255,0.07);
    color: #e2e8f0;
    border: 1px solid rgba(255,255,255,0.08);
    border-bottom-left-radius: 4px;
}

/* Code Block */
.ai-code-block {
    background: #020617;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    margin-top: 10px;
    overflow: hidden;
}
.ai-code-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: rgba(255,255,255,0.05);
    font-size: 12px;
    color: #94a3b8;
}
.ai-code-block pre {
    margin: 0;
    padding: 14px;
    overflow-x: auto;
    font-size: 13px;
    color: #e2e8f0;
    font-family: 'Fira Code', Consolas, monospace;
}

/* Typing Indicator */
.typing-indicator {
    display: none;
    align-self: flex-start;
    background: rgba(255,255,255,0.07);
    padding: 12px 18px;
    border-radius: 14px;
    color: #94a3b8;
    font-size: 13px;
}
.typing-indicator span {
    display: inline-block;
    width: 6px;
    height: 6px;
    background: #94a3b8;
    border-radius: 50%;
    margin: 0 2px;
    animation: blink 1.2s infinite;
}
.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
@keyframes blink {
    0%, 80%, 100% { opacity: 0.3; }
    40% { opacity: 1; }
}

/* Input Area */
.ai-input-area {
    padding: 14px;
    border-top: 1px solid rgba(255,255,255,0.08);
    display: flex;
    gap: 10px;
}
#ai-input {
    flex: 1;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    padding: 12px 16px;
    color: white;
    font-size: 14px;
    outline: none;
}
#ai-input:focus {
    border-color: #3b82f6;
}
#ai-send {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    border: none;
    color: white;
    width: 48px;
    border-radius: 12px;
    cursor: pointer;
    font-size: 18px;
}
</style>

<!-- Toggle Button -->
<button id="ai-copilot-toggle" title="AI Copilot">🤖</button>

<!-- Chat Panel -->
<div id="ai-copilot-panel">
    <div class="ai-header">
        <div>
            <h4>AI Copilot</h4>
            <small>CRM Context Aware</small>
        </div>
        <button onclick="toggleCopilot()" style="background:none;border:none;color:#94a3b8;font-size:20px;cursor:pointer;">×</button>
    </div>

    <div id="ai-messages">
        <div class="ai-msg bot">
            Hello! I am your CRM AI Copilot.<br>
            Ask me about clients, invoices, permissions, or any code in this system.
        </div>
    </div>

    <div class="typing-indicator" id="typing-indicator">
        <span></span><span></span><span></span>
    </div>

    <div class="ai-input-area">
        <input type="text" id="ai-input" placeholder="Ask about the CRM..." autocomplete="off">
        <button id="ai-send">➤</button>
    </div>
</div>

<script>
const panel = document.getElementById('ai-copilot-panel');
const messages = document.getElementById('ai-messages');
const input = document.getElementById('ai-input');
const sendBtn = document.getElementById('ai-send');
const typing = document.getElementById('typing-indicator');

function toggleCopilot() {
    panel.classList.toggle('open');
    if (panel.classList.contains('open')) {
        input.focus();
    }
}

document.getElementById('ai-copilot-toggle').addEventListener('click', toggleCopilot);

function addMessage(text, isUser = false, isCode = false) {
    const div = document.createElement('div');
    div.className = 'ai-msg ' + (isUser ? 'user' : 'bot');

    if (isCode) {
        div.innerHTML = `
            <div class="ai-code-block">
                <div class="ai-code-header">
                    <span>Generated Code</span>
                    <button onclick="copyCode(this)" style="background:none;border:none;color:#38bdf8;cursor:pointer;font-size:12px;">Copy</button>
                </div>
                <pre>${escapeHtml(text)}</pre>
            </div>`;
    } else {
        div.innerHTML = text;
    }

    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
}

function escapeHtml(text) {
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
}

function copyCode(btn) {
    const code = btn.closest('.ai-code-block').querySelector('pre').innerText;
    navigator.clipboard.writeText(code).then(() => {
        btn.innerText = 'Copied!';
        setTimeout(() => btn.innerText = 'Copy', 1500);
    });
}

async function sendMessage() {
    const query = input.value.trim();
    if (!query) return;

    addMessage(query, true);
    input.value = '';
    typing.style.display = 'block';
    messages.scrollTop = messages.scrollHeight;

    try {
        const res = await fetch('ai-copilot-endpoint.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query: query })
        });

        const data = await res.json();
        typing.style.display = 'none';

        if (data.success) {
            addMessage(data.reply);
            if (data.code) {
                addMessage(data.code, false, true);
            }
        } else {
            addMessage('Error: ' + (data.error || 'Something went wrong'));
        }
    } catch (err) {
        typing.style.display = 'none';
        addMessage('Network error. Please try again.');
    }
}

sendBtn.addEventListener('click', sendMessage);
input.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') sendMessage();
});
</script>