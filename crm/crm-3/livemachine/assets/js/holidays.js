/* Holidays page: add holiday / load USA calendar / record avail / delete / remove */
(() => {
    'use strict';

    const root = document.querySelector('.content[data-employees][data-holidays]');
    if (!root) return;

    const employees = JSON.parse(root.dataset.employees || '[]');
    const holidays  = JSON.parse(root.dataset.holidays || '[]');
    const year      = root.dataset.year || String(new Date().getFullYear());

    const empOptions = employees
        .map(e => `<option value="${e.id}">${e.name} (${e.uid})</option>`).join('');
    const holidayOptions = holidays
        .map(h => `<option value="${h.id}" data-paid="${h.paid}">${h.name} (${h.date})</option>`).join('');

    function modal(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = `<div class="modal-backdrop open"><div class="modal">${html}</div></div>`;
        const el = tmp.firstElementChild;
        document.body.appendChild(el);
        if (window.lucide) lucide.createIcons({ nodes: [el] });
        const close = () => { el.classList.remove('open'); setTimeout(() => el.remove(), 150); };
        el.querySelectorAll('[data-x],[data-cancel]').forEach(b => b.onclick = close);
        return { el, close };
    }

    async function post(path, body) {
        return api(BASE + path, { method: 'POST', body });
    }

    // ---------- Add holiday ----------
    document.getElementById('btn-add-holiday')?.addEventListener('click', () => {
        const { el, close } = modal(`
            <header><h3>Add holiday</h3><button class="btn ghost sm" data-x><i data-lucide="x"></i></button></header>
            <form id="hol-form" class="body">
                <div class="field"><label>Name *</label><input name="name" placeholder="e.g. Company Anniversary" required></div>
                <div class="row">
                    <div class="field"><label>Date *</label><input type="date" name="holiday_date" required></div>
                    <div class="field"><label>Paid</label>
                        <select name="is_paid"><option value="1">Paid</option><option value="0">Unpaid</option></select>
                    </div>
                </div>
                <div class="field"><label>Notes</label><input name="notes" placeholder="Optional"></div>
            </form>
            <footer><button class="btn" data-cancel>Cancel</button><button class="btn primary" data-save>Save</button></footer>`);

        const form = el.querySelector('#hol-form');
        const saveBtn = el.querySelector('[data-save]');
        saveBtn.onclick = () => withLoading(saveBtn, async () => {
            if (!form.reportValidity()) return;
            const data = Object.fromEntries(new FormData(form));
            try {
                await post('/api/add_holiday.php', data);
                toast('Holiday added', 'success'); close();
                setTimeout(() => location.reload(), 400);
            } catch (e) { toast('Failed: ' + (e.data?.error || e.message), 'error'); }
        });
    });

    // ---------- Load USA holidays ----------
    document.getElementById('btn-load-usa')?.addEventListener('click', async (ev) => {
        const btn = ev.currentTarget;
        const ok = await confirmModal({
            title: 'Load USA holidays',
            message: `Add the standard USA federal holidays for ${year}? Dates already on the calendar are skipped.`,
            confirmText: 'Load',
        });
        if (!ok) return;
        await withLoading(btn, async () => {
            try {
                const res = await post('/api/load_usa_holidays.php', { year });
                toast(`${res.added} holiday(s) added`, 'success');
                setTimeout(() => location.reload(), 400);
            } catch (e) { toast('Failed: ' + (e.data?.error || e.message), 'error'); }
        });
    });

    // ---------- Record avail ----------
    document.getElementById('btn-record-avail')?.addEventListener('click', () => {
        if (!holidays.length) { toast('Add a holiday first', 'info'); return; }
        const { el, close } = modal(`
            <header><h3>Record avail</h3><button class="btn ghost sm" data-x><i data-lucide="x"></i></button></header>
            <form id="av-form" class="body">
                <div class="field"><label>Employee *</label><select name="employee_id" required>${empOptions}</select></div>
                <div class="row">
                    <div class="field"><label>Holiday *</label><select name="holiday_id" required>${holidayOptions}</select></div>
                    <div class="field"><label>Paid</label>
                        <select name="is_paid"><option value="1">Paid</option><option value="0">Unpaid</option></select>
                    </div>
                </div>
            </form>
            <footer><button class="btn" data-cancel>Cancel</button><button class="btn primary" data-save>Save</button></footer>`);

        const form = el.querySelector('#av-form');
        const holSel = form.holiday_id, paidSel = form.is_paid;
        const syncPaid = () => {
            const opt = holSel.selectedOptions[0];
            paidSel.value = opt && opt.dataset.paid === '0' ? '0' : '1';
        };
        holSel.addEventListener('change', syncPaid); syncPaid();

        const saveBtn = el.querySelector('[data-save]');
        saveBtn.onclick = () => withLoading(saveBtn, async () => {
            const data = Object.fromEntries(new FormData(form));
            try {
                await post('/api/avail_holiday.php', data);
                toast('Avail recorded', 'success'); close();
                setTimeout(() => location.reload(), 400);
            } catch (e) { toast('Failed: ' + (e.data?.error || e.message), 'error'); }
        });
    });

    // ---------- Delete holiday / Remove avail (event delegation) ----------
    document.addEventListener('click', async (ev) => {
        const delHol    = ev.target.closest('[data-del-holiday]');
        const removeAv  = ev.target.closest('[data-remove-avail]');

        if (delHol) {
            const btn = delHol;
            const ok = await confirmModal({ title: 'Delete holiday', message: 'Remove this holiday from the calendar? Existing avail records are kept.', confirmText: 'Delete', danger: true });
            if (!ok) return;
            await withLoading(btn, async () => {
                try {
                    await post('/api/delete_holiday.php', { id: +btn.dataset.delHoliday });
                    toast('Holiday deleted', 'success');
                    setTimeout(() => location.reload(), 300);
                } catch (e) { toast('Failed: ' + (e.data?.error || e.message), 'error'); }
            });
        } else if (removeAv) {
            const btn = removeAv;
            const ok = await confirmModal({ title: 'Remove avail', message: 'Remove this employee\'s avail record for this holiday?', confirmText: 'Remove', danger: true });
            if (!ok) return;
            await withLoading(btn, async () => {
                try {
                    await post('/api/unavail_holiday.php', { id: +btn.dataset.removeAvail });
                    toast('Avail removed', 'success');
                    setTimeout(() => location.reload(), 300);
                } catch (e) { toast('Failed: ' + (e.data?.error || e.message), 'error'); }
            });
        }
    });
})();
