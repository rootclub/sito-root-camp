<!DOCTYPE html>
<html lang="it" data-tone="ironico">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Archivio · /RooT-Camp</title>
  <link rel="stylesheet" href="styles/global.css">
  <style>
    .page-hero {
      background: #d6b94a url("assets/sky-banded-archivio.png") top left / auto 100% repeat-x;
      padding: 80px 0 60px;
      min-height: 455px;
      position: relative; overflow: hidden;
    }
    .page-hero .hero-sun { width: 200px; height: 200px; top: 90px; right: 8%; }
    .editions-grid {
      display: grid;
      gap: 28px;
      margin-top: 40px;
    }
    .edition-card {
      background: var(--cream);
      border: 2px solid var(--ink);
      border-radius: var(--r-lg);
      padding: 32px;
      box-shadow: 6px 6px 0 var(--ink);
      display: grid;
      grid-template-columns: 180px 1fr auto;
      gap: 28px;
      align-items: center;
    }
    @media (max-width: 800px) {
      .edition-card { grid-template-columns: 1fr; gap: 14px; }
    }
    .edition-card.upcoming { background: var(--hot); color: var(--cream); }
    .edition-card.upcoming .keycap { background: var(--cream); color: var(--ink); }
    .ed-year {
      font-family: var(--font-display);
      font-size: clamp(72px, 8vw, 110px);
      font-weight: 700;
      line-height: .9;
      letter-spacing: -.04em;
    }
    .ed-meta {
      font-family: var(--font-ui);
      font-size: 12px;
      letter-spacing: .12em;
      text-transform: uppercase;
      opacity: .75;
      margin-bottom: 8px;
    }
    .ed-status {
      font-family: var(--font-ui);
      font-size: 11px;
      letter-spacing: .14em;
      text-transform: uppercase;
      padding: 6px 12px;
      background: var(--ink);
      color: var(--cream);
      border-radius: 999px;
      align-self: start;
      justify-self: end;
    }
    .edition-card.upcoming .ed-status { background: var(--cream); color: var(--ink); }

    .placeholder-future {
      border-style: dashed !important;
      background: transparent !important;
      box-shadow: none !important;
      opacity: .55;
      text-align: center;
      padding: 40px;
      grid-template-columns: 1fr;
    }
  </style>
  <template id="__bundler_thumbnail" data-bg-color="#c7e38a">
    <svg viewBox="0 0 1200 800" xmlns="http://www.w3.org/2000/svg">
      <rect width="1200" height="800" fill="#c7e38a"/>
      <text x="600" y="430" font-family="Space Grotesk, sans-serif" font-size="120" font-weight="800" text-anchor="middle" fill="#0f2a1a">Archivio</text>
    </svg>
  </template>
  <?php require __DIR__ . '/inc/jsonld_event.php'; ?>
</head>
<body>
  <div data-slot="topbar"></div>

  <section class="page-hero">
    <div class="hero-sun" aria-hidden="true"></div>
    <div class="cloud cloud-1" aria-hidden="true" style="top:50px;"></div>
    <div class="wrap" style="position:relative;z-index:2;">
      <div class="sec-eyebrow">archivio · tutte le edizioni</div>
      <h1 class="h-1" style="max-width:18ch;font-size:clamp(48px,7vw,96px);">
        Il <span class="sketch">prima</span> e il dopo del camp.
      </h1>
      <p class="dim" style="max-width:52ch;margin-top:18px;font-size:18px;">
        Per ora c'è solo la prima. Tornate qui dopo il 12 luglio 2026 per foto, registrazioni dei talk, lightning recap e meme della serata.
      </p>
    </div>
  </section>

  <section style="padding-top:30px;">
    <div class="wrap">
      <div class="editions-grid" id="editions-list"></div>

      <div class="edition-card placeholder-future" style="margin-top:28px;">
        <div>
          <div class="ed-meta">2027 · prossima edizione</div>
          <h2 class="h-2" style="margin-bottom:8px;">Ci stiamo già pensando.</h2>
          <p>Se vuoi che continui, partecipa, sostieni, racconta. Le date 2027 le decideremo dopo il 12 luglio 2026.</p>
        </div>
      </div>
    </div>
  </section>

  <div data-slot="footer"></div>

  <script src="api/edition.js.php"></script>
  <script src="scripts/partials.js"></script>
  <script src="scripts/runtime.js"></script>
  <script src="scripts/archivio.js"></script>
</body>
</html>

<style id="__om-edit-overrides">
#editions-list .ed-year { font-size: 75px !important }
</style>
