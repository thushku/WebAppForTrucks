// Form helpers: confirm before a destructive action, the place-code
// suggestion, the live number preview and the phone sign-in code.

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content
  ?? document.querySelector('input[name="_token"]')?.value ?? '';

const postJson = (url, body) => fetch(url, {
  method: 'POST',
  credentials: 'same-origin',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
  body: new URLSearchParams(body),
}).then((r) => (r.ok ? r.json() : Promise.reject(r)));

const debounce = (fn, ms) => {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
};

// Forms that change or remove something important ask first.
document.addEventListener('submit', (event) => {
  const form = event.target.closest('form[data-confirm]');
  if (form && !window.confirm(form.dataset.confirm)) {
    event.preventDefault();
    event.stopImmediatePropagation();
  }
}, true);

// One button of a form that asks first (the builder's "Save and issue").
document.addEventListener('click', (event) => {
  const button = event.target.closest('button[data-confirm-click]');
  if (button && !window.confirm(button.dataset.confirmClick)) event.preventDefault();
});

// Places and parties: offer a free short code from the name until the code is typed by hand.
document.querySelectorAll('[data-code-suggest]').forEach((form) => {
  const name = form.querySelector('[data-suggest-name]');
  const code = form.querySelector('[data-suggest-code]');
  if (!name || !code) return;
  code.addEventListener('input', () => { code.dataset.touched = '1'; });
  name.addEventListener('input', debounce(() => {
    if (code.dataset.touched || name.value.trim().length < 2) return;
    fetch(`${form.dataset.codeSuggest}?name=${encodeURIComponent(name.value)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then((r) => r.json()).then((d) => { if (!code.dataset.touched) code.value = d.code; }).catch(() => {});
  }, 300));
});

// Party form: show the sections for the roles that are ticked.
document.querySelectorAll('[data-party-form]').forEach((form) => {
  const sync = () => {
    const roles = [...form.querySelectorAll('[data-role]:checked')].map((el) => el.value);
    form.querySelectorAll('[data-role-section]').forEach((section) => {
      const show = section.dataset.roleSection.split(' ').some((r) => roles.includes(r));
      section.classList.toggle('hidden', !show);
    });
  };
  form.addEventListener('change', (event) => { if (event.target.matches('[data-role]')) sync(); });
});

// Rate contracts: another row from the template.
document.querySelectorAll('[data-add-rate-line]').forEach((button) => {
  button.addEventListener('click', () => {
    const table = document.querySelector('[data-rate-lines] tbody');
    const template = document.querySelector('[data-rate-line-template]');
    if (!table || !template) return;
    const next = table.querySelectorAll('tr').length + Date.now() % 1000;
    table.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__N__', String(next)));
  });
});

// Numbering: the next number, as the format is typed.
document.querySelectorAll('[data-numbering]').forEach((form) => {
  const out = form.querySelector('[data-preview]');
  const err = form.querySelector('[data-preview-error]');
  const update = debounce(() => {
    const body = Object.fromEntries(new FormData(form));
    delete body._method;
    postJson(form.dataset.previewUrl, body).then((d) => {
      if (d.ok) { out.textContent = d.number; err.hidden = true; } else { err.textContent = d.error; err.hidden = false; }
    }).catch(() => {});
  }, 250);
  form.addEventListener('input', update);
  form.addEventListener('change', update);
});

// Sign in with the phone: a code that lives two minutes, polled every two seconds.
document.querySelectorAll('[data-qr-login]').forEach((box) => {
  const start = box.querySelector('[data-qr-start]');
  const panel = box.querySelector('[data-qr-panel]');
  const image = box.querySelector('[data-qr-image]');
  const status = box.querySelector('[data-qr-status]');
  const renew = box.querySelector('[data-qr-renew]');
  let timer = null;
  let deadline = 0;

  const stop = () => { clearInterval(timer); timer = null; };
  const expired = () => {
    stop();
    image.classList.add('opacity-20');
    status.textContent = status.dataset.expired;
    renew.classList.remove('hidden');
  };

  const begin = () => {
    stop();
    start.classList.add('hidden');
    panel.classList.remove('hidden');
    renew.classList.add('hidden');
    image.classList.remove('opacity-20');
    status.textContent = status.dataset.waiting;
    postJson(box.dataset.create, {}).then((d) => {
      image.innerHTML = d.svg;
      deadline = Date.now() + d.expires_in * 1000;
      timer = setInterval(() => {
        if (Date.now() > deadline) { expired(); return; }
        postJson(box.dataset.poll, { poll: d.poll }).then((r) => {
          if (r.status === 'approved') { stop(); window.location.assign(r.redirect); }
          else if (r.status === 'expired') expired();
        }).catch(() => {});
      }, 2000);
    }).catch(() => { status.textContent = status.dataset.error; renew.classList.remove('hidden'); });
  };

  start.addEventListener('click', begin);
  renew.addEventListener('click', begin);
  document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); else if (deadline && Date.now() < deadline && !timer) begin(); });
});

// Truck details as plain text: pick the fields, copy or send on WhatsApp.
document.querySelectorAll('[data-share-text]').forEach((box) => {
  const text = () => {
    const lines = [...box.querySelectorAll('[data-share-field]:checked')].map((el) => `${el.dataset.label}: ${el.dataset.value}`);
    return [...lines, '', `— ${box.dataset.footer}`].join('\n');
  };
  const wa = box.querySelector('[data-share-whatsapp]');
  const refresh = () => { if (wa) wa.href = `https://wa.me/?text=${encodeURIComponent(text())}`; };
  box.addEventListener('change', refresh);
  refresh();
  box.querySelector('[data-share-copy]')?.addEventListener('click', (event) => {
    const button = event.currentTarget;
    navigator.clipboard?.writeText(text()).then(() => {
      const label = button.textContent;
      button.textContent = button.dataset.done;
      setTimeout(() => { button.textContent = label; }, 1500);
    });
  });
});

// A select whose options are links ("Add a paper…").
document.querySelectorAll('[data-go-select]').forEach((select) => {
  select.addEventListener('change', () => { if (select.value) window.location.assign(select.value); });
});

// Read-only boxes holding a link select all on focus, ready to copy.
document.querySelectorAll('[data-select-on-focus]').forEach((input) => {
  input.addEventListener('focus', () => input.select());
});

// "Tick all" for a list of boxes: data-check-all="name" ticks every enabled
// data-check-item="name" (the approvals inbox: flagged rows stay unticked).
document.addEventListener('change', (event) => {
  const all = event.target.closest('[data-check-all]');
  if (!all) return;
  document.querySelectorAll(`[data-check-item="${all.dataset.checkAll}"]:not(:disabled)`).forEach((box) => { box.checked = all.checked; });
});

// Another row from a template: data-add-row="<tbody selector>" data-template="<template selector>"
// (the bill builder). __N__ in the template becomes a fresh index. A row's data-remove-row button removes it.
document.addEventListener('click', (event) => {
  const add = event.target.closest('[data-add-row]');
  if (add) {
    const body = document.querySelector(add.dataset.addRow);
    const template = document.querySelector(add.dataset.template);
    if (body && template) body.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__N__', String(Date.now())));
    return;
  }
  const remove = event.target.closest('[data-remove-row]');
  if (remove) remove.closest('tr')?.remove();
});
