// SPS CRM — shared JS
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('add-line')) {
    const tbody = e.target.closest('form').querySelector('.line-items tbody');
    if (!tbody) return;
    const row = tbody.querySelector('tr').cloneNode(true);
    row.querySelectorAll('input').forEach(i => {
      if (i.type === 'number') i.value = i.classList.contains('qty') ? '1' : '0';
      else if (i.name && i.name.includes('description')) i.value = '';
      else if (i.name && i.name.includes('service_id')) i.value = '';
    });
    tbody.appendChild(row);
  }
  if (e.target.classList.contains('remove-line')) {
    const tbody = e.target.closest('tbody');
    if (tbody && tbody.querySelectorAll('tr').length > 1) {
      e.target.closest('tr').remove();
    }
  }
});
