// Palinsesto — render lista per giorno + filtri + descrizione espandibile
(function () {
  window.TAB_mountPartials('palinsesto');

  const ed = window.TAB_CURRENT_EDITION;
  const mount = document.getElementById('schedule-mount');
  const emptyMsg = document.getElementById('empty-msg');

  function fmtEnd(time, dur) {
    const [h, m] = time.split(':').map(Number);
    const total = h * 60 + m + (dur || 0);
    const eh = Math.floor(total / 60) % 24;
    const em = total % 60;
    return String(eh).padStart(2, '0') + ':' + String(em).padStart(2, '0');
  }

  function esc(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // Description: prima escape, poi linkify URL, poi \n -> <br>.
  const URL_RE = /\b(https?:\/\/[^\s<]+[^\s<.,:;!?)\]}'"])/g;
  function renderDesc(s) {
    const escaped = esc(s);
    const linked = escaped.replace(URL_RE, (m) =>
      `<a href="${m}" target="_blank" rel="noopener noreferrer">${m}</a>`
    );
    return linked.replace(/\r\n|\r|\n/g, '<br>');
  }

  function trackNameFor(it, trackNames) {
    // Preferisci il nome diretto (robusto a buchi nelle position).
    if (it.trackName) return String(it.trackName);
    // Fallback per payload cached vecchi.
    const i = Number(it.track);
    if (Number.isFinite(i) && trackNames[i]) return trackNames[i];
    return '';
  }

  function render() {
    mount.innerHTML = ed.schedule.days.map(day => {
      const items = [...day.items].sort((a, b) => a.time.localeCompare(b.time));
      const trackNames = day.tracks || [];
      return `
        <div class="day-section" data-day="${esc(day.date)}">
          <div class="day-head">
            <h2>${esc(day.label)}</h2>
            <span class="date-tag">${esc(day.date)}</span>
          </div>
          <div class="schedule-list">
            ${items.map((it, idx) => {
              const hasDesc = !!(it.description && String(it.description).trim());
              const slotId = `slot-${esc(day.date)}-${idx}`;
              const tName = trackNameFor(it, trackNames);
              const interactive = hasDesc
                ? ` role="button" tabindex="0" aria-expanded="false" aria-controls="${slotId}"`
                : '';
              return `
              <article class="slot kind-${esc(it.kind)}${hasDesc ? ' has-desc' : ''}"
                       data-kind="${esc(it.kind)}"${interactive}>
                <div class="slot-time">
                  <span>${esc(it.time)}</span>
                  <span class="duration">→ ${esc(fmtEnd(it.time, it.duration))}</span>
                </div>
                <div class="slot-body">
                  <div class="slot-title">
                    <span>${esc(it.title)}</span>
                    ${hasDesc ? `<span class="chevron" aria-hidden="true">▾</span>` : ''}
                  </div>
                  <div class="slot-meta">
                    <span class="keycap" style="font-size:10px;padding:3px 8px;">${esc(it.kind)}</span>
                    ${it.speaker ? `<span class="speaker">${esc(it.speaker)}</span>` : ''}
                    <span class="dim">${esc(String(it.duration))} min</span>
                  </div>
                  ${hasDesc
                    ? `<div class="slot-desc" id="${slotId}" hidden>${renderDesc(it.description)}</div>`
                    : ''
                  }
                </div>
                <div class="slot-side">
                  ${tName ? `<span class="track-badge">${esc(tName)}</span>` : ''}
                </div>
              </article>
              `;
            }).join('')}
          </div>
        </div>
      `;
    }).join('');
  }

  render();

  // CFP — link "Manda proposta": usa contact_email dell'edizione attiva.
  // Se non valorizzato in admin, il bottone resta nascosto.
  const cfpLink = document.getElementById('cfp-link');
  if (cfpLink) {
    const email = (ed.contacts && ed.contacts.email) ? String(ed.contacts.email).trim() : '';
    if (email) {
      const subject = encodeURIComponent(`CFP /RooT-Camp ${ed.year}`);
      cfpLink.href = `mailto:${email}?subject=${subject}`;
      cfpLink.hidden = false;
    }
  }

  // Toggle: tutta la riga è cliccabile, ma i link nella descrizione (e selezione testo)
  // non devono chiudere lo slot.
  function toggleSlot(slot) {
    const desc = slot.querySelector('.slot-desc');
    if (!desc) return;
    const open = slot.classList.toggle('is-open');
    slot.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) desc.removeAttribute('hidden'); else desc.setAttribute('hidden', '');
  }

  mount.addEventListener('click', (e) => {
    const slot = e.target.closest('.slot.has-desc');
    if (!slot) return;
    // Click su link: lascia passare.
    if (e.target.closest('a')) return;
    // Click dentro la descrizione (per selezionare testo): non toggla.
    if (e.target.closest('.slot-desc')) return;
    toggleSlot(slot);
  });

  mount.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    const slot = e.target.closest('.slot.has-desc');
    if (!slot || slot !== e.target) return;
    e.preventDefault();
    toggleSlot(slot);
  });

  // Filtri
  const chips = document.querySelectorAll('.filter-chip');
  chips.forEach(chip => {
    chip.addEventListener('click', () => {
      const f = chip.dataset.filter;
      chips.forEach(c => {
        c.classList.remove('on', 'talk', 'workshop', 'music', 'kids', 'food', 'opening', 'all');
      });
      chip.classList.add('on', f);
      chips.forEach(c => { if (!c.classList.contains('on')) c.className = 'filter-chip'; });

      const slots = document.querySelectorAll('.slot');
      let visibleByDay = {};
      slots.forEach(s => {
        const match = (f === 'all') || (s.dataset.kind === f) || (f === 'food' && s.dataset.kind === 'food');
        s.classList.toggle('is-hidden', !match);
        const day = s.closest('.day-section').dataset.day;
        if (match) visibleByDay[day] = (visibleByDay[day] || 0) + 1;
      });
      document.querySelectorAll('.day-section').forEach(d => {
        d.style.display = (visibleByDay[d.dataset.day] || 0) > 0 ? '' : 'none';
      });
      const totalVisible = Object.values(visibleByDay).reduce((a, b) => a + b, 0);
      emptyMsg.classList.toggle('show', totalVisible === 0);
    });
  });
})();
