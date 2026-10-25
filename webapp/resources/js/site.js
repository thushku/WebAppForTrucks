// The website's small helpers: the FAQ search (the questions stay readable
// without it — every answer is in the page).
const search = document.querySelector('[data-faq-search]');
if (search) {
  const items = [...document.querySelectorAll('[data-faq-item]')];
  const groups = [...document.querySelectorAll('[data-faq-group]')];
  const empty = document.querySelector('[data-faq-empty]');
  search.addEventListener('input', () => {
    const words = search.value.toLowerCase().split(/\s+/).filter(Boolean);
    let shown = 0;
    items.forEach((item) => {
      const text = item.textContent.toLowerCase();
      const match = words.every((w) => text.includes(w));
      item.hidden = !match;
      if (match) shown++;
      // Open the matching answers while searching, close them again after.
      item.open = words.length > 0 && match;
    });
    groups.forEach((g) => { g.hidden = !g.querySelector('[data-faq-item]:not([hidden])'); });
    empty?.classList.toggle('hidden', shown > 0);
  });
}
