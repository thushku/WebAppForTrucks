// Chart hover (dataviz method): one tooltip per column group listing every
// series — the value strong, the series name after it with a short line key.
// Text is set with textContent; the same tooltip shows on keyboard focus.

document.querySelectorAll('[data-chart]').forEach((chart) => {
  const tip = chart.querySelector('[data-tooltip]');
  if (!tip) return;

  const show = (hit, x, y) => {
    let data;
    try { data = JSON.parse(hit.dataset.tip); } catch (e) { return; }
    tip.replaceChildren();
    const head = document.createElement('div');
    head.className = 'mb-1 font-medium text-ink-500';
    head.textContent = data.title;
    tip.append(head);
    data.rows.forEach((row) => {
      const line = document.createElement('div');
      line.className = 'flex items-center gap-2';
      const key = document.createElement('span');
      key.style.cssText = `display:inline-block;width:12px;height:2px;background:${row.color}`;
      const value = document.createElement('strong');
      value.className = 'text-ink-900';
      value.textContent = row.value;
      const label = document.createElement('span');
      label.className = 'text-ink-500';
      label.textContent = row.label;
      line.append(key, value, label);
      tip.append(line);
    });
    tip.classList.remove('hidden');
    const box = chart.getBoundingClientRect();
    const left = Math.min(Math.max(0, x - box.left + 12), box.width - tip.offsetWidth);
    tip.style.left = `${left}px`;
    tip.style.top = `${Math.max(0, y - box.top - tip.offsetHeight - 8)}px`;
    chart.querySelectorAll('[data-bar]').forEach((bar) => { bar.style.opacity = bar.dataset.bar === hit.dataset.hit ? '1' : '0.45'; });
  };
  const hide = () => {
    tip.classList.add('hidden');
    chart.querySelectorAll('[data-bar]').forEach((bar) => { bar.style.opacity = '1'; });
  };

  chart.querySelectorAll('[data-hit]').forEach((hit) => {
    hit.addEventListener('pointermove', (e) => show(hit, e.clientX, e.clientY));
    hit.addEventListener('pointerleave', hide);
    hit.addEventListener('focus', () => { const r = hit.getBoundingClientRect(); show(hit, r.left + r.width / 2, r.top + 20); });
    hit.addEventListener('blur', hide);
  });
});
