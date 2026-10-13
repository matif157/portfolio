/* Salary Setup — inline edit hourly_rate per employee */
(() => {
    'use strict';

    document.querySelectorAll('tr[data-id]').forEach(tr => {
        const input = tr.querySelector('.rate-input');
        const btn   = tr.querySelector('[data-save]');
        if (!input || !btn) return;

        btn.addEventListener('click', () => withLoading(btn, async () => {
            const rate = parseFloat(input.value);
            if (isNaN(rate) || rate < 0) { toast('Enter a non-negative number', 'warn'); return; }
            try {
                await api(BASE + '/api/save_salary.php', {
                    method: 'POST',
                    body: { id: tr.dataset.id, hourly_rate: rate },
                });
                input.dataset.original = rate.toFixed(2);
                input.value            = rate.toFixed(2);
                toast('Hourly rate saved', 'success');
            } catch (e) {
                toast('Save failed: ' + e.message, 'error');
            }
        }));

        // Enter key inside the input = save
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); btn.click(); }
        });
    });
})();
