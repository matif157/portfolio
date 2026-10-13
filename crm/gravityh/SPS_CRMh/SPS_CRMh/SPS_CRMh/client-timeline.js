/**
 * client-timeline.js
 * 
 * Vanilla ES6 Module for rendering the Unified Omnichannel Client Vertical Timeline.
 */

export class ClientTimeline {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        if (this.container) {
            this.clientId = this.container.getAttribute('data-client-id');
            this.init();
        }
    }

    async init() {
        await this.refreshFeed();
    }

    async refreshFeed() {
        if (!this.clientId) return;
        
        try {
            const response = await fetch(`timeline_feed.php?client_id=${this.clientId}`);
            const data = await response.json();

            if (data.success && data.timeline) {
                this.render(data.timeline);
            } else {
                this.container.innerHTML = `<div style="padding:20px; text-align:center; color:var(--text-muted);">No communication activity recorded yet.</div>`;
            }
        } catch (err) {
            console.error('Timeline fetch error:', err);
            this.container.innerHTML = `<div style="padding:20px; color:var(--danger);">Failed to load timeline feed.</div>`;
        }
    }

    render(items) {
        if (items.length === 0) {
            this.container.innerHTML = `<div style="padding:20px; text-align:center; color:var(--text-muted);">No feed entries.</div>`;
            return;
        }

        let html = '<div style="position:relative; padding-left:30px; border-left:3px solid var(--border-color); margin-left:15px; display:flex; flex-direction:column; gap:20px;">';

        items.forEach(item => {
            let icon = '📧';
            let badgeClass = 'badge-info';

            if (item.channel === 'voice') { icon = '📞'; badgeClass = 'badge-warning'; }
            if (item.channel === 'sms') { icon = '💬'; badgeClass = 'badge-primary'; }
            if (item.channel === 'whatsapp') { icon = '🟢'; badgeClass = 'badge-success'; }

            html += `
                <div style="position:relative; background:var(--bg-card); padding:16px; border-radius:8px; border:1px solid var(--border-color); box-shadow:var(--shadow-sm);">
                    <div style="position:absolute; left:-46px; top:16px; width:30px; height:30px; border-radius:50%; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-size:14px;">
                        ${icon}
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <div>
                            <span class="badge ${badgeClass}">${item.channel.toUpperCase()}</span>
                            <strong style="margin-left:8px; font-size:14px;">${this.escapeHtml(item.subject)}</strong>
                        </div>
                        <span style="font-size:11px; color:var(--text-muted);">${item.sent_at}</span>
                    </div>

                    <div style="font-size:13px; color:var(--text-dark); white-space:pre-wrap; margin-bottom:8px;">${this.escapeHtml(item.message_body)}</div>

                    <div style="font-size:11px; color:var(--text-muted); display:flex; gap:12px;">
                        <span>Status: <strong style="color:var(--success);">${item.status.toUpperCase()}</strong></span>
                        ${item.call_duration > 0 ? `<span>Duration: <strong>${item.call_duration}s</strong></span>` : ''}
                        ${item.external_sid ? `<span>SID: <code>${item.external_sid}</code></span>` : ''}
                    </div>
                </div>
            `;
        });

        html += '</div>';
        this.container.innerHTML = html;
    }

    escapeHtml(str) {
        return (str || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }
}

// Global initialization helper
window.timelineApp = new ClientTimeline('timelineContainer');
