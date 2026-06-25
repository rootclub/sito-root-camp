<!DOCTYPE html>
<html lang="it" data-tone="ironico">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>/RooT-Camp — hacker camp · Fratta Terme · 10-12 luglio 2026</title>
  <link rel="stylesheet" href="styles/global.css">
  <template id="__bundler_thumbnail" data-bg-color="#92ccea">
    <svg viewBox="0 0 1200 800" xmlns="http://www.w3.org/2000/svg">
      <rect width="1200" height="800" fill="#92ccea"/>
      <circle cx="980" cy="170" r="68" fill="#ffd36b"/>
      <path d="M0 600 Q 300 540 600 580 T 1200 580 L 1200 800 L 0 800 Z" fill="#7ec04b"/>
      <g font-family="Space Grotesk, sans-serif" font-size="120" font-weight="800" text-anchor="middle">
        <text x="600" y="420">
          <tspan fill="#ff6b3d">/</tspan><tspan fill="#ffd36b">R</tspan><tspan fill="#3fa34d">o</tspan><tspan fill="#5fb0dc">o</tspan><tspan fill="#e8488a">T</tspan><tspan fill="#0f2a1a">-</tspan><tspan fill="#ffd36b">C</tspan><tspan fill="#ff6b3d">a</tspan><tspan fill="#5fb0dc">m</tspan><tspan fill="#3fa34d">p</tspan>
        </text>
      </g>
      <polygon points="150,720 230,580 310,720" fill="#ff6b3d" stroke="#0f2a1a" stroke-width="6"/>
      <polygon points="900,720 980,580 1060,720" fill="#5fb0dc" stroke="#0f2a1a" stroke-width="6"/>
    </svg>
  </template>
  <?php require __DIR__ . '/inc/jsonld_event.php'; ?>
