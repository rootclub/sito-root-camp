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
