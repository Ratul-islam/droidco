// Minimal progressive enhancement for contact form
(function () {
  const form = document.getElementById('contact');
  const btn = document.getElementById('submitBtn');
  const alertBox = document.getElementById('formAlert');
  if (!form) return;


  console.log(form)
  function setAlert(kind, msg) {
    alertBox.className = 'mx-auto mt-6 max-w-3xl rounded-lg border px-4 py-3 text-sm ' +
      (kind === 'ok'
        ? 'border-teal-500/40 bg-teal-500/10 text-teal-100'
        : 'border-rose-500/40 bg-rose-500/10 text-rose-100');
    alertBox.textContent = msg;
    alertBox.classList.remove('hidden');
  }

  function setLoading(loading) {
    const spinner = btn.querySelector('svg');
    if (loading) {
      btn.disabled = true;
      spinner.classList.remove('hidden');
    } else {
      btn.disabled = false;
      spinner.classList.add('hidden');
    }
  }

  // Show success if redirected with ?sent=1
  if (new URLSearchParams(location.search).get('sent') === '1') {
    setAlert('ok', 'Thanks! Your message has been sent. We’ll get back to you shortly.');
    // Clean URL
    history.replaceState({}, '', location.pathname + '#contact');
  }

  form.addEventListener('submit', async (e) => {
    // Use AJAX; if it fails, the browser will still submit traditionally if we don't preventDefault early.
    e.preventDefault();
    alertBox.classList.add('hidden');

    // Basic client-side checks
    const email = form.email.value.trim();
    const name = form.name.value.trim();
    const message = form.message.value.trim();
    const consent = form.consent.checked;

    if (!name || !email || !message || !consent) {
      setAlert('err', 'Please complete all required fields and consent to be contacted.');
      return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      setAlert('err', 'Please enter a valid email address.');
      return;
    }

    const data = new FormData(form);
    setLoading(true);
    try {
      const res = await fetch(form.action, {
        method: 'POST',
        body: data,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const ct = res.headers.get('content-type') || '';
      if (!res.ok) throw new Error('Server error');
      const payload = ct.includes('application/json') ? await res.json() : {};
      if (payload.success) {
        setAlert('ok', payload.message || 'Thanks! Your message has been sent.');
        form.reset();
      } else {
        throw new Error(payload.message || 'Failed to send. Please try again later.');
      }
    } catch (err) {
      setAlert('err', err.message || 'Something went wrong. Please try again later.');
    } finally {
      setLoading(false);
    }
  });
})();