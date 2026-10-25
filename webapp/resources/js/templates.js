// The template editor: the preview follows the form. Each change posts the
// form, as it is, to the preview address and puts the page that comes back
// into the frame (srcdoc; the frame runs no scripts). Nothing is saved until
// Save. The frame is scaled to fit its column, the way paper sits on a desk.

document.querySelectorAll('[data-design-form]').forEach((form) => {
  const frame = document.querySelector('[data-preview]');
  const box = document.querySelector('[data-preview-frame]');
  const state = form.querySelector('[data-preview-state]');
  const accent = form.querySelector('[data-accent]');
  const warning = form.querySelector('[data-contrast-warning]');
  if (!frame || !box) return;

  // Fit the page into the column: the sheet keeps its real width and is scaled down.
  const fit = () => {
    const doc = frame.contentDocument;
    if (!doc?.body) return;
    const sheetWidth = Math.max(...[...doc.querySelectorAll('.sheet')].map((s) => s.offsetWidth + 24), 320);
    const scale = Math.min(1, box.clientWidth / sheetWidth);
    const height = doc.documentElement.scrollHeight;
    frame.style.width = `${sheetWidth}px`;
    frame.style.height = `${height}px`;
    frame.style.transform = `scale(${scale})`;
    frame.style.transformOrigin = 'top left';
    frame.style.marginInline = scale === 1 ? 'auto' : '0';
    box.style.height = `${Math.ceil(height * scale)}px`;
  };
  frame.addEventListener('load', fit);
  window.addEventListener('resize', fit);
  if (frame.contentDocument?.readyState === 'complete') fit();

  let timer = null;
  let running = null;
  const refresh = () => {
    clearTimeout(timer);
    timer = setTimeout(async () => {
      running?.abort();
      running = new AbortController();
      const data = new FormData(form);
      data.delete('_method'); // the preview is a POST; the form itself saves with PUT
      if (state) state.textContent = state.dataset.busy || '…';
      try {
        const response = await fetch(form.dataset.previewUrl, { method: 'POST', body: data, credentials: 'same-origin', signal: running.signal, headers: { Accept: 'text/html' } });
        if (!response.ok) throw new Error(String(response.status));
        frame.srcdoc = await response.text();
        if (state) state.textContent = state.dataset.done || '';
      } catch (error) {
        if (error.name !== 'AbortError' && state) state.textContent = state.dataset.failed || '!';
      }
    }, 250);
  };

  // A light accent on white paper is hard to read (WCAG contrast below 3:1).
  const contrast = (hex) => {
    const c = (i) => { const v = parseInt(hex.slice(i, i + 2), 16) / 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; };
    const l = 0.2126 * c(1) + 0.7152 * c(3) + 0.0722 * c(5);
    return 1.05 / (l + 0.05);
  };
  const checkAccent = () => { if (accent && warning) warning.classList.toggle('hidden', contrast(accent.value) >= 3); };

  // A theme brings its paper (the thermal receipt is 80 mm wide); the paper can still be changed after.
  const paper = form.querySelector('[data-paper]');
  form.querySelectorAll('[data-theme-paper]').forEach((radio) => radio.addEventListener('change', () => {
    if (radio.checked && paper) paper.value = radio.dataset.themePaper;
  }));

  form.addEventListener('input', () => { checkAccent(); refresh(); });
  form.addEventListener('change', refresh);
  form.addEventListener('click', (event) => {
    const swatch = event.target.closest('[data-swatch]');
    if (swatch && accent) { accent.value = swatch.dataset.swatch; checkAccent(); refresh(); return; }
    // Rows added or removed by the shared add-row helper: redraw after it has run.
    if (event.target.closest('[data-add-row], [data-remove-row]')) setTimeout(refresh, 0);
  });
});
