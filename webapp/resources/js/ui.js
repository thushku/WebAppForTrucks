// Menus, dismissable messages, the sign-in splash and the page-load bar.

document.addEventListener('click', (event) => {
  // The phone/tablet menu button opens and closes the sidebar.
  const toggle = event.target.closest('[data-toggle="sidebar"]');
  if (toggle) {
    const sidebar = document.getElementById('sidebar');
    const open = sidebar.classList.toggle('hidden') === false;
    sidebar.classList.toggle('flex', open);
    toggle.setAttribute('aria-expanded', String(open));
    return;
  }

  // A dropdown menu (the person's menu in the top bar).
  const menuButton = event.target.closest('[data-menu-button]');
  document.querySelectorAll('[data-menu-panel]').forEach((panel) => {
    const mine = menuButton && panel.closest('[data-menu]') === menuButton.closest('[data-menu]');
    panel.classList.toggle('hidden', !mine || !panel.classList.contains('hidden'));
    if (mine) menuButton.setAttribute('aria-expanded', String(!panel.classList.contains('hidden')));
  });

  // Close a message.
  const dismiss = event.target.closest('[data-dismiss]');
  if (dismiss) dismiss.closest('[data-flash]')?.remove();

  // A fresh picture challenge without reloading the form.
  const refresh = event.target.closest('[data-captcha-refresh]');
  if (refresh) {
    const img = document.querySelector('[data-captcha-image]');
    if (img) img.src = img.dataset.src + '?t=' + Date.now();
  }
});

// Messages leave by themselves after a while (errors stay until closed).
document.querySelectorAll('[data-flash][role="status"]').forEach((el) => {
  setTimeout(() => el.remove(), 6000);
});

// The splash: the bar moves with real loading steps, then the splash fades.
const splash = document.querySelector('[data-splash]');
if (splash) {
  const fill = splash.querySelector('.splash-fill');
  const step = (pct) => { if (fill) fill.style.width = pct + '%'; };
  step(35);
  document.fonts?.ready.then(() => step(70));
  const finish = () => {
    step(100);
    setTimeout(() => splash.classList.add('is-done'), 250);
    setTimeout(() => splash.remove(), 700);
  };
  if (document.readyState === 'complete') finish();
  else window.addEventListener('load', finish, { once: true });
}

// The thin bar at the top while the next page loads.
const bar = document.querySelector('[data-nav-progress]');
if (bar) {
  const start = () => bar.classList.add('is-running');
  document.addEventListener('submit', start);
  document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (link && !link.target && link.origin === location.origin && !event.metaKey && !event.ctrlKey && !link.hasAttribute('download')) start();
  });
  window.addEventListener('pageshow', () => bar.classList.remove('is-running'));
}