</head>
<body>
  <div data-slot="topbar"></div>

  <!-- HERO -->
  <section class="hero" style="padding:0;">
    <!-- cielo: nuvole -->
    <div class="cloud cloud-1" aria-hidden="true"></div>
    <div class="cloud cloud-2" aria-hidden="true"></div>
    <div class="cloud cloud-3" aria-hidden="true"></div>

    <!-- HERO VIDEO -->
    <div class="hero-video" aria-hidden="true">
      <video autoplay muted loop playsinline preload="auto" poster="">
        <source src="assets/hero.mp4" type="video/mp4"/>
      </video>
      <div class="hero-video-tint"></div>
    </div>

    <!-- patrocinio -->
    <div class="floater f-patrocinio">
      <img src="assets/logo_bertinoro.webp" alt="Comune di Bertinoro" width="300" height="300" />
      <span>con il patrocinio del<strong>Comune di Bertinoro</strong></span>
    </div>

    <!-- floater keycaps sparse -->
    <a href="#palinsesto" class="floater f-talk" aria-label="Vai al palinsesto">talk</a>
    <a href="#palinsesto" class="floater f-workshop" aria-label="Vai al palinsesto">workshop</a>
    <a href="info.php#mangiare" class="floater f-birra" aria-label="Info su mangiare e bere">birra</a>
    <a href="info.php#mangiare" class="floater f-cucina" aria-label="Info su mangiare e bere">cucina</a>
    <a href="info.php#dormire" class="floater f-campeggio" aria-label="Info sul campeggio">campeggio</a>

    <div class="wrap hero-content" style="padding-top:24px;">
      <h1 class="hero-title hero-title-img hero-title-split" aria-label="/RooT-Camp">
        <span class="logo-stack">
          <img class="logo-part logo-slash" src="assets/logo-slash.png" alt="" />
          <img class="logo-part logo-root"  src="assets/logo-root.png"  alt="" />
          <img class="logo-part logo-dash"  src="assets/logo-dash.png"  alt="" />
          <img class="logo-part logo-camp"  src="assets/logo-camp.png"  alt="/RooT-Camp 2026" />
        </span>
      </h1>

      <div class="hero-stack">
        <p class="hero-lede">
          Tre giorni di talk, workshop, birre e tende piantate in un prato a Fratta Terme. Prima edizione. Se va male, non è colpa nostra.
        </p>

        <div class="hero-side">
          <div class="hero-side-box">
            <div class="hero-dates-col">
              <span class="keycap hot">10 lug</span>
              <span class="keycap hot">11 lug</span>
              <span class="keycap hot">12 lug</span>
            </div>
            <div class="hero-side-right">
              <div class="hand" style="font-size:30px;color:var(--cream);line-height:1;">
                ci vediamo<br>nel prato!
              </div>
              <svg width="120" height="48" viewBox="0 0 120 48" class="hero-arrow">
                <path d="M10 10 Q 40 40 100 32" stroke="var(--sun)" stroke-width="3" fill="none" stroke-linecap="round"/>
                <polyline points="92,28 100,32 95,40" stroke="var(--sun)" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
          </div>
        </div>
      </div>

      <div class="btn-row" style="margin-top:40px;gap:35px;">
        <a href="iscrizione.php" class="btn accent">Mi iscrivo <span class="arr">→</span></a>
        <a href="palinsesto.php" class="btn ghost">Vedi il palinsesto</a>
      </div>
    </div>

    <!-- scroll cue -->
    <a href="#sintesi" class="scroll-cue" aria-label="Scorri in basso">
      <span class="scroll-cue-label">scorri</span>
      <span class="scroll-cue-mouse" aria-hidden="true">
        <span class="scroll-cue-wheel"></span>
      </span>
    </a>

  </section>

  <!-- MARQUEE -->
  <div class="marquee" aria-hidden="true">
    <div class="marquee-track" id="mq-track"></div>
  </div>

  <!-- WHAT / WHERE / WHEN -->
  <section id="sintesi" style="background:var(--cream);">
    <div class="wrap">
      <div class="sec-eyebrow">sintesi</div>
      <h2 class="h-1" style="max-width:22ch;margin-bottom:48px;">
        Un <span class="sketch">hacker camp</span> nel verde della Romagna.
      </h2>

      <div class="grid-3">
        <div class="tile hov tilt-l">
          <div class="mono" style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--ink-dim);margin-bottom:14px;">01 · che roba</div>
          <h3 class="h-2" style="margin-bottom:14px;">Che roba è</h3>
          <p>Un hacker camp. Tipo: gente che parla di reti mesh mentre al tavolo accanto c'è una padella di salsiccia. I bambini giocano, gli adulti saldano. Nessuno ha ragione su systemd.</p>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:18px;">
            <span class="keycap grass">talk</span>
            <span class="keycap sun">workshop</span>
            <span class="keycap sky">musica</span>
            <span class="keycap">birra</span>
          </div>
        </div>
        <div class="tile hov accent">
          <div class="mono" style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;opacity:.85;margin-bottom:14px;">02 · dove</div>
          <h3 class="h-2" style="margin-bottom:14px;">Dove</h3>
          <p>Fratta Terme, Bertinoro (FC). C'è un prato, c'è una struttura, c'è del verde. Il wifi lo portiamo noi.</p>
          <div style="margin-top:18px;font-family:var(--font-ui);font-size:13px;opacity:.95;">
            📍 Via Superga 190 · Fratta Terme · Bertinoro · FC
          </div>
        </div>
        <div class="tile hov tilt-r sky">
          <div class="mono" style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--ink-dim);margin-bottom:14px;">03 · quando</div>
          <h3 class="h-2" style="margin-bottom:14px;">Quando</h3>
          <p>10 - 11 - 12 luglio 2026. Allestimento il 9, domenica 12 mattina e primo pomeriggio per il debrief. Se arrivi l'8 ti mettiamo a montare palchi.</p>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:18px;">
            <span class="keycap">VEN 10</span>
            <span class="keycap hot">SAB 11</span>
            <span class="keycap">DOM 12</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- PALINSESTO PREVIEW -->
  <section id="palinsesto" style="background:linear-gradient(180deg, var(--cream) 0%, var(--sky-1) 100%);">
    <div class="wrap">
      <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:20px;margin-bottom:40px;">
        <div>
          <div class="sec-eyebrow">palinsesto</div>
          <h2 class="h-1" style="max-width:18ch;">
            Talk, workshop, cose <em class="hand" style="color:var(--hot);font-style:normal;font-size:.95em;">nel prato</em>.
          </h2>
        </div>
        <a href="palinsesto.php" class="btn ghost">Apri il palinsesto completo →</a>
      </div>

      <div id="schedule-preview" class="schedule-preview"></div>
      <p class="dim mono" style="font-size:12px;margin-top:16px;">
        In aggiornamento. I buchi si riempiono man mano che arrivano i sì.
      </p>
    </div>
  </section>

  <!-- MANGIARE / DORMIRE / REGOLE QUICK LINKS -->
  <section style="background:var(--sky-1);">
    <div class="wrap">
      <div class="sec-eyebrow">info pratiche</div>
      <h2 class="h-1" style="margin-bottom:48px;max-width:20ch;">
        Come funziona <em class="hand" style="color:var(--hot);font-style:normal;">la faccenda</em>.
      </h2>

      <div class="grid-3">
        <a href="info.php#mangiare" class="tile hov" style="text-decoration:none;color:inherit;display:block;">
          <div class="eatdrink-ill" style="margin-bottom:18px;">
            <svg viewBox="0 0 80 60" width="80" height="60" style="display:block;">
              <!-- boccale -->
              <rect x="18" y="18" width="36" height="36" rx="3" fill="var(--sun)" stroke="var(--ink)" stroke-width="2.5"/>
              <rect x="22" y="14" width="28" height="8" fill="var(--cream)" stroke="var(--ink)" stroke-width="2.5"/>
              <path d="M54 26 Q 66 26 66 38 Q 66 48 54 48" fill="none" stroke="var(--ink)" stroke-width="2.5"/>
              <line x1="24" y1="28" x2="48" y2="28" stroke="var(--ink)" stroke-width="1.5" opacity=".5"/>
              <line x1="24" y1="38" x2="48" y2="38" stroke="var(--ink)" stroke-width="1.5" opacity=".5"/>
            </svg>
          </div>
          <h3 class="h-3" style="margin-bottom:8px;">Si mangia, si beve</h3>
          <p class="dim">Si mangia bene. Si beve meglio.</p>
          <div class="mono" style="margin-top:18px;font-size:12px;color:var(--hot);font-weight:700;">apri →</div>
        </a>
        <a href="info.php#dormire" class="tile hov" style="text-decoration:none;color:inherit;display:block;">
          <div style="margin-bottom:18px;">
            <svg viewBox="0 0 80 60" width="80" height="60" style="display:block;">
              <!-- tenda -->
              <polygon points="10,52 40,14 70,52" fill="var(--hot)" stroke="var(--ink)" stroke-width="2.5"/>
              <polygon points="32,52 40,30 48,52" fill="var(--ink)"/>
              <line x1="40" y1="14" x2="40" y2="52" stroke="var(--ink)" stroke-width="1.5"/>
            </svg>
          </div>
          <h3 class="h-3" style="margin-bottom:8px;">Dove si dorme</h3>
          <p class="dim">Il prato è grande. La tua tenda è benvenuta.</p>
          <div class="mono" style="margin-top:18px;font-size:12px;color:var(--hot);font-weight:700;">apri →</div>
        </a>
        <a href="regolamento.php" class="tile hov" style="text-decoration:none;color:inherit;display:block;">
          <div style="margin-bottom:18px;">
            <svg viewBox="0 0 80 60" width="80" height="60" style="display:block;">
              <!-- braccialetto -->
              <ellipse cx="40" cy="30" rx="26" ry="16" fill="none" stroke="var(--ink)" stroke-width="2.5"/>
              <rect x="30" y="24" width="20" height="12" rx="2" fill="var(--berry)" stroke="var(--ink)" stroke-width="2.5"/>
              <circle cx="40" cy="30" r="1.5" fill="var(--cream)"/>
            </svg>
          </div>
          <h3 class="h-3" style="margin-bottom:8px;">Regole del camp</h3>
          <p class="dim">Poche regole, sensate. Leggile comunque.</p>
          <div class="mono" style="margin-top:18px;font-size:12px;color:var(--hot);font-weight:700;">apri →</div>
        </a>
      </div>
    </div>
  </section>

  <!-- SPONSORS -->
  <section id="sponsors" style="background:var(--cream);" hidden>
    <div class="wrap">
      <div class="sec-eyebrow">sponsor tecnici</div>
      <h2 class="h-1" style="margin-bottom:48px;max-width:26ch;">
        Chi <span class="sketch">supporta</span> la manifestazione.
      </h2>
      <div id="sponsors-grid" class="grid-4"></div>
    </div>
  </section>

  <!-- ORGS -->
  <section id="orgs" style="background:var(--cream);">
    <div class="wrap">
      <div class="sec-eyebrow">chi lo fa</div>
      <h2 class="h-1" style="margin-bottom:48px;max-width:26ch;">
        Un camp tenuto insieme da <span class="sketch">queste persone</span>.
      </h2>
      <div id="orgs-grid" class="grid-4"></div>
    </div>
  </section>

  <!-- FINAL CTA -->
  <section style="background:var(--hot);color:var(--cream);padding:80px 0;position:relative;overflow:hidden;">
    <div style="position:absolute;inset:0;z-index:0;pointer-events:none;opacity:.18;">
      <svg width="100%" height="100%" preserveAspectRatio="xMidYMid slice" viewBox="0 0 1200 400">
        <g stroke="var(--cream)" stroke-width="1.5" fill="none">
          <circle cx="200" cy="80"  r="40"/>
          <circle cx="400" cy="160" r="80"/>
          <circle cx="900" cy="90"  r="60"/>
          <circle cx="1100" cy="220" r="50"/>
        </g>
      </svg>
    </div>
    <div class="wrap" style="position:relative;z-index:1;text-align:center;">
      <div class="mono" style="font-size:12px;letter-spacing:.16em;text-transform:uppercase;margin-bottom:18px;opacity:.85;">iscrizioni aperte</div>
      <h2 style="font-size:clamp(48px, 8vw, 120px);line-height:.95;letter-spacing:-0.03em;margin-bottom:28px;">
        Ti aspettiamo<br><em class="hand" style="font-style:normal;color:var(--sun);">nel prato</em>.
      </h2>
      <p style="font-size:clamp(18px, 1.8vw, 22px);max-width:50ch;margin:0 auto 36px;opacity:.95;">
        Ingresso libero, aperto a tutt*: si entra con un'offerta libera. La scheda gettoni per cibo e bevande si compra sul posto.
      </p>
      <a href="iscrizione.php" class="btn ghost" style="font-size:20px;padding:18px 32px;">Mi iscrivo <span class="arr">→</span></a>
    </div>
  </section>

  <div data-slot="footer"></div>

  <script src="api/edition.js.php"></script>
  <script src="scripts/partials.js"></script>
  <script src="scripts/runtime.js"></script>
  <script src="scripts/home.js"></script>
</body>
</html>
