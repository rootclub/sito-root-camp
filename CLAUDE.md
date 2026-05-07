# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Marketing site + backoffice for **/RooT-Camp 2026**, a hacker camp in Fratta Terme (Italy). Italian-language. PHP 8.4 + MariaDB + vanilla HTML/CSS/JS frontend. No build step, no package manager, no tests.

Hosting: shared (rootclub.it / Plesk) at `https://rootcamp.rootclub.it`, FTPS deploy. **The provider blocks creation of `/lib/` in document root** — that's why PHP libraries live in `inc/` instead. Don't rename it back.

## Common commands

- **Local preview**: needs PHP since the frontend now fetches `api/edition.js.php`. `php -S 127.0.0.1:8000` from the project root, then open `http://localhost:8000/index.html`. (Plain `python3 -m http.server` won't execute PHP.) For local dev you also need a MariaDB instance with `schema.sql` + `seed-2026.sql` imported and a local `.env` pointing to it.
- **Deploy**: `./deploy.sh <file1> <file2> ...` or `./deploy.sh --all`. Uploads to `rootclub.it` over FTPS using credentials in `.deploy-config`. Blocks `PROTECTED_FILES`: `.env`, `.deploy-config`, `deploy.sh`, `*.sql`, `*.sqlite`, `*.log`, `CLAUDE.md`, `*standalone*`, `scraps/`. **`.env` is never deployed by the script** — upload it manually via FTP client / `curl` the first time and after credential changes.
- **DB schema**: import `schema.sql` (DDL) and optionally `seed-2026.sql` (initial 2026 data) via phpMyAdmin. Never deploy these via FTP — they're protected.
- **First admin**: set `SETUP_TOKEN` in `.env`, visit `/admin/setup.php?token=XXX`, create the user, then clear `SETUP_TOKEN` from `.env`.
- **SMTP test**: `/admin/_smtp_test.php` (admin only, not linked from nav) sends a test mail using current `.env` SMTP settings.

## Backend layout (PHP 8.4 + MariaDB)

```
.env              # secrets (DB, SMTP, SETUP_TOKEN). NEVER deployed via deploy.sh
.env.example      # template
.htaccess         # denies .env, *.sql, *.log, CLAUDE.md (minimal — provider rejects php_flag/mod_expires)
config.php        # bootstrap: loads .env, defines constants, configures session
schema.sql        # DDL: editions, schedule_tracks/items, organizers, rules,
                  # food_items, sleep_options, iscrizioni, admin_users, admin_audit
seed-2026.sql     # initial 2026 edition data

inc/              # PHP includes (renamed from /lib/ — provider blocks /lib/!)
  .htaccess       #   Require all denied (covers PHPMailer/ too)
  env.php         #   minimal .env parser (no deps)
  db.php          #   PDO singleton + db_tx() helper
  response.php    #   e(), redirect(), abort(), json_response(), client_ip()
  csrf.php        #   per-session token, csrf_field(), csrf_check()
  auth.php        #   sessions, login/logout, role check, audit_log()
  edition.php     #   edition_current/active/get/set_active/make_current
  admin_helpers.php # flash messages, post_str/int/bool, get_int/str
  admin_layout.php  # admin_layout_open()/close() — topbar with edition switcher
  crud.php        #   crud_next_sort/move/delete/get for sortable tables
  mailer.php      #   mailer_send() + mailer_send_iscrizione_confirm()
  PHPMailer/      #   vendored PHPMailer 6.10.0 (Exception + PHPMailer + SMTP only)

admin/            # backoffice UI, server-rendered PHP
  login.php       #   credentials form
  logout.php      #   POST + CSRF, GET shows confirm
  setup.php       #   first-run only: SETUP_TOKEN gate + zero-users gate
  index.php       #   dashboard: stats, last 5 iscrizioni, edition recap
  schedule.php    #   palinsesto: items per day + tracks (sale) management
  organizers.php  #   CRUD + sort
  rules.php       #   CRUD + sort, icon whitelist mirrors scripts/regolamento.js
  food.php        #   CRUD + sort
  sleep.php       #   CRUD + sort + kind enum + is_available
  meta.php        #   single form for the active edition's full metadata
  iscrizioni.php  #   list/filter/CSV export/check-in toggle/edit/delete
  editions.php    #   admin-only: create-from-scratch + clone + make_current + delete
  users.php       #   admin-only: CRUD admin/editor with self/last-admin protection
  _switch_edition.php  # POST endpoint behind the topbar edition dropdown
  _smtp_test.php  #   admin-only diagnostic
  assets/admin.css

api/              # public endpoints
  edition.js.php  #   GET — outputs window.TAB_EDITION_<year> = {...} (Cache: 60s)
  iscrizione.php  #   POST — accepts JSON or form-urlencoded; honeypot _hp;
                  #   server-canonical price calc; sends confirm mail best-effort

bin/              # CLI scripts (denied via .htaccess)
```

**Multi-edition model**: `editions` table holds one row per year with `is_current=1` for the active one. All editorial tables (`schedule_*`, `organizers`, `rules`, `food_items`, `sleep_options`, `iscrizioni`) carry `edition_id` FK with `ON DELETE CASCADE`. The "only one current at a time" invariant is enforced at the app layer, not by a DB constraint (MariaDB lacks partial unique indexes); `edition_make_current()` does it in a transaction. Backoffice has an edition switcher stored in session (`_admin_edition_id`); public pages always serve the `is_current` edition. `admin_users` is **not** edition-scoped.

**Roles**: `admin` and `editor`. Only `admin` can access `/admin/editions.php` and `/admin/users.php`; `editor` does CRUD on content of the selected edition. Enforced at every entry point with `auth_require('admin')`.

**Audit log**: `admin_audit` records every login, CRUD action, edition switch, password reset, CSV export, SMTP test. Uses LONGTEXT for the JSON payload (provider's MariaDB version doesn't always have functional JSON columns).

## Frontend pipeline

### Per-page pattern
Every page is a `<name>.html` + `scripts/<name>.js` pair (e.g. `iscrizione.html` ↔ `scripts/iscrizione.js`). Each HTML page:
1. Has empty `<div data-slot="topbar">` and `<div data-slot="footer">` placeholders.
2. Loads the same script tail in this order: `api/edition.js.php`, `scripts/partials.js`, `scripts/runtime.js`, `scripts/<page>.js`.
3. The page script calls `window.TAB_mountPartials('<active>')` (replaces the slots), then renders page-specific content from `window.TAB_CURRENT_EDITION`.

When adding a new page, replicate this pattern — there is no router, no templating engine.

### Data layer
**Editorial content comes from the DB via `api/edition.js.php`**, which outputs JS assigning `window.TAB_EDITION_<year> = {...}` for every published edition, plus `window.TAB_EDITIONS` (array) and `window.TAB_CURRENT_EDITION` (alias to the `is_current=1` one). The shape mirrors what the old static `data/edizione-2026.js` used to produce, so page scripts just read `window.TAB_CURRENT_EDITION.schedule.days[i].items[j]` etc.

**Important**: track names live on `day.tracks` (array of strings, indexed by `item.track`). Don't hardcode them in page scripts — read from `day.tracks`.

### Runtime (`scripts/runtime.js`)
Slim. Exposes `window.TAB.edition` / `TAB.editions` getters for debugging, and a custom smooth-scroll for in-page anchors. The old multi-tone copy system (`themes.js`, `data-copy=`, `applyTone`, `bindTone`, tweaks panel, `__activate_edit_mode` postMessage) was **removed** in favor of plain hardcoded Italian copy in the HTML.

### Iscrizione form
`scripts/iscrizione.js` does a real `fetch POST /api/iscrizione.php` with JSON body. Server validates everything (sleep_kind must match an `is_available=1` row of the active edition; price calc is server-side, never trusted from client). Honeypot field `_hp` in the form, hidden off-screen. The endpoint sends a confirmation mail via SMTP best-effort (no-op if `SMTP_HOST` is empty).

### Styling
A single `styles/global.css` for the public site (~34KB). Backoffice uses `admin/assets/admin.css` (separate file, same palette via duplicated CSS variables — admin pages don't import `global.css`). CSS custom properties define the palette (`--sky-*`, `--grass-*`, `--cream`, `--ink`, `--sun`, `--hot`, `--berry`) and fonts (`--font-display` Space Grotesk, `--font-ui` JetBrains Mono, `--font-hand` Caveat). Page-specific styles live in `<style>` blocks inside the page's HTML — keep them there rather than bloating the global stylesheet, and reuse the CSS variables.

## Notes & gotchas

- `RooT-Camp - standalone.html` and `TAB camp - standalone.html` (~5MB each) are self-contained snapshot exports of an earlier design phase. Don't edit them; they're outputs, not sources. Excluded from `--all` deploys.
- `assets/` (canonical, referenced by HTML) and `uploads/` (leftover staging) partially overlap.
- `scraps/` holds throwaway debugging images. Excluded from `--all` deploys.
- `.htaccess` is intentionally minimal: the provider's `AllowOverride` rejects `php_flag` and `<IfModule mod_expires>`, which would otherwise return HTTP 500 site-wide. Stick to `Options -Indexes` + `<FilesMatch>` + `RedirectMatch`.
- All UI copy is in Italian.
- The frontend caches `api/edition.js.php` for 60 seconds (`Cache-Control: public, max-age=60`). Backoffice changes propagate to the public site within that window.
- `data/` is no longer a source of truth — it's empty after the migration. The old `data/edizione-2026.js` and `data/themes.js` were deleted both locally and remotely.
