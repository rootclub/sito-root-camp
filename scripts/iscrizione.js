// Iscrizione: render dinamico opzioni sleep + slot pasti, riepilogo live,
// submit reale via POST /api/iscrizione.php.

(function () {
  window.TAB_mountPartials('iscrizione');

  const ed = window.TAB_CURRENT_EDITION;
  if (!ed) {
    console.warn('[iscrizione] TAB_CURRENT_EDITION mancante. Verifica api/edition.js.php.');
    return;
  }

  const TICKET_EUR = (ed.tickets && Number.isFinite(ed.tickets.priceEur)) ? ed.tickets.priceEur : 15;
  const TICKET_LABEL = (ed.tickets && ed.tickets.price) || (TICKET_EUR + ' €');

  // --------- Render sleep options ---------
  const sleepMount = document.getElementById('sleep-options');
  const sleepOpts = (ed.sleep && ed.sleep.options) || [];
  if (sleepMount) {
    sleepMount.innerHTML = sleepOpts.map((o) => {
      const price = o.price_eur || 0;
      // available = selezionabile. Le opzioni esaurite sono comunque mostrate,
      // ma disabilitate e marcate "Esaurito". Il costo non viene mostrato.
      const soldOut = o.available === false;
      return `
        <label class="${soldOut ? 'sold-out' : ''}">
          <input type="radio" name="sleep" value="${o.kind}" data-price="${price}" ${soldOut ? 'disabled' : ''}>
          <div class="rc-text">
            <strong>${o.title}</strong>
            <span>${o.body}</span>
          </div>
          ${soldOut ? '<span class="rc-price sold">Esaurito</span>' : ''}
        </label>
      `;
    }).join('');
    // Preseleziona la prima opzione selezionabile (non esaurita).
    const firstSelectable = sleepMount.querySelector('input[name="sleep"]:not([disabled])');
    if (firstSelectable) firstSelectable.checked = true;
  }

  // --------- Render meal slots (raggruppati per giorno) ---------
  const mealMount = document.getElementById('meal-slots');
  const mealSlots = (ed.meals && ed.meals.slots) || [];
  const DAY_NAMES_IT = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
  const MONTH_NAMES_IT = ['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];
  function dayHead(iso) {
    if (!iso) return 'Senza data';
    const d = new Date(iso + 'T12:00:00');
    if (isNaN(d.getTime())) return iso;
    return `${DAY_NAMES_IT[d.getDay()]} ${d.getDate()} ${MONTH_NAMES_IT[d.getMonth()]}`;
  }
  if (mealMount) {
    if (mealSlots.length === 0) {
      mealMount.innerHTML = `<p class="dim" style="font-size:14px;">Nessun pasto configurato per ora.</p>`;
    } else {
      const groups = new Map();
      for (const m of mealSlots) {
        const key = m.day_date || '';
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(m);
      }
      const sortedKeys = Array.from(groups.keys()).sort();
      mealMount.innerHTML = sortedKeys.map(key => {
        const items = groups.get(key).map(m => `
          <label>
            <input type="checkbox" name="meals" value="${escapeAttr(m.code)}">
            <span>${escapeHtml(m.label)}</span>
          </label>
        `).join('');
        return `
          <div class="meal-day">
            <div class="meal-day-head">${escapeHtml(dayHead(key))}</div>
            <div class="meal-day-items">${items}</div>
          </div>
        `;
      }).join('');
    }
  }

  // --------- Riepilogo live ---------
  function updateRiepilogo() {
    const sleepEl = document.querySelector('input[name="sleep"]:checked');
    const sleepKind  = sleepEl ? sleepEl.value : '';
    const sleepCost  = sleepEl ? parseInt(sleepEl.dataset.price, 10) || 0 : 0;
    const nMeals     = document.querySelectorAll('input[name="meals"]:checked').length;
    const total      = TICKET_EUR + sleepCost;

    const sleepObj = sleepOpts.find(o => o.kind === sleepKind);
    const sleepLine = sleepObj ? sleepObj.title : '—';

    const elTicket = document.getElementById('riepilogo-ticket');
    const elSleep = document.getElementById('riepilogo-sleep');
    const elMeals = document.getElementById('riepilogo-meals');
    const elTotal = document.getElementById('riepilogo-total');
    if (elTicket) elTicket.textContent = TICKET_LABEL;
    if (elSleep) elSleep.textContent = sleepLine;
    if (elMeals) elMeals.textContent = String(nMeals);
    if (elTotal) elTotal.textContent = total + ' €';
  }
  document.querySelectorAll('input[name="sleep"]').forEach(r => {
    r.addEventListener('change', updateRiepilogo);
  });
  if (mealMount) {
    mealMount.addEventListener('change', e => {
      if (e.target && e.target.name === 'meals') updateRiepilogo();
    });
  }
  updateRiepilogo();

  // --------- Consenso art. 9 (salute/dieta): casella condizionale ---------
  // Compare solo se il campo allergie/dieta è valorizzato; se l'utente svuota
  // il campo, la casella si nasconde e si deseleziona (niente dato → niente consenso).
  const dietInput   = document.getElementById('diet');
  const healthRow   = document.getElementById('health-consent-row');
  const healthCheck = document.getElementById('health-consent');
  function syncHealthConsent() {
    if (!dietInput || !healthRow || !healthCheck) return;
    const hasDiet = dietInput.value.trim() !== '';
    healthRow.style.display = hasDiet ? 'flex' : 'none';
    if (!hasDiet) healthCheck.checked = false;
  }
  if (dietInput) dietInput.addEventListener('input', syncHealthConsent);
  syncHealthConsent();

  // --------- Submit ---------
  const form = document.getElementById('reg-form');
  const msg  = document.getElementById('form-msg');
  const submitBtn = form.querySelector('button[type="submit"]');

  if (!ed.registrationsOpen) {
    msg.className = 'form-msg info';
    msg.textContent = 'Le iscrizioni non sono ancora aperte. Torna fra qualche giorno.';
    submitBtn.disabled = true;
    return;
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    msg.className = 'form-msg';
    msg.textContent = '';

    const name      = document.getElementById('name').value.trim();
    const email     = document.getElementById('email').value.trim();
    const phoneEl   = document.getElementById('phone');
    const phone     = phoneEl ? phoneEl.value.trim() : '';
    const ageEl     = document.getElementById('age');
    const age       = ageEl ? ageEl.value : 'adult';
    const sleepKind = (document.querySelector('input[name="sleep"]:checked') || {}).value || '';
    const meals     = Array.from(document.querySelectorAll('input[name="meals"]:checked')).map(c => c.value);
    const dietEl    = document.getElementById('diet');
    const diet      = dietEl ? dietEl.value.trim() : '';
    const notesEl   = document.getElementById('notes');
    const notes     = notesEl ? notesEl.value.trim() : '';
    const honeypot  = (document.getElementById('hp') || {}).value || '';
    const agree     = document.getElementById('agree').checked;
    const privacy   = document.getElementById('privacy').checked;
    const healthConsent = !!(healthCheck && healthCheck.checked);

    if (!name || !email || !agree || !privacy) {
      msg.className = 'form-msg error';
      msg.textContent = !privacy
        ? 'Per procedere devi dichiarare di aver preso visione dell\'informativa privacy.'
        : 'Compila nome, email e accetta il regolamento.';
      return;
    }

    // Se ha indicato allergie/dieta deve dare il consenso art. 9, altrimenti
    // non possiamo trattare quel dato: non lo inviamo affatto.
    if (diet !== '' && !healthConsent) {
      msg.className = 'form-msg error';
      msg.textContent = 'Hai indicato allergie o un regime alimentare: per trattare questo dato spunta la casella di consenso, oppure svuota il campo per proseguire senza.';
      return;
    }

    submitBtn.disabled = true;
    const oldLabel = submitBtn.textContent;
    submitBtn.textContent = 'Invio…';

    try {
      const res = await fetch('api/iscrizione.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
          name, email, phone, age,
          sleep_kind: sleepKind,
          meals,
          diet,
          notes,
          privacy_accepted: true,
          health_consent: healthConsent,
          _hp: honeypot,
        }),
      });
      const data = await res.json().catch(() => ({}));

      if (res.ok && data.ok) {
        msg.className = 'form-msg success';
        msg.innerHTML = `
          Iscrizione ricevuta, <strong>${escapeHtml(name)}</strong>.
          Ti abbiamo scritto a <strong>${escapeHtml(email)}</strong> con il riepilogo
          (totale previsto: <strong>${data.total_eur} €</strong>) e il link per modificare le tue scelte.
          Ci si vede nel prato.
        `;
        form.reset();
        updateRiepilogo();
        syncHealthConsent();
        return;
      }

      msg.className = 'form-msg error';
      if (data.code === 'registrations_closed') {
        msg.textContent = 'Le iscrizioni non sono aperte in questo momento.';
      } else if (data.code === 'validation_failed' && Array.isArray(data.errors)) {
        msg.textContent = data.errors.join(' ');
      } else {
        msg.textContent = 'Errore inatteso. Riprova fra un momento.';
      }
    } catch (err) {
      msg.className = 'form-msg error';
      msg.textContent = 'Connessione al server fallita. Verifica la rete e riprova.';
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = oldLabel;
    }
  });

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
  }
  function escapeAttr(s) {
    return String(s).replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
  }
})();
