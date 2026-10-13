/* Attendance page: manual sync, manual punch, auto-poll toggle */
(() => {
    'use strict';

    let autoTimer = null;

    document.getElementById('btn-sync')?.addEventListener('click', function () {
        withLoading(this, async () => {
            try {
                const r = await api(BASE+'/api/sync_attendance.php', { method: 'POST', body: {} });
                // `skipped` are punches the device re-offered that we already hold —
                // including ones corrected or crossed off here, which is the point.
                toast(`Sync OK — ${r.inserted} new, ${r.skipped} already on record`, 'success');
                if (r.warning) toast(r.warning, 'error', 9000);
                setTimeout(() => location.reload(), r.warning ? 2000 : 500);
            } catch (e) { toast('Sync failed: ' + (e.data?.detail || e.message), 'error'); }
        });
    });

    document.getElementById('chk-autosync')?.addEventListener('change', function () {
        if (this.checked) {
            toast('Auto-sync every 30s enabled', 'info', 2000);
            autoTimer = setInterval(async () => {
                try {
                    const r = await api(BASE+'/api/sync_attendance.php', { method: 'POST', body: {} });
                    if (r.warning) toast(r.warning, 'error', 9000);
                    if (r.inserted > 0) {
                        toast(`Auto-sync: ${r.inserted} new punch(es)`, 'success', 2500);
                        setTimeout(() => location.reload(), 800);
                    }
                } catch (e) { console.warn('auto-sync', e); }
            }, 30000);
        } else if (autoTimer) {
            clearInterval(autoTimer); autoTimer = null;
            toast('Auto-sync disabled', 'info', 1500);
        }
    });

    // ---------- Add break (writes Break Start + Break End in one step) ----------
    const employees = (() => {
        try { return JSON.parse(document.querySelector('.content[data-employees]')?.dataset.employees || '[]'); }
        catch { return []; }
    })();
    const empOptions = employees.map(e => `<option value="${e.uid}">${e.name} (${e.uid})</option>`).join('');

    document.getElementById('btn-add-break')?.addEventListener('click', () => {
        const today = new Date().toISOString().slice(0, 10);
        const html = `
        <div class="modal-backdrop open">
          <div class="modal">
            <header><h3>Add break</h3><button class="btn ghost sm" data-x><i data-lucide="x"></i></button></header>
            <form id="ab-form" class="body">
              <p class="text-mute" style="margin-top:0">Records a <strong>Break Start</strong> and <strong>Break End</strong> for the employee. A break of 30&nbsp;min or more is a Meal Break, shorter is a Short Break.</p>
              <div class="field"><label>Employee *</label>
                <select name="device_user_id" required>${empOptions || '<option value="">No employees</option>'}</select>
              </div>
              <div class="field"><label>Date *</label><input name="date" type="date" required value="${today}"></div>
              <div class="row">
                <div class="field"><label>Break Start *</label><input name="start_time" type="time" required value="13:00"></div>
                <div class="field"><label>Break End *</label><input name="end_time" type="time" required value="13:45"></div>
              </div>
            </form>
            <footer><button class="btn" data-cancel>Cancel</button><button class="btn primary" data-save>Save break</button></footer>
          </div>
        </div>`;
        const tmp = document.createElement('div'); tmp.innerHTML = html;
        const root = tmp.firstElementChild; document.body.appendChild(root);
        if (window.lucide) lucide.createIcons({ nodes: [root] });
        const close = () => { root.classList.remove('open'); setTimeout(() => root.remove(), 150); };
        root.querySelector('[data-x]').onclick = close;
        root.querySelector('[data-cancel]').onclick = close;
        const saveBtn = root.querySelector('[data-save]');
        saveBtn.onclick = () => withLoading(saveBtn, async () => {
            const f = root.querySelector('#ab-form');
            const d = Object.fromEntries(new FormData(f));
            const body = {
                device_user_id: d.device_user_id,
                break_start: `${d.date}T${d.start_time}`,
                break_end:   `${d.date}T${d.end_time}`,
            };
            try {
                await api(BASE + '/api/add_break.php', { method: 'POST', body });
                toast('Break added', 'success'); close();
                setTimeout(() => location.reload(), 400);
            } catch (e) {
                const msg = e.data?.error === 'end_before_start' ? 'Break End must be after Break Start' : (e.data?.error || e.message);
                toast('Failed: ' + msg, 'error');
            }
        });
    });

    /**
     * Manual punch entry. `prefill` lets the cross-off flow open this already
     * pointed at the right person and day, so replacing a mis-stamped punch with
     * the real time is one step rather than a re-typed form.
     *
     * @param {{device_user_id?:string, punch_time?:string, hint?:string}} prefill
     */
    function openManualPunch(prefill = {}) {
        const uid  = prefill.device_user_id || '';
        const when = prefill.punch_time || new Date().toISOString().slice(0, 16);
        // Fall back to a free-text box when no employee list is on the page.
        const picker = empOptions
            ? `<select name="device_user_id" required>${empOptions}</select>`
            : `<input name="device_user_id" required value="${uid}">`;
        const html = `
        <div class="modal-backdrop open">
          <div class="modal">
            <header><h3>Manual Punch Entry</h3>
              <button class="btn ghost sm" data-x><i data-lucide="x"></i></button>
            </header>
            <form id="mp-form" class="body">
              ${prefill.hint ? `<p class="text-mute" style="margin-top:0">${prefill.hint}</p>` : ''}
              <div class="field"><label>Employee *</label>${picker}</div>
              <div class="field"><label>Punch Time *</label><input name="punch_time" type="datetime-local" required value="${when}"></div>
              <div class="field"><label>Type</label>
                <select name="punch_type">
                    <option value="0">Time In</option><option value="1">Time Out</option>
                    <option value="2">Break Start</option><option value="3">Break End</option>
                </select>
              </div>
            </form>
            <footer>
              <button class="btn" data-cancel>Cancel</button>
              <button class="btn primary" data-save>Save</button>
            </footer>
          </div>
        </div>`;
        const tmp = document.createElement('div'); tmp.innerHTML = html;
        const root = tmp.firstElementChild; document.body.appendChild(root);
        if (window.lucide) lucide.createIcons({ nodes: [root] });
        if (uid) { const sel = root.querySelector('[name="device_user_id"]'); if (sel) sel.value = uid; }
        const close = () => { root.classList.remove('open'); setTimeout(()=>root.remove(),150); };
        root.querySelector('[data-x]').onclick      = close;
        root.querySelector('[data-cancel]').onclick = close;
        const saveBtn = root.querySelector('[data-save]');
        saveBtn.onclick = () => withLoading(saveBtn, async () => {
            const form = root.querySelector('#mp-form');
            const data = Object.fromEntries(new FormData(form));
            try {
                await api(BASE+'/api/manual_punch.php', { method: 'POST', body: data });
                toast('Manual punch saved', 'success');
                close();
                setTimeout(() => location.reload(), 500);
            } catch (e) {
                const msg = e.data?.error === 'duplicate' ? 'A punch already exists at that exact time' : (e.data?.error || e.message);
                toast('Failed: ' + msg, 'error');
            }
        });
    }

    document.getElementById('btn-manual-punch')?.addEventListener('click', () => openManualPunch());

    // ---------- Review / edit / delete a punch (fix forgotten Break End, mis-stamps) ----------
    document.addEventListener('click', async (ev) => {
        const revBtn  = ev.target.closest('[data-review-punch]');
        const editBtn = ev.target.closest('[data-edit-punch]');
        const delBtn  = ev.target.closest('[data-del-punch]');

        if (revBtn) {
            const id     = +revBtn.dataset.reviewPunch;
            const status = revBtn.dataset.status;
            const row    = revBtn.closest('tr');
            let note = null;

            if (status === 'rejected') {
                const ok = await confirmModal({
                    title: 'Mark punch as wrong',
                    message: 'This punch stays on record, struck through, so the device cannot re-import it — but it stops counting towards hours, reports and payroll. Add the correct time afterwards with a manual punch.',
                    confirmText: 'Mark wrong', danger: true,
                });
                if (!ok) return;
                note = 'Wrong device timestamp';
            }

            // Only the request itself sits inside withLoading — the follow-up dialog
            // waits on the user, and a button left spinning that whole time reads
            // as a hung page.
            let saved = false;
            await withLoading(revBtn, async () => {
                try {
                    await api(BASE + '/api/review_punch.php', { method: 'POST', body: { id, status, note } });
                    saved = true;
                } catch (e) {
                    toast('Failed: ' + (e.data?.error || e.message), 'error');
                }
            });
            if (!saved) return;

            if (status !== 'rejected') { location.reload(); return; }

            toast('Marked wrong — excluded from all totals', 'success');
            // Straight into the correction, prefilled from the row we just rejected.
            const uid  = row?.querySelector('td:nth-child(3) code')?.textContent.trim() || '';
            const when = row?.querySelector('td:first-child')?.dataset.order || '';
            const add  = await confirmModal({
                title: 'Add the correct entry?',
                message: 'Enter the time the employee actually punched, so the record adds up.',
                confirmText: 'Add manual punch',
            });
            if (add) {
                openManualPunch({
                    device_user_id: uid,
                    punch_time: when ? when.replace(' ', 'T').slice(0, 16) : '',
                    hint: 'Replacing a punch marked wrong. Set the real time below.',
                });
            } else {
                location.reload();
            }
        } else if (editBtn) {
            const id   = +editBtn.dataset.editPunch;
            const time = editBtn.dataset.time || '';
            const html = `
            <div class="modal-backdrop open">
              <div class="modal">
                <header><h3>Edit punch</h3><button class="btn ghost sm" data-x><i data-lucide="x"></i></button></header>
                <form id="ep-form" class="body">
                  <p class="text-mute" style="margin-top:0">Correct a forgotten Break&nbsp;End or a mis-stamped time, then save.</p>
                  <div class="field"><label>Punch Time *</label><input name="punch_time" type="datetime-local" required value="${time}"></div>
                  <div class="field"><label>Type (optional)</label>
                    <select name="punch_type">
                        <option value="">Keep / auto</option>
                        <option value="0">Time In</option><option value="1">Time Out</option>
                        <option value="2">Break Start</option><option value="3">Break End</option>
                    </select>
                  </div>
                </form>
                <footer><button class="btn" data-cancel>Cancel</button><button class="btn primary" data-save>Save</button></footer>
              </div>
            </div>`;
            const tmp = document.createElement('div'); tmp.innerHTML = html;
            const root = tmp.firstElementChild; document.body.appendChild(root);
            if (window.lucide) lucide.createIcons({ nodes: [root] });
            const close = () => { root.classList.remove('open'); setTimeout(() => root.remove(), 150); };
            root.querySelector('[data-x]').onclick = close;
            root.querySelector('[data-cancel]').onclick = close;
            const saveBtn = root.querySelector('[data-save]');
            saveBtn.onclick = () => withLoading(saveBtn, async () => {
                const data = Object.fromEntries(new FormData(root.querySelector('#ep-form')));
                data.id = id;
                if (data.punch_type === '') delete data.punch_type;
                try {
                    await api(BASE + '/api/edit_punch.php', { method: 'POST', body: data });
                    toast('Punch updated', 'success'); close();
                    setTimeout(() => location.reload(), 400);
                } catch (e) { toast('Failed: ' + (e.data?.error || e.message), 'error'); }
            });
        } else if (delBtn) {
            // A punch that came from the device cannot truly be deleted: the device
            // still holds it and would hand it straight back on the next sync. Say so
            // up front, and cross it off instead — same effect on every total.
            const fromDevice = delBtn.dataset.fromDevice === '1';
            const ok = await confirmModal({
                title: fromDevice ? 'Remove punch from the record' : 'Delete punch',
                message: fromDevice
                    ? 'This punch came from the device, so it is kept as a crossed-off row — that is what stops the next sync re-importing it. It will no longer count towards hours, reports or payroll.'
                    : 'Remove this manual punch permanently? This affects break and hour calculations.',
                confirmText: fromDevice ? 'Remove' : 'Delete', danger: true,
            });
            if (!ok) return;
            await withLoading(delBtn, async () => {
                try {
                    const r = await api(BASE + '/api/delete_punch.php', { method: 'POST', body: { id: +delBtn.dataset.delPunch } });
                    toast(r.outcome === 'crossed' ? 'Punch crossed off — no longer counted' : 'Punch deleted', 'success');
                    setTimeout(() => location.reload(), 400);
                } catch (e) { toast('Failed: ' + (e.data?.error || e.message), 'error'); }
            });
        }
    });
})();
