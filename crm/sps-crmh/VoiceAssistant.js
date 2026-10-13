/**
 * VoiceAssistant.js
 * 
 * ES6 Module implementing Web Speech API (SpeechRecognition + SpeechSynthesis)
 * for Voice Command Recognition and AI Text-to-Speech Response.
 */

export class VoiceAssistant {
    constructor(micBtnId, statusId, outputId) {
        this.micBtn = document.getElementById(micBtnId);
        this.statusEl = document.getElementById(statusId);
        this.outputEl = document.getElementById(outputId);
        this.recognition = null;
        
        this.initSpeechRecognition();
    }

    initSpeechRecognition() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        if (!SpeechRecognition) {
            if (this.statusEl) {
                this.statusEl.innerText = "Web Speech API not supported in this browser. Use text prompt.";
            }
            return;
        }

        this.recognition = new SpeechRecognition();
        this.recognition.lang = 'en-US';
        this.recognition.interimResults = false;

        this.recognition.onstart = () => {
            if (this.micBtn) {
                this.micBtn.style.transform = 'scale(1.15)';
                this.micBtn.style.background = '#ef4444';
            }
            if (this.statusEl) this.statusEl.innerText = "Listening... Speak your command now";
        };

        this.recognition.onspeechend = () => {
            this.recognition.stop();
            if (this.micBtn) {
                this.micBtn.style.transform = 'scale(1.0)';
                this.micBtn.style.background = 'var(--accent)';
            }
            if (this.statusEl) this.statusEl.innerText = "Processing voice command...";
        };

        this.recognition.onresult = async (event) => {
            const transcript = event.results[0][0].transcript;
            if (this.statusEl) this.statusEl.innerText = `Recognized: "${transcript}"`;
            await this.sendPromptToBackend(transcript);
        };

        this.recognition.onerror = () => {
            if (this.micBtn) {
                this.micBtn.style.transform = 'scale(1.0)';
                this.micBtn.style.background = 'var(--accent)';
            }
            if (this.statusEl) this.statusEl.innerText = "Error capturing speech. Try again.";
        };

        if (this.micBtn) {
            this.micBtn.addEventListener('click', () => this.startListening());
        }
    }

    startListening() {
        if (this.recognition) {
            this.recognition.start();
        }
    }

    async sendPromptToBackend(promptText) {
        try {
            const response = await fetch('AIPromptController.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ prompt: promptText })
            });

            const data = await response.json();

            if (data.success) {
                if (this.outputEl) {
                    this.outputEl.innerText = data.response_text;
                }
                this.speak(data.speech_text || data.response_text);
            }
        } catch (err) {
            console.error('AI Voice Assistant API error:', err);
            if (this.statusEl) this.statusEl.innerText = "Failed to process AI prompt.";
        }
    }

    speak(textToSpeak) {
        if ('speechSynthesis' in window && textToSpeak) {
            window.speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(textToSpeak);
            utterance.rate = 1.0;
            utterance.pitch = 1.0;
            window.speechSynthesis.speak(utterance);
        }
    }
}

// Auto Initialize if elements exist
document.addEventListener('DOMContentLoaded', () => {
    window.spsVoiceAssistant = new VoiceAssistant('micBtn', 'speechStatus', 'speechText');
});
