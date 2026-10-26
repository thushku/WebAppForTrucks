// The trip form: own or hired truck, the supplier's trucks, empty runs,
// the agreed rate from the rate contract, and a freight preview. The server
// works the freight out again when saving; this only shows it.

const money = new Intl.NumberFormat(document.documentElement.lang || 'en', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

document.querySelectorAll('[data-trip-form]').forEach((form) => {
  const $ = (sel) => form.querySelector(sel);

  // Our truck / hired truck.
  const syncSource = () => {
    const hired = form.querySelector('[data-truck-source]:checked')?.value === 'hired';
    form.querySelectorAll('[data-source-own]').forEach((el) => el.classList.toggle('hidden', hired));
    form.querySelectorAll('[data-source-hired]').forEach((el) => el.classList.toggle('hidden', !hired));
  };
  form.querySelectorAll('[data-truck-source]').forEach((el) => el.addEventListener('change', syncSource));

  // Only the chosen supplier's trucks.
  const supplier = $('[data-supplier]');
  const vehicle = $('[data-supplier-vehicle]');
  const syncVehicles = () => {
    if (!supplier || !vehicle) return;
    [...vehicle.options].forEach((o) => { if (o.value) o.hidden = o.dataset.party !== supplier.value; });
    if (vehicle.selectedOptions[0]?.hidden) vehicle.value = '';
  };
  supplier?.addEventListener('change', syncVehicles);
  syncVehicles();

  // An empty run has no load.
  const kind = $('[data-trip-kind]');
  kind?.addEventListener('change', () => $('[data-first-load]')?.classList.toggle('hidden', kind.value === 'empty'));

  form.querySelectorAll('[data-load]').forEach((load) => {
    const q = (sel) => load.querySelector(sel);
    const rate = q('[data-calc-rate]');
    const basis = q('[data-calc-basis]');
    const line = q('[data-rate-line]');
    const note = q('[data-rate-note]');
    let typedByHand = !!rate?.value;

    const preview = () => {
      const r = parseFloat(rate?.value || '0');
      const weight = parseFloat(q('[data-calc-charged]')?.value || q('[data-calc-weight]')?.value || '0');
      const km = parseFloat($('[data-calc-km]')?.value || '0');
      const packages = parseFloat(q('[data-calc-packages]')?.value || '0');
      const min = parseFloat(line?.dataset.min || '0');
      const amount = { trip: r, tonne: r * Math.max(weight, min), kg: r * Math.max(weight, min) * 1000, km: r * km, package: r * packages }[basis?.value || 'trip'];
      const out = q('[data-calc-freight]');
      if (out) out.textContent = r ? money.format(amount || 0) : '—';
    };
    load.addEventListener('input', preview);
    load.addEventListener('change', preview);
    rate?.addEventListener('input', () => { typedByHand = true; if (line) { line.value = ''; line.dataset.min = ''; } });

    const lookup = () => {
      const party = q('[data-rate-party]')?.value;
      const from = $('[data-rate-from]')?.value;
      const to = $('[data-rate-to]')?.value;
      if (!party || !from || !to) return;
      const params = new URLSearchParams({ party, from, to, material: q('[data-rate-material]')?.value || '', truck: $('[data-rate-truck]')?.value || '', date: $('[data-rate-date]')?.value || '' });
      fetch(`${load.dataset.rateUrl}?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((r) => r.json())
        .then(({ rate: found }) => {
          if (!found) { if (note) note.textContent = ''; return; }
          if (note) note.textContent = `${found.contract}: ${money.format(found.rate)} ${basis?.querySelector(`option[value="${found.basis}"]`)?.textContent || ''}${found.min_weight ? ` · min ${found.min_weight} MT` : ''}`;
          if (typedByHand && line?.value === '' && rate.value && parseFloat(rate.value) !== found.rate) return;
          rate.value = found.rate;
          if (basis) basis.value = found.basis;
          if (line) { line.value = found.line_id; line.dataset.min = found.min_weight || ''; }
          typedByHand = false;
          preview();
        })
        .catch(() => {});
    };
    ['[data-rate-party]', '[data-rate-material]'].forEach((sel) => q(sel)?.addEventListener('change', lookup));
    ['[data-rate-from]', '[data-rate-to]', '[data-rate-truck]', '[data-rate-date]'].forEach((sel) => $(sel)?.addEventListener('change', lookup));
    preview();
  });
});

// Bars whose width comes from the server (set here: no inline styles under the CSP).
document.querySelectorAll('[data-width]').forEach((el) => { el.style.width = `${Math.max(0, Math.min(100, Number(el.dataset.width)))}%`; });

// Office entries on a trip: the "For" list shows expense or income kinds, as chosen.
document.querySelectorAll('[data-office-form]').forEach((form) => {
  const kind = form.querySelector('[data-office-kind]');
  const select = form.querySelector('[name="category"]');
  const sync = () => {
    form.querySelectorAll('[data-office-group]').forEach((group) => {
      const on = group.dataset.officeGroup === kind.value;
      group.disabled = !on;
      group.hidden = !on;
    });
    if (select.selectedOptions[0]?.parentElement.disabled) {
      select.value = form.querySelector(`[data-office-group="${kind.value}"] option`)?.value ?? '';
    }
  };
  kind.addEventListener('change', sync);
  sync();
});

// "+ New" inside the trip form (M25): a short form in a dialog; the new
// record joins every list it belongs in and is chosen in the one it was
// opened from (a new place fills From when empty, else To).
const quickTargets = {
  client: ['#load_billing_party_id', '#load_consignor_party_id', '#load_consignee_party_id'],
  person: ['#driver_id', '#co_driver_id', '#helper_ids'],
  truck: ['#truck_id'],
  place: ['#from_place_id', '#to_place_id'],
};
const quickChoose = {
  client: () => '#load_billing_party_id',
  person: () => '#driver_id',
  truck: () => '#truck_id',
  place: () => (document.querySelector('#from_place_id')?.value ? '#to_place_id' : '#from_place_id'),
};
document.querySelectorAll('[data-quick-open]').forEach((button) => {
  button.addEventListener('click', () => {
    const dialog = document.querySelector(`[data-quick-dialog="${button.dataset.quickOpen}"]`);
    if (!dialog) return;
    dialog.querySelector('form').reset();
    dialog.querySelector('[data-quick-error]').classList.add('hidden');
    dialog.showModal();
    dialog.querySelector('input:not([type=hidden])')?.focus();
  });
});
document.querySelectorAll('[data-quick-dialog]').forEach((dialog) => {
  const kind = dialog.dataset.quickDialog;
  const form = dialog.querySelector('[data-quick-form]');
  const error = dialog.querySelector('[data-quick-error]');
  dialog.querySelector('[data-quick-close]').addEventListener('click', () => dialog.close());
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    fetch(form.dataset.url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json', 'X-CSRF-TOKEN': token },
      body: new URLSearchParams(new FormData(form)),
    }).then(async (r) => {
      const body = await r.json().catch(() => ({}));
      if (!r.ok) {
        error.textContent = Object.values(body.errors ?? {})[0]?.[0] ?? body.message ?? 'Could not add it.';
        error.classList.remove('hidden');
        return;
      }
      quickTargets[kind].forEach((sel) => {
        const list = document.querySelector(sel);
        if (list) list.add(new Option(body.label, body.id));
      });
      const chosen = document.querySelector(quickChoose[kind]());
      if (chosen) { chosen.value = String(body.id); chosen.dispatchEvent(new Event('change', { bubbles: true })); }
      dialog.close();
    }).catch(() => { error.textContent = 'No connection. Try again.'; error.classList.remove('hidden'); });
  });
});
