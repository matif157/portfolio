/* ===================================================================
   Dashboard: live punch feed + KPI cards + hourly chart
   =================================================================== */
(() => {
    'use strict';
    const feedBody = document.getElementById('live-feed-body');
    const kpis = {
        total:   document.querySelector('[data-kpi="total"] .card-value'),
        present: document.querySelector('[data-kpi="present"] .card-value'),
        late:    document.querySelector('[data-kpi="late"] .card-value'),
        absent:  document.querySelector('[data-kpi="absent"] .card-value'),
    };
    // Seed from server-rendered rows so the first pull doesn't re-prepend them.
    let lastSeenId = 0;
    if (feedBody) {
        feedBody.querySelectorAll('tr[data-id]').forEach(tr => {
            const id = parseInt(tr.dataset.id, 10);
            if (id > lastSeenId) lastSeenId = id;
        });
    }
    let chart;

    const DAYS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const MONS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    function formatPunch(s) {
        if (!s) return '';
        const d = new Date(String(s).replace(' ', 'T'));
        if (isNaN(d)) return String(s).slice(11, 19);
        let h = d.getHours();
        const m = String(d.getMinutes()).padStart(2, '0');
        const ap = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return `${DAYS[d.getDay()]}, ${MONS[d.getMonth()]} ${d.getDate()} · ${h}:${m} ${ap}`;
    }

    function rowHtml(r) {
        const name = r.employee_name || `User ${r.device_user_id}`;
        const source = r.source || 'device';
        const typeLabel = r.type_label || '';
        const typeClass = r.type_class || '';
        const arrival = r.display_status || '';                 // '' on non-first punches
        const arrivalCell = arrival
            ? `<span class="badge ${arrival}">${arrival}</span>` : '';
        return `
            <tr data-id="${r.id}" class="new">
                <td data-order="${escapeHtml(r.punch_time || '')}">${escapeHtml(formatPunch(r.punch_time))}</td>
                <td>${escapeHtml(name)}</td>
                <td><code>${escapeHtml(r.device_user_id)}</code></td>
                <td><span class="badge ${escapeHtml(typeClass)}">${escapeHtml(typeLabel)}</span></td>
                <td>${arrivalCell}</td>
                <td><span class="badge ${escapeHtml(source)}">${escapeHtml(source)}</span></td>
            </tr>`;
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    async function pull(syncDevice = false) {
        try {
            const url = `${BASE}/api/fetch_live_logs.php?after=${lastSeenId}` + (syncDevice ? '&sync=1' : '');
            const r = await api(url);
            if (Array.isArray(r.logs) && r.logs.length) {
                const dt = (window.jQuery && jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable('#live-feed-table'))
                    ? jQuery('#live-feed-table').DataTable() : null;

                if (dt) {
                    // ---- DataTables-aware insert (keeps pagination/sort/search working) ----
                    let added = false;
                    r.logs.slice().reverse().forEach(row => {
                        if (row.id > lastSeenId) lastSeenId = row.id;
                        if (dt.rows(`tr[data-id="${row.id}"]`).any()) return; // no duplicates
                        const tmp = document.createElement('tbody');
                        tmp.innerHTML = rowHtml(row);
                        const trNode = tmp.firstElementChild;
                        dt.row.add(trNode);
                        trNode.classList.add('new');
                        setTimeout(() => trNode.classList.remove('new'), 700);
                        added = true;
                    });
                    if (added) {
                        dt.draw(false); // re-sort (Time desc) without resetting the current page
                        // cap to 50 newest rows by id
                        const ids = dt.rows().nodes().toArray().map(n => +n.dataset.id).sort((a, b) => b - a);
                        if (ids.length > 50) {
                            ids.slice(50).forEach(id => dt.row(`tr[data-id="${id}"]`).remove());
                            dt.draw(false);
                        }
                    }
                } else if (feedBody) {
                    // ---- Fallback: plain DOM prepend ----
                    const fragment = document.createDocumentFragment();
                    r.logs.slice().reverse().forEach(row => {
                        if (row.id > lastSeenId) lastSeenId = row.id;
                        if (feedBody.querySelector(`tr[data-id="${row.id}"]`)) return;
                        const tr = document.createElement('tbody');
                        tr.innerHTML = rowHtml(row);
                        fragment.prepend(tr.firstElementChild);
                    });
                    const empty = feedBody.querySelector('.empty');
                    if (empty) empty.parentElement.remove();
                    feedBody.prepend(fragment);
                    [...feedBody.querySelectorAll('tr.new')].forEach(tr => {
                        setTimeout(() => tr.classList.remove('new'), 700);
                    });
                    while (feedBody.children.length > 50) feedBody.lastElementChild.remove();
                }
                if (r.inserted > 0) toast(`Synced ${r.inserted} new punch(es) from device`, 'success', 2500);
            }
        } catch (e) { console.warn('fetch_live_logs', e); }
    }

    async function pullStats() {
        try {
            const r = await api(BASE+'/api/dashboard_stats.php');
            if (kpis.total)   kpis.total.textContent   = r.counts.total_employees;
            if (kpis.present) kpis.present.textContent = r.counts.present;
            if (kpis.late)    kpis.late.textContent    = r.counts.late;
            if (kpis.absent)  kpis.absent.textContent  = r.counts.absent;
            if (chart) {
                chart.data.datasets[0].data = r.hourly;
                chart.update('none');
            }
        } catch (e) { console.warn('dashboard_stats', e); }
    }

    function initChart() {
        const el = document.getElementById('hourly-chart');
        if (!el || !window.Chart) return;
        chart = new Chart(el, {
            type: 'line',
            data: {
                labels: Array.from({length: 24}, (_, h) => h.toString().padStart(2,'0') + ':00'),
                datasets: [{
                    label: 'Punches today',
                    data: Array(24).fill(0),
                    fill: true,
                    backgroundColor: 'rgba(79,70,229,.10)',
                    borderColor: '#4f46e5',
                    borderWidth: 2,
                    tension: .35,
                    pointRadius: 3,
                    pointBackgroundColor: '#4f46e5',
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                    x: { grid: { display: false } },
                },
            },
        });
    }

    document.getElementById('btn-sync-now')?.addEventListener('click', function () {
        withLoading(this, async () => {
            try {
                const r = await api(BASE+'/api/sync_attendance.php', { method: 'POST', body: {} });
                toast(`Sync OK — ${r.inserted} new of ${r.fetched} fetched`, 'success');
                pullStats(); pull(false);
            } catch (e) {
                toast('Sync failed: ' + e.message, 'error');
            }
        });
    });

    // First paint, then poll
    pull(false); pullStats(); initChart();
    setInterval(() => pull(false),    7000);   // live feed every 7s
    setInterval(() => pullStats(),   15000);   // KPI refresh every 15s
    // Auto pull from device every 30s (configurable later)
    setInterval(() => pull(true),    30000);
})();
