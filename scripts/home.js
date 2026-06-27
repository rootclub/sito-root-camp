// Homepage: marquee, preview palinsesto, orgs
(function () {
  window.TAB_mountPartials('home');

  // Spot radio: toggle play/stop sul floater nella hero
  (function () {
    const btn = document.getElementById('radioToggle');
    const img = document.getElementById('radioImg');
    const audio = document.getElementById('radioAudio');
    if (!btn || !img || !audio) return;

    function setPlaying(on) {
      img.src = on ? 'assets/radio_on.png' : 'assets/radio_off.png';
      btn.classList.toggle('is-playing', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    }

    // Mobile Chrome ignora il preload e bufferizza solo una frazione del file:
    // scarichiamo l'intero mp3 in memoria (blob) al primo tocco sulla pagina,
    // così la riproduzione parte subito e non va in underrun.
    let primed = false;
    let blobUrl = null;
    function prime() {
      if (primed) return;
      primed = true;
      fetch('assets/spot_radio.mp3')
        .then((r) => r.blob())
        .then((b) => { blobUrl = URL.createObjectURL(b); })
        .catch(() => {});
    }
    document.addEventListener('pointerdown', prime, { once: true, passive: true });
    document.addEventListener('touchstart', prime, { once: true, passive: true });

    // Lock anti-evento-duplicato: su Chrome Android un tap su un elemento in
    // movimento (il box ha un'animazione CSS) può generare un secondo click
    // sintetico che ferma l'audio appena avviato. Ignoriamo i click ravvicinati.
    let lockUntil = 0;
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const now = e.timeStamp || performance.now();
      if (now < lockUntil) return;
      lockUntil = now + 600;
      prime();
      if (audio.paused) {
        // Usa la sorgente completamente bufferizzata, se pronta.
        if (blobUrl && audio.src !== blobUrl) audio.src = blobUrl;
        const p = audio.play();
        if (p && p.then) p.then(() => setPlaying(true)).catch(() => setPlaying(false));
      } else {
        audio.pause();
        audio.currentTime = 0;
      }
    });

    audio.addEventListener('play', () => setPlaying(true));
    audio.addEventListener('pause', () => setPlaying(false));
    audio.addEventListener('ended', () => setPlaying(false));
  })();

  // Etichetta prezzo biglietto dal DB (meta.php → tickets.price)
  const ticketPrice = (window.TAB_CURRENT_EDITION && window.TAB_CURRENT_EDITION.tickets && window.TAB_CURRENT_EDITION.tickets.price) || '';
  document.querySelectorAll('[data-ticket-price]').forEach(el => { el.textContent = ticketPrice; });

  // Marquee track — replicato per loop seamless
  const mq = document.getElementById('mq-track');
  if (mq) {
    const fromEdition = (window.TAB_CURRENT_EDITION && Array.isArray(window.TAB_CURRENT_EDITION.marqueeWords))
      ? window.TAB_CURRENT_EDITION.marqueeWords
      : [];
    const words = fromEdition.length ? fromEdition : [
      "/RooT-Camp 2026", "10-11 LUGLIO", "FRATTA TERME", "HACKER CAMP", "TALK", "WORKSHOP",
      "BIRRA", "PRATO", "TENDA", "FAMILY FRIENDLY (fino alle 11)", "IN THE WILD (dopo)",
      "PRIMA EDIZIONE"
    ];
    const escape = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const html = words.map(w => `<span>${escape(w)}<span class="star">✦</span></span>`).join("");
    mq.innerHTML = html + html;
  }

  // Preview palinsesto: i giorni con showInHomePreview !== false
  // (default visibile; di solito si nasconde solo setup/teardown).
  // Se zero giorni visibili → nasconde l'intera sezione #palinsesto.
  const pv = document.getElementById('schedule-preview');
  if (pv) {
    const allDays = window.TAB_CURRENT_EDITION.schedule.days || [];
    const days = allDays.filter(d => d.showInHomePreview !== false);

    if (days.length === 0) {
      const section = document.getElementById('palinsesto');
      if (section) section.style.display = 'none';
    } else {
      pv.innerHTML = days.map(day => {
        const highlights = day.items.filter(i => i.kind !== 'food').slice(0, 5);
        return `
          <div class="schedule-day">
            <div class="schedule-day-head">
              <strong style="font-size:22px;">${day.label}</strong>
              <span class="mono" style="font-size:12px;opacity:.7;">${day.date}</span>
            </div>
            ${highlights.map(it => `
              <div class="schedule-row">
                <div class="schedule-time">${it.time}</div>
                <div class="schedule-item">
                  <div class="title-line">
                    <span class="keycap ${kindColor(it.kind)}" style="font-size:10px;padding:3px 8px;">${it.kind}</span>
                    <strong>${it.title}</strong>
                  </div>
                  ${it.speaker ? `<span class="dim mono" style="font-size:12px;">${it.speaker}</span>` : ''}
                </div>
              </div>
            `).join('')}
          </div>
        `;
      }).join('');
    }
  }
  function kindColor(k) {
    return ({ talk:'grass', workshop:'sun', music:'berry', kids:'sky', opening:'hot' })[k] || '';
  }

  // Sponsor tecnici — sezione nascosta se vuota
  const spEl = document.getElementById('sponsors-grid');
  if (spEl) {
    const sponsors = (window.TAB_CURRENT_EDITION && Array.isArray(window.TAB_CURRENT_EDITION.sponsors))
      ? window.TAB_CURRENT_EDITION.sponsors
      : [];
    const orgsSec = document.getElementById('orgs');
    if (sponsors.length === 0) {
      const sec = document.getElementById('sponsors');
      if (sec) sec.hidden = true;
      // Senza sponsor, "chi lo fa" torna prima sezione cream: padding pieno.
      if (orgsSec) orgsSec.style.paddingTop = '';
    } else {
      const sec = document.getElementById('sponsors');
      if (sec) sec.hidden = false;
      // Con gli sponsor sopra (stesso sfondo), "chi lo fa" sta a filo.
      if (orgsSec) orgsSec.style.paddingTop = '0';
      spEl.innerHTML = sponsors.map(s => {
        const cls = 'tile hov';
        const baseStyle = 'min-height:180px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;text-align:center;';
        const inner = `
          ${s.logo
            ? `<img src="${s.logo}" alt="${s.name}" style="max-width:100%;max-height:90px;object-fit:contain;">`
            : ''}
          <div style="font-family:var(--font-display);font-size:18px;line-height:1.15;font-weight:700;">${s.name}</div>
        `;
        if (s.link) {
          return `<a class="${cls}" style="${baseStyle}text-decoration:none;color:inherit;" href="${s.link}" target="_blank" rel="noopener">${inner}</a>`;
        }
        return `<div class="${cls}" style="${baseStyle}">${inner}</div>`;
      }).join('');
    }
  }

  // Orgs
  const orgsEl = document.getElementById('orgs-grid');
  if (orgsEl) {
    const bgs = ['sun', 'sky', 'grass', ''];
    orgsEl.innerHTML = window.TAB_CURRENT_EDITION.organizers.map((o, i) => {
      const hasPhoto = !!o.photo;
      const cls = `tile hov ${hasPhoto ? '' : bgs[i % bgs.length]}`;
      const baseStyle = `min-height:180px;display:flex;flex-direction:column;${hasPhoto ? 'background:#fff;' : 'justify-content:space-between;'}${o.placeholder ? 'border-style:dashed;opacity:.7;' : ''}`;
      const inner = hasPhoto
        ? `
        <div style="flex:1;display:flex;align-items:center;justify-content:center;padding:8px 0;">
          <img src="${o.photo}" alt="${o.name}" style="max-width:100%;max-height:130px;object-fit:contain;">
        </div>
        <div style="text-align:center;">
          ${o.role ? `<div class="mono" style="font-size:11px;letter-spacing:.12em;text-transform:uppercase;opacity:.75;">${o.role}</div>` : ''}
          <div style="font-family:var(--font-display);font-size:18px;line-height:1.1;font-weight:700;">${o.name}</div>
        </div>
      `
        : `
        <div class="mono" style="font-size:11px;letter-spacing:.12em;text-transform:uppercase;opacity:.75;">${o.role}</div>
        <div style="font-family:var(--font-display);font-size:22px;line-height:1.1;font-weight:700;">${o.name}</div>
      `;
      if (o.link) {
        return `<a class="${cls}" style="${baseStyle}text-decoration:none;color:inherit;" href="${o.link}" target="_blank" rel="noopener">${inner}</a>`;
      }
      return `<div class="${cls}" style="${baseStyle}">${inner}</div>`;
    }).join('');
  }
})();
