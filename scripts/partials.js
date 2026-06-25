// Partials — topbar + footer per tutte le pagine

window.TAB_PARTIALS = {
  topbar(active) {
    return `
      <header class="topbar">
        <div class="wrap topbar-inner">
          <a href="index.php" class="brand">
            <span class="tabkey">/RooT-Camp</span>
            <span class="meta">2026</span>
          </a>
          <button class="nav-toggle" aria-label="Apri menu" aria-expanded="false" aria-controls="site-nav">
            <span></span><span></span><span></span>
          </button>
          <nav class="nav" id="site-nav">
            <a href="index.php"       ${active==='home'?'class="active"':''}>home</a>
            <a href="palinsesto.php"  ${active==='palinsesto'?'class="active"':''}>palinsesto</a>
            <a href="info.php"        ${active==='info'?'class="active"':''}>info pratiche</a>
            <a href="regolamento.php" ${active==='regolamento'?'class="active"':''}>regolamento</a>
            <a href="archivio.php"    ${active==='archivio'?'class="active"':''}>archivio</a>
            <a href="iscrizione.php"  class="cta ${active==='iscrizione'?'active':''}">iscriviti →</a>
          </nav>
        </div>
      </header>
    `;
  },
  footer() {
    const e = window.TAB_CURRENT_EDITION;
    return `
      <footer class="site-footer">
        <div class="wrap">
          <div class="footer-grid">
            <div>
              <div style="display:inline-flex;align-items:center;gap:10px;margin-bottom:14px;">
                <span class="tabkey" style="background:var(--cream);color:var(--ink);border:2px solid var(--cream);padding:6px 12px;border-radius:var(--r-sm);font-family:var(--font-ui);font-weight:700;letter-spacing:.04em;">/RooT-Camp</span>
              </div>
              <p style="max-width:36ch;opacity:.9;">Hacker camp estivo a Fratta Terme. Prima edizione nel luglio 2026. Ci si vede nel prato.</p>
              <p class="hand" style="font-size:26px;margin-top:16px;color:var(--sun);">a presto!</p>
            </div>
            <div>
              <h4 style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;margin-bottom:12px;opacity:.7;">Naviga</h4>
              <div style="display:grid;gap:8px;">
                <a href="index.php">Home</a>
                <a href="palinsesto.php">Palinsesto</a>
                <a href="info.php">Info pratiche</a>
                <a href="regolamento.php">Regolamento</a>
                <a href="iscrizione.php">Iscrizione</a>
                <a href="archivio.php">Archivio</a>
                <a href="privacy.php">Privacy</a>
              </div>
            </div>
            <div>
              <h4 style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;margin-bottom:12px;opacity:.7;">Scrivici</h4>
              <div style="display:grid;gap:8px;">
                ${e.contacts.email    ? `<a href="mailto:${e.contacts.email}">${e.contacts.email}</a>` : ''}
                ${e.contacts.matrix   ? `<a href="https://bsky.app/profile/${e.contacts.matrix.replace(/^@/, '')}" target="_blank" rel="noopener">bluesky · ${e.contacts.matrix}</a>` : ''}
                ${e.contacts.telegram ? `<a href="${/^https?:\/\//.test(e.contacts.telegram) ? e.contacts.telegram : `https://t.me/${e.contacts.telegram.replace(/^@/, '')}`}" target="_blank" rel="noopener">telegram · ${e.contacts.telegram}</a>` : ''}
                ${e.contacts.mastodon ? `<a href="#">mastodon · ${e.contacts.mastodon}</a>` : ''}
              </div>
            </div>
            <div>
              <h4 style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;margin-bottom:12px;opacity:.7;">Organizzano</h4>
              <div style="display:grid;gap:10px;opacity:.9;">
                ${e.organizers.map(o => {
                  const name = o.link
                    ? `<a href="${o.link}" target="_blank" rel="noopener" style="color:inherit;">${o.name}</a>`
                    : o.name;
                  return `<span style="display:block;"><span style="opacity:.7;font-size:12px;">${o.role}</span><br><strong>${name}</strong></span>`;
                }).join("")}
              </div>
            </div>
          </div>
          <div style="margin-top:56px;padding-top:20px;border-top:1px solid rgba(255,255,255,.3);display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px;opacity:.8;font-family:var(--font-ui);font-size:12px;">
            <span>/RooT-Camp ${e.year} · a cura di Associazione Root APS · tutti i diritti a chi ha diritto</span>
            <span>v1.0 · ${new Date().toISOString().slice(0,10)}</span>
          </div>
        </div>
      </footer>
    `;
  }
};

window.TAB_mountPartials = function(active) {
  const t = document.querySelector("[data-slot='topbar']"); if (t) t.outerHTML = window.TAB_PARTIALS.topbar(active);
  const f = document.querySelector("[data-slot='footer']"); if (f) f.outerHTML = window.TAB_PARTIALS.footer();

  // Hamburger
  const btn = document.querySelector('.nav-toggle');
  const nav = document.getElementById('site-nav');
  if (btn && nav) {
    btn.addEventListener('click', () => {
      const open = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', String(!open));
      nav.classList.toggle('is-open', !open);
      btn.classList.toggle('is-open', !open);
    });
    // chiudi su link tap
    nav.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
      btn.setAttribute('aria-expanded','false');
      nav.classList.remove('is-open');
      btn.classList.remove('is-open');
    }));
  }
};
