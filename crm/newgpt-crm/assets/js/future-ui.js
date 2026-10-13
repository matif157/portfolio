document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('table').forEach(table => {
    if (!table.closest('.table-scroll')) {
      const wrap = document.createElement('div');
      wrap.className = 'table-scroll';
      wrap.style.overflowX = 'auto';
      wrap.style.borderRadius = '14px';
      table.parentNode.insertBefore(wrap, table);
      wrap.appendChild(table);
    }
  });

  document.querySelectorAll('input, select, textarea').forEach(el => {
    el.addEventListener('focus', () => el.closest('.field, form')?.classList.add('is-focus'));
    el.addEventListener('blur', () => el.closest('.field, form')?.classList.remove('is-focus'));
  });
});
