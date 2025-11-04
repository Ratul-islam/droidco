document.addEventListener('DOMContentLoaded', () => {
  const cb = document.getElementById('nav-toggle');
  const label = document.querySelector('label[for="nav-toggle"]');
  if (cb && label) {
    const sync = () => label.setAttribute('aria-expanded', cb.checked ? 'true' : 'false');
    cb.addEventListener('change', sync);
    sync();
  }

  const yearEl = document.getElementById('year');
  if (yearEl) yearEl.textContent = new Date().getFullYear();

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const revealEls = document.querySelectorAll('.reveal');
  if (!prefersReducedMotion && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries, obs) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('in-view');
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach(el => io.observe(el));
  } else {
    revealEls.forEach(el => el.classList.add('in-view'));
  }
});

(function () {
  const form = document.getElementById('contactForm');
  const btn = document.getElementById('submitBtn');
  const alertBox = document.getElementById('formAlert');

  if (!form || !btn || !alertBox) return;

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
      spinner && spinner.classList.remove('hidden');
    } else {
      btn.disabled = false;
      spinner && spinner.classList.add('hidden');
    }
  }

  try {
    if (new URLSearchParams(location.search).get('sent') === '1') {
      setAlert('ok', 'Thanks! Your message has been sent. We’ll get back to you shortly.');
      history.replaceState({}, '', location.pathname + '#contact');
    }
  } catch (_) {
    // ignore
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    alertBox.classList.add('hidden');

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

    if (form.website && form.website.value) {
      setAlert('ok', 'Thanks! Your message has been sent.');
      form.reset();
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
      let payload = {};
      if (ct.includes('application/json')) {
        payload = await res.json();
      }
      if (!res.ok) {
        throw new Error(payload.message || 'Server error. Please try again later.');
      }

      if (payload.success === false) {
        throw new Error(payload.message || 'Failed to send. Please try again later.');
      }

      setAlert('ok', payload.message || 'Thanks! Your message has been sent.');
      form.reset();
    } catch (err) {
      setAlert('err', err?.message || 'Something went wrong. Please try again later.');
    } finally {
      setLoading(false);
    }
  });
})();