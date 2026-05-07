// Archivio — lista edizioni
(function () {
  window.TAB_mountPartials('archivio');

  const list = document.getElementById('editions-list');
  if (!list) return;

  const editions = window.TAB_EDITIONS || [];
  const today = new Date();

  list.innerHTML = editions.map(ed => {
    const start = new Date(ed.dates.start);
    const isUpcoming = start > today;
    const status = isUpcoming ? 'in arrivo' : 'archivio';

    return `
      <div class="edition-card ${isUpcoming ? 'upcoming' : ''}">
        <div class="ed-year">${ed.year}</div>
        <div>
          <div class="ed-meta">${ed.subtitle} · ${ed.location.name}</div>
          <h2 class="h-2" style="margin-bottom:10px;">${ed.name}</h2>
          <p style="margin-bottom:14px;opacity:.92;">${ed.dates.label} · ${ed.location.city}</p>
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <span class="keycap">${ed.schedule.days.length} giorni</span>
            <span class="keycap">${ed.schedule.days.reduce((n, d) => n + d.items.length, 0)} appuntamenti</span>
            <span class="keycap">${ed.organizers.length} org.</span>
          </div>
          ${isUpcoming ? `
            <div style="margin-top:18px;display:flex;gap:12px;flex-wrap:wrap;">
              <a href="iscrizione.html" class="btn ghost" style="font-size:14px;padding:10px 18px;">Iscriviti →</a>
              <a href="palinsesto.html" class="btn ghost" style="font-size:14px;padding:10px 18px;">Palinsesto →</a>
            </div>
          ` : `
            <div style="margin-top:18px;">
              <a href="#" class="mono" style="font-size:13px;">— recap, foto e registrazioni in arrivo —</a>
            </div>
          `}
        </div>
        <span class="ed-status">${status}</span>
      </div>
    `;
  }).join('');
})();
