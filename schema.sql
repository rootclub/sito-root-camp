-- /RooT-Camp — schema database (MariaDB 10.5+)
-- Importare via phpMyAdmin nel database scelto.
-- Charset: utf8mb4. Engine: InnoDB.
--
-- Per i dati iniziali dell'edizione 2026, importa anche seed-2026.sql.
-- Per creare il primo amministratore: imposta SETUP_TOKEN nel .env e visita /admin/setup.php?token=XXX

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS admin_audit;
DROP TABLE IF EXISTS iscrizione_meals;
DROP TABLE IF EXISTS iscrizioni;
DROP TABLE IF EXISTS meal_slots;
DROP TABLE IF EXISTS sleep_options;
DROP TABLE IF EXISTS food_items;
DROP TABLE IF EXISTS rules;
DROP TABLE IF EXISTS sponsors;
DROP TABLE IF EXISTS organizers;
DROP TABLE IF EXISTS schedule_day_settings;
DROP TABLE IF EXISTS schedule_items;
DROP TABLE IF EXISTS schedule_tracks;
DROP TABLE IF EXISTS editions;
DROP TABLE IF EXISTS admin_users;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- editions: una riga per anno. Esattamente una con is_current=1.
-- (l'unicità di is_current=1 è garantita lato app, non da indice
-- parziale che MariaDB non supporta nativamente.)
-- ============================================================
CREATE TABLE editions (
  id               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  year             SMALLINT UNSIGNED NOT NULL,
  slug             VARCHAR(40)       NOT NULL,
  name             VARCHAR(120)      NOT NULL,
  subtitle         VARCHAR(120)      DEFAULT NULL,

  is_current       TINYINT(1)        NOT NULL DEFAULT 0,
  is_published     TINYINT(1)        NOT NULL DEFAULT 0,
  registrations_open TINYINT(1)      NOT NULL DEFAULT 0,

  date_start       DATE              NOT NULL,
  date_end         DATE              NOT NULL,
  date_setup       DATE              DEFAULT NULL,
  date_teardown    DATE              DEFAULT NULL,
  date_label       VARCHAR(80)       NOT NULL,
  date_label_short VARCHAR(60)       DEFAULT NULL,

  loc_name         VARCHAR(120)      NOT NULL,
  loc_city         VARCHAR(120)      DEFAULT NULL,
  loc_region       VARCHAR(120)      DEFAULT NULL,
  loc_venue_note   TEXT              DEFAULT NULL,

  ticket_price_eur SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  ticket_label     VARCHAR(40)       DEFAULT '15 €',
  ticket_note      TEXT              DEFAULT NULL,
  ticket_cards_note TEXT             DEFAULT NULL,
  card_price_eur   SMALLINT UNSIGNED NOT NULL DEFAULT 10,

  contact_email    VARCHAR(180)      DEFAULT NULL,
  contact_matrix   VARCHAR(180)      DEFAULT NULL,
  contact_telegram VARCHAR(180)      DEFAULT NULL,
  contact_mastodon VARCHAR(180)      DEFAULT NULL,

  food_intro       TEXT              DEFAULT NULL,
  sleep_intro      TEXT              DEFAULT NULL,

  hero_video_url   VARCHAR(255)      DEFAULT NULL,
  hero_poster_url  VARCHAR(255)      DEFAULT NULL,

  -- Parole/frasi del marquee sotto la hero (una per riga; vuoto = default JS)
  marquee_words    TEXT              DEFAULT NULL,

  created_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uniq_year (year),
  UNIQUE KEY uniq_slug (slug),
  KEY idx_published (is_published),
  KEY idx_current (is_current)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- schedule_tracks: piste/sale per edizione.
-- position 0..N preserva l'ordine di visualizzazione.
-- ============================================================
CREATE TABLE schedule_tracks (
  id         INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  edition_id INT UNSIGNED      NOT NULL,
  position   TINYINT UNSIGNED  NOT NULL,
  name       VARCHAR(80)       NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_edition_pos (edition_id, position),
  CONSTRAINT fk_tracks_edition FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- schedule_items
-- ============================================================
CREATE TABLE schedule_items (
  id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  edition_id   INT UNSIGNED      NOT NULL,
  day_date     DATE              NOT NULL,
  day_label    VARCHAR(40)       NOT NULL,
  start_time   TIME              NOT NULL,
  duration_min SMALLINT UNSIGNED NOT NULL DEFAULT 50,
  track_id     INT UNSIGNED      NOT NULL,
  kind         ENUM('opening','talk','workshop','music','kids','food','closing','other') NOT NULL DEFAULT 'talk',
  title        VARCHAR(200)      NOT NULL,
  speaker      VARCHAR(200)      DEFAULT NULL,
  description  TEXT              DEFAULT NULL,
  notes        TEXT              DEFAULT NULL,
  sort         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at   TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_edition_day_time (edition_id, day_date, start_time),
  KEY idx_track (track_id),
  CONSTRAINT fk_items_edition FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_track   FOREIGN KEY (track_id)   REFERENCES schedule_tracks(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- schedule_day_settings: visibilità del giorno nella preview home.
-- Riga assente => default visibile (true).
-- Usata principalmente per nascondere giorni di setup/teardown.
-- ============================================================
CREATE TABLE schedule_day_settings (
  id                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  edition_id           INT UNSIGNED  NOT NULL,
  day_date             DATE          NOT NULL,
  show_in_home_preview TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_edition_day (edition_id, day_date),
  CONSTRAINT fk_dsett_edition FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- organizers
-- ============================================================
CREATE TABLE organizers (
  id             INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  edition_id     INT UNSIGNED      NOT NULL,
  name           VARCHAR(160)      NOT NULL,
  role           VARCHAR(160)      DEFAULT NULL,
  photo_url      VARCHAR(255)      DEFAULT NULL,
  is_placeholder TINYINT(1)        NOT NULL DEFAULT 0,
  link_url       VARCHAR(255)      DEFAULT NULL,
  sort           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_edition (edition_id, sort),
  CONSTRAINT fk_orgs_edition FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sponsors (sponsor tecnici di edizione)
-- ============================================================
CREATE TABLE sponsors (
  id         INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  edition_id INT UNSIGNED      NOT NULL,
  name       VARCHAR(160)      NOT NULL,
  logo_url   VARCHAR(255)      DEFAULT NULL,
  link_url   VARCHAR(255)      DEFAULT NULL,
  sort       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_edition (edition_id, sort),
  CONSTRAINT fk_sponsors_edition FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- rules
-- ============================================================
CREATE TABLE rules (
  id         INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  edition_id INT UNSIGNED      NOT NULL,
  icon       VARCHAR(40)       NOT NULL DEFAULT 'ticket',
  title      VARCHAR(160)      NOT NULL,
  body       TEXT              NOT NULL,
  sort       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_edition (edition_id, sort),
  CONSTRAINT fk_rules_edition FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- food_items
-- ============================================================
CREATE TABLE food_items (
  id         INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  edition_id INT UNSIGNED      NOT NULL,
  label      VARCHAR(80)       NOT NULL,
  note       VARCHAR(255)      DEFAULT NULL,
  sort       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_edition (edition_id, sort),
  CONSTRAINT fk_food_edition FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sleep_options
-- ============================================================
CREATE TABLE sleep_options (
  id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  edition_id   INT UNSIGNED      NOT NULL,
  kind         ENUM('camping','indoor','offsite','other') NOT NULL,
  title        VARCHAR(120)      NOT NULL,
  body         TEXT              NOT NULL,
  price_eur    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  -- is_available qui significa "selezionabile/prenotabile dal cliente":
  -- l'opzione è SEMPRE mostrata nel form, ma se 0 appare come "Esaurito"
  -- e non è selezionabile.
  is_available TINYINT(1)        NOT NULL DEFAULT 1,
  sort         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_edition (edition_id, sort),
  CONSTRAINT fk_sleep_edition FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- meal_slots: pasti prenotabili per edizione (headcount cucina).
-- "code" è uno slug stabile usato dal frontend (es. "thu_lunch").
-- "label" è testo libero, può includere il prezzo a titolo informativo
-- (es. "Cena Spaghetti al tonno (10€)").
-- ============================================================
CREATE TABLE meal_slots (
  id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  edition_id   INT UNSIGNED      NOT NULL,
  code         VARCHAR(40)       NOT NULL,
  label        VARCHAR(160)      NOT NULL,
  day_date     DATE              DEFAULT NULL,
  is_available TINYINT(1)        NOT NULL DEFAULT 1,
  sort         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_edition_code (edition_id, code),
  KEY idx_edition (edition_id, sort),
  CONSTRAINT fk_meal_edition FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- iscrizioni: registrazioni dal form pubblico.
-- edit_token: opaco (32 hex), permette modifica via /modifica.php?t=...
-- ============================================================
CREATE TABLE iscrizioni (
  id            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  edition_id    INT UNSIGNED      NOT NULL,
  name          VARCHAR(160)      NOT NULL,
  email         VARCHAR(180)      NOT NULL,
  phone         VARCHAR(40)       DEFAULT NULL,
  age           ENUM('adult','minor') NOT NULL DEFAULT 'adult',
  sleep_kind    VARCHAR(40)       NOT NULL DEFAULT 'camping',
  n_cards       TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  ticket_eur    SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  sleep_eur     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  cards_eur     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  total_eur     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  diet          VARCHAR(255)      DEFAULT NULL,
  notes         TEXT              DEFAULT NULL,
  edit_token    CHAR(32)          NOT NULL,
  checked_in    TINYINT(1)        NOT NULL DEFAULT 0,
  checked_in_at TIMESTAMP         NULL DEFAULT NULL,
  ip            VARCHAR(45)       DEFAULT NULL,
  user_agent    VARCHAR(255)      DEFAULT NULL,
  privacy_consent_at TIMESTAMP    NULL DEFAULT NULL,
  created_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_edit_token (edit_token),
  KEY idx_edition_created (edition_id, created_at),
  KEY idx_email (email),
  KEY idx_checked (edition_id, checked_in),
  CONSTRAINT fk_iscr_edition FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- iscrizione_meals: ponte iscrizione ↔ meal_slots.
-- ============================================================
CREATE TABLE iscrizione_meals (
  iscrizione_id INT UNSIGNED NOT NULL,
  meal_slot_id  INT UNSIGNED NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (iscrizione_id, meal_slot_id),
  KEY idx_meal (meal_slot_id),
  CONSTRAINT fk_im_iscr FOREIGN KEY (iscrizione_id) REFERENCES iscrizioni(id) ON DELETE CASCADE,
  CONSTRAINT fk_im_meal FOREIGN KEY (meal_slot_id)  REFERENCES meal_slots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- admin_users: account backoffice (NON legati a edizione).
-- ruoli: admin (può gestire utenti ed edizioni), editor (no).
-- ============================================================
CREATE TABLE admin_users (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  username      VARCHAR(60)   NOT NULL,
  email         VARCHAR(180)  DEFAULT NULL,
  password_hash VARCHAR(255)  NOT NULL,
  role          ENUM('admin','editor') NOT NULL DEFAULT 'editor',
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  last_login_at TIMESTAMP     NULL DEFAULT NULL,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- admin_audit: trail delle azioni admin (utile in caso di errori).
-- ============================================================
CREATE TABLE admin_audit (
  id         BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED     DEFAULT NULL,
  username   VARCHAR(60)      DEFAULT NULL,
  action     VARCHAR(80)      NOT NULL,
  entity     VARCHAR(60)      DEFAULT NULL,
  entity_id  INT UNSIGNED     DEFAULT NULL,
  edition_id INT UNSIGNED     DEFAULT NULL,
  payload    LONGTEXT         DEFAULT NULL,
  ip         VARCHAR(45)      DEFAULT NULL,
  created_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user (user_id, created_at),
  KEY idx_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
