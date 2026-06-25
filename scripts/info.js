// Info — render food / sleep / contatti
(function () {
  window.TAB_mountPartials('info');

  const ed = window.TAB_CURRENT_EDITION;

  // Food cards
  const fg = document.getElementById('food-grid');
  if (fg) {
    const icons = ['☕', '🍝', '🍺', '🥨'];
    fg.innerHTML = ed.food.items.map((it, i) => `
      <div class="food-card alt-${(i % 4) + 1}">
        <div class="ico" aria-hidden="true">${icons[i % icons.length]}</div>
        <h3 class="h-3" style="margin-bottom:8px;">${it.label}</h3>
        <p class="dim" style="font-size:15px;">${it.note}</p>
      </div>
    `).join('');
  }

  // Sleep cards
  const sl = document.getElementById('sleep-list');
  if (sl) {
    const sleepIcons = {
      camping: `<svg viewBox="0 0 80 80" width="70" height="70"><polygon points="10,66 40,18 70,66" fill="#ff6b3d" stroke="#0f2a1a" stroke-width="3"/><polygon points="32,66 40,40 48,66" fill="#0f2a1a"/></svg>`,
      indoor:  `<svg viewBox="0 0 80 80" width="70" height="70"><rect x="14" y="36" width="52" height="30" fill="#fffef5" stroke="#0f2a1a" stroke-width="3"/><polygon points="10,38 40,14 70,38" fill="#ffd36b" stroke="#0f2a1a" stroke-width="3"/><rect x="34" y="48" width="12" height="18" fill="#0f2a1a"/></svg>`,
      offsite: `<svg viewBox="0 0 80 80" width="70" height="70"><rect x="20" y="34" width="40" height="32" fill="#5fb0dc" stroke="#0f2a1a" stroke-width="3"/><polygon points="16,36 40,16 64,36" fill="#fffef5" stroke="#0f2a1a" stroke-width="3"/><rect x="36" y="48" width="8" height="18" fill="#0f2a1a"/><circle cx="50" cy="46" r="3" fill="#ffd36b" stroke="#0f2a1a" stroke-width="2"/></svg>`,
      other:   `<svg viewBox="0 0 80 80" width="70" height="70"><rect x="12" y="42" width="56" height="18" fill="#fffef5" stroke="#0f2a1a" stroke-width="3"/><rect x="12" y="34" width="24" height="14" rx="5" fill="#ff6b3d" stroke="#0f2a1a" stroke-width="3"/><line x1="13" y1="60" x2="13" y2="66" stroke="#0f2a1a" stroke-width="3"/><line x1="67" y1="60" x2="67" y2="66" stroke="#0f2a1a" stroke-width="3"/></svg>`
    };
    const tags = { camping: 'incluso', indoor: 'su prenotazione', offsite: 'fai-da-te', other: 'autonomo' };

    sl.innerHTML = ed.sleep.options.map(o => {
      const soldOut = o.available === false;
      const tag = soldOut ? 'esaurito' : (tags[o.kind] || '');
      return `
      <div class="sleep-card kind-${o.kind}${soldOut ? ' sold-out' : ''}">
        <div class="sleep-icon">${sleepIcons[o.kind] || ''}</div>
        <div>
          <h3 class="h-3" style="margin-bottom:6px;">${o.title}</h3>
          <p style="font-size:16px;line-height:1.5;">${o.body}</p>
        </div>
        <div class="sleep-tag">${tag}</div>
      </div>
    `;
    }).join('');
  }

  // Contatti
  const cg = document.getElementById('contact-grid');
  if (cg) {
    const c = ed.contacts;
    const items = [
      { label: 'Email', value: c.email,    href: `mailto:${c.email}`,                    icon: '✉️' },
      { label: 'Telegram', value: c.telegram, href: c.telegram ? `https://t.me/${c.telegram.replace('@','')}` : '#', icon: '✈️' },
      { label: 'Bluesky', value: c.matrix, href: c.matrix ? `https://bsky.app/profile/${c.matrix.replace(/^@/, '')}` : '#', icon: '🦋' },
      { label: 'Mastodon', value: c.mastodon, href: '#',                                 icon: '🐘' }
    ];
    cg.innerHTML = items.filter(it => it.value).map(it => `
      <a class="contact-card" href="${it.href}" ${it.href.startsWith('http') ? 'target="_blank" rel="noopener"' : ''}>
        <div class="ch-label">${it.icon} ${it.label}</div>
        <div class="ch-value">${it.value}</div>
      </a>
    `).join('');
  }
})();
