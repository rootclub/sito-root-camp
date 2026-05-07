// Regolamento — render rules grid
(function () {
  window.TAB_mountPartials('regolamento');

  const ed = window.TAB_CURRENT_EDITION;
  const grid = document.getElementById('rules-grid');
  if (!grid) return;

  const icons = {
    ticket: `<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v3a2 2 0 0 1 0 4v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3a2 2 0 0 1 0-4V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>`,
    card:    `<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="8" cy="12" r="1.5"/><circle cx="13" cy="12" r="1.5"/><circle cx="18" cy="12" r="1.5"/></svg>`,
    clock:   `<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>`,
    moon:    `<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`,
    volume:  `<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>`,
    tent:    `<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 20l9-15 9 15"/><path d="M12 5v15"/><path d="M9 20l3-5 3 5"/></svg>`
  };

  grid.innerHTML = ed.rules.map((r, i) => `
    <div class="rule-card alt-${i}">
      <div class="rule-num">regola ${String(i + 1).padStart(2, '0')}</div>
      <div class="rule-icon">${icons[r.icon] || ''}</div>
      <h3>${r.title}</h3>
      <p>${r.body}</p>
    </div>
  `).join('');
})();
