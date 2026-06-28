// /RooT-Camp runtime — versione semplificata.
// In passato gestiva 3 toni di copy (ironico/sobrio/tecnico) + tweaks panel.
// Ora il contenuto editoriale viene da api/edition.js.php; le copy del sito
// sono hardcoded in italiano nelle HTML. Qui restano solo le utility minime.

(function () {
  // Riferimento globale all'edizione corrente (proveniente da api/edition.js.php).
  // Comodo per debugging dalla console.
  window.TAB = {
    get edition() { return window.TAB_CURRENT_EDITION; },
    get editions() { return window.TAB_EDITIONS || []; },
  };

  // Inietta i dati strutturati schema.org/Event nella <head>, costruiti
  // dall'edizione corrente: stessa fonte di verità del backoffice, così le
  // date/luogo restano allineate senza duplicarle in HTML. Googlebot esegue il
  // JS e legge il JSON-LD iniettato a runtime, quindi va bene farlo qui.
  window.TAB_injectEventJsonLd = function () {
    const ed = window.TAB_CURRENT_EDITION;
    if (!ed || !ed.dates || !ed.dates.start) return;
    const base = 'https://rootcamp.rootclub.it/';
    const loc = ed.location || {};
    const data = {
      '@context': 'https://schema.org',
      '@type': 'Event',
      name: ed.name || ('/RooT-Camp ' + (ed.year || '')),
      startDate: ed.dates.start,
      endDate: ed.dates.end || ed.dates.start,
      eventStatus: 'https://schema.org/EventScheduled',
      eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
      image: base + 'assets/preview.jpg',
      url: base,
      location: {
        '@type': 'Place',
        name: loc.name || 'Fratta Terme',
        address: {
          '@type': 'PostalAddress',
          addressLocality: [loc.city, loc.region].filter(Boolean).join(', '),
          addressCountry: 'IT',
        },
      },
      organizer: { '@type': 'Organization', name: 'RootClub', url: 'https://rootclub.it' },
    };
    if (ed.subtitle) data.description = ed.subtitle;
    if (ed.registrationsOpen) {
      data.offers = {
        '@type': 'Offer',
        url: base + 'iscrizione.html',
        availability: 'https://schema.org/InStock',
      };
      if (ed.tickets && ed.tickets.priceEur != null) {
        data.offers.price = String(ed.tickets.priceEur);
        data.offers.priceCurrency = 'EUR';
      }
    }
    const s = document.createElement('script');
    s.type = 'application/ld+json';
    s.textContent = JSON.stringify(data);
    document.head.appendChild(s);
  };

  // Variante con palinsesto completo: Event padre + un subEvent per ogni
  // sessione (talk/workshop/musica…), costruito dall'INTERO schedule del
  // backoffice — non dall'anteprima ridotta della home. Da usare su
  // palinsesto.html, la pagina semanticamente dedicata al programma.
  window.TAB_injectScheduleJsonLd = function () {
    const ed = window.TAB_CURRENT_EDITION;
    if (!ed || !ed.schedule || !Array.isArray(ed.schedule.days)) return;
    const base = 'https://rootcamp.rootclub.it/';
    const TZ = '+02:00'; // Italia, luglio (ora legale CEST)
    const loc = ed.location || {};
    const venue = {
      '@type': 'Place',
      name: loc.name || 'Fratta Terme',
      address: {
        '@type': 'PostalAddress',
        addressLocality: [loc.city, loc.region].filter(Boolean).join(', '),
        addressCountry: 'IT',
      },
    };

    // "YYYY-MM-DD" + n giorni, senza usare Date (deterministico).
    function addDays(date, n) {
      const p = date.split('-').map(Number);
      let y = p[0], mo = p[1], d = p[2] + n;
      const leap = yy => (yy % 4 === 0 && (yy % 100 !== 0 || yy % 400 === 0));
      const dim = mm => [31, leap(y) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][mm - 1];
      while (d > dim(mo)) { d -= dim(mo); mo++; if (mo > 12) { mo = 1; y++; } }
      return y + '-' + String(mo).padStart(2, '0') + '-' + String(d).padStart(2, '0');
    }

    // "HH:MM" + minuti -> ISO con offset; se l'orario scavalca la mezzanotte
    // la data avanza di conseguenza (es. DJ set 23:30 +60 -> giorno dopo 00:30).
    function isoAt(date, hhmm, addMin) {
      const m = /^(\d{1,2}):(\d{2})/.exec(hhmm);
      if (!m) return null;
      const total = (+m[1]) * 60 + (+m[2]) + (addMin || 0);
      const dayShift = Math.floor(total / 1440);
      const tod = ((total % 1440) + 1440) % 1440;
      const hh = String(Math.floor(tod / 60)).padStart(2, '0');
      const mm = String(tod % 60).padStart(2, '0');
      return (dayShift > 0 ? addDays(date, dayShift) : date) + 'T' + hh + ':' + mm + ':00' + TZ;
    }

    const subEvents = [];
    ed.schedule.days.forEach(function (day) {
      (day.items || []).forEach(function (it) {
        if (!it.title || it.kind === 'food' || !it.time || !day.date) return;
        const ev = {
          '@type': 'Event',
          name: it.title,
          startDate: isoAt(day.date, it.time, 0),
          eventStatus: 'https://schema.org/EventScheduled',
          eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
          location: it.trackName
            ? { '@type': 'Place', name: venue.name + ' — ' + it.trackName, address: venue.address }
            : venue,
        };
        if (it.duration) ev.endDate = isoAt(day.date, it.time, it.duration);
        if (it.description) ev.description = it.description;
        if (it.speaker) ev.performer = { '@type': 'Person', name: it.speaker };
        subEvents.push(ev);
      });
    });
    if (subEvents.length === 0) return;

    const data = {
      '@context': 'https://schema.org',
      '@type': 'Event',
      name: ed.name || ('/RooT-Camp ' + (ed.year || '')),
      startDate: (ed.dates && ed.dates.start) || undefined,
      endDate: (ed.dates && ed.dates.end) || undefined,
      eventStatus: 'https://schema.org/EventScheduled',
      eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
      image: base + 'assets/preview.jpg',
      url: base + 'palinsesto.html',
      location: venue,
      organizer: { '@type': 'Organization', name: 'RootClub', url: 'https://rootclub.it' },
      subEvent: subEvents,
    };
    if (ed.subtitle) data.description = ed.subtitle;

    const s = document.createElement('script');
    s.type = 'application/ld+json';
    s.textContent = JSON.stringify(data);
    document.head.appendChild(s);
  };

  // Smooth scroll personalizzato per anchor in pagina (più rapido di scroll-behavior:smooth).
  function smoothScrollTo(targetY, duration) {
    const startY = window.scrollY || window.pageYOffset;
    const diff = targetY - startY;
    if (Math.abs(diff) < 2) return;
    const start = performance.now();
    const ease = t => 1 - Math.pow(1 - t, 3);
    function step(now) {
      const elapsed = now - start;
      const t = Math.min(1, elapsed / duration);
      window.scrollTo(0, startY + diff * ease(t));
      if (t < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[href^="#"]');
    if (!a) return;
    const href = a.getAttribute('href');
    if (!href || href === '#') return;
    const el = document.querySelector(href);
    if (!el) return;
    e.preventDefault();
    const y = el.getBoundingClientRect().top + window.scrollY - 20;
    smoothScrollTo(y, 700);
    history.replaceState(null, '', href);
  });
})();
