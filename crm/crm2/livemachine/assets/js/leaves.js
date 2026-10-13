/* Leaves page: add / assign / approve / reject / delete */
(() => {
    'use strict';

    const root = document.querySelector('.content[data-employees]');
    if (!root) return;

    const employees = JSON.parse(root.dataset.employees || '[]');
    const types     = JSON.parse(root.dataset.types || '{}');
    const year      = root.dataset.year || String(new Date().getFullYear());

    const empOptions = employees
        .map(e => `<option value="${e.id}">${e.name} (${e.uid})</option>`).join('');
    const typeOptions = Object.entries(types)
        .map(([k, v]) => `<option value="${k}" data-paid="${v.paid}">${v.label}</option>`).join('');

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

    // ---------- Add leave ----------
    document.getElementById('btn-add-leave')?.addEventListener('click', () => {
        const { el, close } = modal(`
            <header><h3>Add leave</h3><button class="btn ghost sm" data-x><i data-lucide="x"></i></button></header>
            <form id="lv-form" class="body">
                <div class="field"><label>Employee *</label><select name="employee_id" required>${empOptions}</select></div>
                <div class="row">
                    <div class="field"><label>Type</label><select name="leave_type">${typeOptions}</select></div>
                    <div class="field"><label>Paid</label>
                        <select name="is_paid"><option value="1">Paid</option><option value="0">Unpaid</option></select>
                    </div>
                </div>
                <div class="row">
                    <div class="field"><label>From *</label><input type="date" name="from_date" required></div>
                    <div class="field"><label>To *</label><input type="date" name="to_date" required></div>
                </div>
                <div class="field"><label>Reason</label><input name="reason" placeholder="Optional"></div>
                <div class="field"><label>Status</label>
                    <select name="status"><option value="pending">Pending</option><option value="approved">Approved</option></select>
                </div>
            </form>
            <footer><button class="btn" data-cancel>Cancel</button><button class="btn primary" data-save>Save</button></footer>`);

        const form = el.querySelector('#lv-form');
        // Keep the Paid select in step with the chosen type's default.
        const typeSel = form.leave_type, paidSel = form.is_paid;
        const syncPaid = () => {
            const opt = typeSel.selectedOptions[0];
            paidSel.value = opt && opt.dataset.paid === '0' ? '0' : '1';
        };
        typeSel.addEventListener('change', syncPaid); syncPaid();

        const saveBtn = el.querySelector('[data-save]');
        saveBtn.onclick = () => withLoading(saveBtn, async () => {
            const data = Object.fromEntries(new FormData(form));
            if (!data.to_date) data.to_date = data.from_date;
            try {
                await post('/api/save_leave.php', data);
                toast('Leave saved', 'success'); close();
                setTimeout(() => location.reload(), 400);
            } catch (e) { toast('Failed: ' + (e.data?.error || e.message), 'error'); }
        });
    });

    // ---------- Edit leave (type / paid) ----------
    function openEditLeave(id, currentType, currentPaid) {
        const { el, close } = modal(`
            <header><h3>Edit leave</h3><button class="btn ghost sm" data-x><i data-lucide="x"></i></button></header>
            <form id="lv-edit-form" class="body">
                <div class="row">
                    <div class="field"><label>Type</label><select name="leave_type">${typeOptions}</select></div>
                    <div class="field"><label>Paid</label>
                        <select name="is_paid"><option value="1">Paid</option><option value="0">Unpaid</option></select>
                    </div>
                </div>
            </form>
            <footer><button class="btn" data-cancel>Cancel</button><button class="btn primary" data-save>Save</button></footer>`);

        const form = el.querySelector('#lv-edit-form');
        form.leave_type.value = currentType;
        form.is_paid.value = currentPaid ? '1' : '0';

        const saveBtn = el.querySelector('[data-save]');
        saveBtn.onclick = () => withLoading(saveBtn, async () => {
            const data = Object.fromEntries(new FormData(form));
            data.id = id;
            try {
                await post('/api/edit_leave.php', data);
                toast('Leave updated', 'success'); close();
                setTimeout(() => location.reload(), 400);
            } catch (e) { toast('Failed: ' + (e.data?.error || e.message), 'error'); }
        });
    }

    // ---------- Assign balance ----------
    document.getElementById('btn-assign-leave')?.addEventListener('click', () => {
        const { el, close } = modal(`
            <header><h3>Assign leave balance</h3><button class="btn ghost sm" data-x><i data-lucide="x"></i></button></header>
            <form id="as-form" class="body">
                <div class="field"><label>Employee *</label><select name="employee_id" required>${empOptions}</select></div>
                <div class="row">
                    <div class="field"><label>Type</label><select name="leave_type">${typeOptions}</select></div>
                    <div class="field"><label>Year</label><input type="number" name="year" value="${year}"></div>
                    <div class="field"><label>Allocated days</label><input type="number" step="0.5" min="0" name="allocated_days" value="0"></div>
                </div>
            </form>
            <footer><button class="btn" data-cancel>Cancel</button><button class="btn primary" data-save>Save</button></footer>`);

        const saveBtn = el.querySelector('[data-save]');
        saveBtn.onclick = () => withLoading(saveBtn, async () => {
            const data = Object.fromEntries(new FormData(el.querySelector('#as-form')));
            try {
                await post('/api/assign_leave.php', data);
                toast('Balance assigned', 'success'); close();
                setTimeout(() => location.reload(), 400);
            } catch (e) { toast('Failed: ' + (e.data?.error || e.message), 'error'); }
        });
    });

    // ---------- Approve / Reject / Delete (event delegation) ----------
    document.addEventListener('click', async (ev) => {
        const approve = ev.target.closest('[data-approve]');
        const reject  = ev.target.closest('[data-reject]');
        const edit    = ev.target.closest('[data-edit-leave]');
        const del     = ev.target.closest('[data-del-leave]');

        if (edit) {
            openEditLeave(+edit.dataset.editLeave, edit.dataset.editType, edit.dataset.editPaid === '1');
        } else if (approve) {
            const btn = approve;
            await withLoading(btn, async () => {
                try {
                    await post('/api/decide_leave.php', { id: +btn.dataset.approve, status: 'approved' });
                    toast('Leave approved', 'success');
                    setTimeout(() => location.reload(), 300);
                } catch (e) { toast('Failed: ' + (e.data?.error || e.message), 'error'); }
            });
        } else if (reject) {
            const btn = reject;
            const note = prompt('Reason for rejection (optional):') ?? '';
            await withLoading(btn, async () => {
                try {
                    await post('/api/decide_leave.php', { id: +btn.dataset.reject, status: 'rejected', note });
                    toast('Leave rejected', 'info');
                    setTimeout(() => location.reload(), 300);
                } catch (e) { toast('Failed: ' + (e.data?.error || e.message), 'error'); }
            });
        } else if (del) {
            const btn = del;
            const ok = await confirmModal({ title: 'Delete leave', message: 'Remove this leave record permanently?', confirmText: 'Delete', danger: true });
            if (!ok) return;
            await withLoading(btn, async () => {
                try {
                    await post('/api/delete_leave.php', { id: +btn.dataset.delLeave });
                    toast('Leave deleted', 'success');
                    setTimeout(() => location.reload(), 300);
                } catch (e) { toast('Failed: ' + (e.data?.error || e.message), 'error'); }
            });
        }
    });
})();
