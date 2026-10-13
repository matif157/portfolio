/* Employee page interactions */
(() => {
    'use strict';

    function openEmployeeModal(emp = null) {
        const html = `
        <div class="modal-backdrop open">
          <div class="modal">
            <header><h3>${emp ? 'Edit' : 'Add'} Employee</h3>
               <button class="btn ghost sm" data-x><i data-lucide="x"></i></button>
            </header>
            <form id="emp-form" class="body">
              <input type="hidden" name="id" value="${emp?.id || ''}">
              <div class="row" style="margin:0">
                <div class="field"><label>Device User ID *</label><input name="device_user_id" required value="${emp?.device_user_id || ''}"></div>
                <div class="field"><label>Name *</label><input name="name" required value="${emp?.name || ''}"></div>
              </div>
              <div class="row" style="margin:0">
                <div class="field"><label>Department</label><input name="department" value="${emp?.department || ''}"></div>
                <div class="field"><label>Designation</label><input name="designation" value="${emp?.designation || ''}"></div>
              </div>
              <div class="row" style="margin:0">
                <div class="field"><label>Card No.</label><input name="card_no" type="number" value="${emp?.card_no || ''}"></div>
                <div class="field"><label>Role</label>
                    <select name="role">
                        <option value="0"  ${(+emp?.role===0||!emp)?'selected':''}>User</option>
                        <option value="14" ${+emp?.role===14?'selected':''}>Admin</option>
                    </select>
                </div>
              </div>
              <div class="row" style="margin:0">
                <div class="field"><label>PIN Password</label><input name="password" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="8" placeholder="Numeric, max 8 digits"></div>
              </div>
            </form>
            <footer>
                <button class="btn" data-cancel>Cancel</button>
                <button class="btn primary" data-save>${emp ? 'Save' : 'Add to device'}</button>
            </footer>
          </div>
        </div>`;
        const tmp = document.createElement('div'); tmp.innerHTML = html;
        const root = tmp.firstElementChild; document.body.appendChild(root);
        if (window.lucide) lucide.createIcons({ nodes: [root] });
        const close = () => { root.classList.remove('open'); setTimeout(()=>root.remove(), 150); };
        root.querySelector('[data-x]').onclick = close;
        root.querySelector('[data-cancel]').onclick = close;
        const saveBtn = root.querySelector('[data-save]');
        saveBtn.onclick = () => withLoading(saveBtn, async () => {
            const form = root.querySelector('#emp-form');
            const data = Object.fromEntries(new FormData(form));
            try {
                const r = await api(BASE+'/api/add_employee_to_device.php', { method: 'POST', body: data });
                if (r.device_pushed) toast('Saved and pushed to device', 'success');
                else                  toast('Saved locally. Device push failed: ' + (r.device_error || 'unknown'), 'warn');
                close();
                setTimeout(() => location.reload(), 600);
            } catch (e) { toast('Save failed: ' + e.message, 'error'); }
        });
    }
    window.openEmployeeModal = openEmployeeModal;

    document.getElementById('btn-add-employee')?.addEventListener('click', () => openEmployeeModal());

    document.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', e => {
        const tr = e.currentTarget.closest('tr');
        openEmployeeModal({
            id: tr.dataset.id,
            device_user_id: tr.dataset.devid,
            name: tr.dataset.name,
            department: tr.dataset.dept,
            designation: tr.dataset.desg,
            card_no: tr.dataset.card,
            role: tr.dataset.role,
        });
    }));

    document.querySelectorAll('[data-delete]').forEach(b => b.addEventListener('click', async e => {
        const tr = e.currentTarget.closest('tr');
        const ok = await confirmModal({
            title: 'Delete employee',
            message: `Delete "${tr.dataset.name}" from both local DB and device?`,
            confirmText: 'Delete',
            danger: true,
        });
        if (!ok) return;
        try {
            const r = await api(BASE+'/api/delete_employee.php', { method: 'POST', body: { id: tr.dataset.id } });
            toast(r.device_pushed ? 'Deleted from device + DB' : 'Deleted from DB (device push failed)', r.device_pushed ? 'success' : 'warn');
            setTimeout(() => location.reload(), 500);
        } catch (err) { toast('Delete failed: ' + err.message, 'error'); }
    }));

    document.getElementById('btn-sync-from-device')?.addEventListener('click', function () {
        withLoading(this, async () => {
            try {
                const r = await api(BASE+'/api/get_users.php');
                toast(`Reconciled ${r.reconciled} user(s) from device`, 'success');
                setTimeout(() => location.reload(), 600);
            } catch (e) { toast('Sync failed: ' + e.message, 'error'); }
        });
    });
})();
