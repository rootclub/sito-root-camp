// Homepage: marquee, preview palinsesto, orgs, banner "cookie" parodia
(function () {
  window.TAB_mountPartials('home');

  // Etichetta prezzo biglietto dal DB (meta.php → tickets.price)
  const ticketPrice = (window.TAB_CURRENT_EDITION && window.TAB_CURRENT_EDITION.tickets && window.TAB_CURRENT_EDITION.tickets.price) || '';
  document.querySelectorAll('[data-ticket-price]').forEach(el => { el.textContent = ticketPrice; });

  // ============================================================
  // Banner cookie parodia — bottom slide-in, una volta per browser.
  // ============================================================
  const CB_KEY = 'rcCookieBannerSeen';
  if (!localStorage.getItem(CB_KEY)) {
    const cb = document.createElement('div');
    cb.className = 'cookie-banner';
    cb.setAttribute('role', 'dialog');
    cb.setAttribute('aria-labelledby', 'cb-title');
    cb.innerHTML = `
      <div class="cb-inner">
        <button class="cb-close" aria-label="Chiudi" data-action="dismiss">×</button>
        <h3 id="cb-title">La tua privacy è importante per noi.</h3>
        <p class="cb-lead">
          Ai sensi del Regolamento UE 2016/679 e della Direttiva 2002/58/CE —
          quei capolavori legislativi pensati per limitare la profilazione, e che
          hanno invece partorito un'industria miliardaria di consulenti del consenso,
          finestrelle pop-up e dark pattern istituzionalizzati — siamo onorati di
          chiederti consenso esplicito al trattamento dei tuoi dati personali da
          parte di noi e dei nostri 1.247 partner certificati <strong>IAB Europe</strong>
          (la stessa associazione pubblicitaria che ha scritto il modulo di consenso
          che stai leggendo), inclusi 312 sub-partner non meglio identificati, un
          broker dati con sede formale alle Isole Cayman e tre cugini di nostra
          madre, sulla base del nostro legittimo interesse insindacabile, per le
          seguenti finalità:
        </p>
        <ul class="cb-purposes">
          <li>Memorizzazione e accesso a informazioni su un dispositivo che hai pagato tu</li>
          <li>Annunci e contenuti personalizzati (= ti seguiamo)</li>
          <li>Misurazione di annunci e contenuti (= ti misuriamo mentre ti seguiamo)</li>
          <li>Creazione di un profilo per pubblicità personalizzata, ovvero la cosa che il GDPR doveva impedire</li>
          <li>Selezione di contenuti basata su 1.247 punti di profilazione (cosa siano non lo sappiamo neanche noi)</li>
          <li>Trasferimento dati a giurisdizioni in cui la Corte di Giustizia UE ci ha detto di non trasferirli (Schrems II, 2020) — aggirato con "clausole contrattuali standard", copyright degli avvocati</li>
          <li>Decisioni automatizzate sul prezzo del biglietto aereo che vedi tu, diverso dal prezzo che vede chi ti sta accanto sul divano</li>
          <li>Cessione dello storico di navigazione a entità che chiameremo "partner" per non chiamarle "compratori"</li>
          <li class="opt">Cookie strettamente necessari (non disattivabili, il legislatore non aveva previsto questa eventualità)</li>
          <li class="opt">Esercizio dei tuoi diritti, che sono quarantasette e richiedono una PEC</li>
        </ul>
        <p class="cb-meta">
          Puoi modificare le tue preferenze in qualsiasi momento, attraverso un
          percorso di 11 click in profondità. Verranno comunque ignorate da almeno
          il 30% dei nostri partner, secondo i dati pubblicati da IAB Europe stessa.
        </p>
        <div class="cb-actions">
          <button class="cb-btn cb-btn-primary"  data-action="dismiss">Accetta tutto</button>
          <button class="cb-btn cb-btn-ghost"    data-action="dismiss">Gestisci preferenze (47 click)</button>
          <button class="cb-btn cb-btn-thin"     data-action="dismiss">Continua senza accettare</button>
        </div>
        <p class="cb-truth">
          <strong>P.S. — Niente di tutto questo è vero qui.</strong> Non usiamo
          cookie di tracciamento, non profiliamo, non vendiamo dati, non abbiamo
          partner pubblicitari. L'unico cookie del sito è la sessione del backoffice
          riservato, dove tu non vai, esente da consenso ex art. 5(3) Direttiva ePrivacy.
          <br><br>
          <strong>Però è vero quasi ovunque.</strong> Il banner che ti tocca cliccare
          un milione di volte all'anno non ti ha protetto da nulla: ha solo addestrato
          il dito ad accettare in automatico, mentre l'industria della profilazione
          cresceva del 400% e un esercito di consulenti privacy si comprava la villa
          al mare. La "protezione europea" della tua privacy è uno dei più grandi
          dark pattern legalizzati della storia di Internet, ed è stata scritta dalle
          stesse persone che avevano interesse a non chiamarlo così.
          <br><br>
          Eccoci comunque qui, costretti per legge a chiederti il permesso per cose
          che non facciamo. Almeno ci ridiamo sopra. Informativa per davvero:
          <a href="privacy.html">privacy →</a>
        </p>
      </div>
    `;
    document.body.appendChild(cb);

    // Slide-in dopo un attimo (mima il delay dei banner reali).
    requestAnimationFrame(() => {
      setTimeout(() => cb.classList.add('is-open'), 600);
    });

    function dismiss() {
      try { localStorage.setItem(CB_KEY, '1'); } catch (e) {}
      cb.classList.remove('is-open');
      setTimeout(() => cb.remove(), 400);
    }
    cb.addEventListener('click', (e) => {
      if (e.target.closest('[data-action="dismiss"]')) dismiss();
    });
    document.addEventListener('keydown', function escClose(e) {
      if (e.key === 'Escape' && cb.isConnected) {
        dismiss();
        document.removeEventListener('keydown', escClose);
      }
    });
  }

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
    if (sponsors.length === 0) {
      const sec = document.getElementById('sponsors');
      if (sec) sec.hidden = true;
    } else {
      const sec = document.getElementById('sponsors');
      if (sec) sec.hidden = false;
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
      const cls = `tile hov ${bgs[i % bgs.length]}`;
      const baseStyle = `min-height:180px;display:flex;flex-direction:column;justify-content:space-between;${o.placeholder ? 'border-style:dashed;opacity:.7;' : ''}`;
      const inner = `
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
