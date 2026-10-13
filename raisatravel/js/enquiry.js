/* ==========================================================================
   Raisatravel — enquiry form controller
   Talks to /api/enquiry. Progressive enhancement: the page still exposes
   direct mailto and WhatsApp links, so the form is never the only route.
   ========================================================================== */

const form = document.querySelector('[data-enquiry]');
const statusBox = document.querySelector('[data-formstatus]');

if (form) {
  const fields = [...form.querySelectorAll('[name]')].filter((el) => el.type !== 'hidden');
  const submit = form.querySelector('button[type="submit"]');
  const stamp = form.querySelector('[name="t"]');

  /* timestamp the form was rendered, used for the bot timing check */
  if (stamp) stamp.value = String(Date.now());

  const setInvalid = (name, message) => {
    const field = form.querySelector(`[name="${name}"]`);
    if (!field) return;
    const wrap = field.closest('.field');
    if (!wrap) return;
    wrap.classList.toggle('is-invalid', Boolean(message));
    const err = wrap.querySelector('.field__err');
    if (err) err.textContent = message || '';
  };

  const clearInvalid = (name) => setInvalid(name, '');

  fields.forEach((f) => {
    f.addEventListener('blur', () => {
      if (f.value.trim() && f.getAttribute('aria-invalid') === 'true') clearInvalid(f.name);
    });
    f.addEventListener('input', () => clearInvalid(f.name));
  });

  const say = (kind, message) => {
    if (!statusBox) return;
    statusBox.className = `formstatus formstatus--${kind} is-shown`;
    statusBox.innerHTML = `
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        ${
          kind === 'ok'
            ? '<path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/>'
            : '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01" stroke-linecap="round"/>'
        }
      </svg>
      <span>${message}</span>`;
  };

  const hide = () => statusBox && statusBox.classList.remove('is-shown');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    hide();

    /* client-side pass first so people get instant feedback */
    let firstBad = null;
    fields.forEach((f) => {
      const v = f.value.trim();
      let bad = '';
      if (f.required && !v) bad = 'This field is required.';
      else if (f.type === 'email' && v && !/^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(v))
        bad = 'Please enter a valid email address.';
      else if (f.name === 'message' && v && v.length < 10)
        bad = 'Please add a little more detail (at least 10 characters).';
      if (bad && !firstBad) firstBad = f;
      setInvalid(f.name, bad);
      f.setAttribute('aria-invalid', bad ? 'true' : 'false');
    });

    if (firstBad) {
      say('err', 'Please complete the highlighted fields.');
      firstBad.focus();
      return;
    }

    const original = submit.innerHTML;
    submit.setAttribute('aria-busy', 'true');
    submit.disabled = true;
    submit.innerHTML = '<span class="spinner"></span> Sending…';

    const payload = Object.fromEntries(new FormData(form).entries());

    try {
      const res = await fetch(form.action || '/api/enquiry', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json().catch(() => ({}));

      if (!res.ok || !data.ok) {
        if (data.errors) {
          Object.entries(data.errors).forEach(([k, v]) => setInvalid(k, v));
        }
        say('err', data.error || 'Something went wrong. Please email us directly instead.');
        return;
      }

      say(
        'ok',
        `Thank you. Your enquiry has been received${data.ref ? ` (ref ${data.ref})` : ''}. Our team responds within one business day.`
      );
      form.reset();
      if (stamp) stamp.value = String(Date.now());
    } catch {
      say(
        'err',
        'We could not reach the server. Please email ceo@raisatravel.com or message us on WhatsApp.'
      );
    } finally {
      submit.removeAttribute('aria-busy');
      submit.disabled = false;
      submit.innerHTML = original;
    }
  });
}