-- /RooT-Camp — migration: prenotazione pasti + edit token
-- Da eseguire UNA VOLTA in phpMyAdmin sul DB di produzione.
-- Idempotente solo in parte: ALTER fallisce se la colonna esiste già — droppa
-- e rilancia solo i pezzi che servono.
--
-- Cambiamenti:
--   1) iscrizioni: aggiunta edit_token (UNIQUE), diet, updated_at
--   2) Backfill edit_token per le iscrizioni esistenti
--   3) Nuova tabella meal_slots (pasti prenotabili per edizione)
--   4) Nuova tabella iscrizione_meals (ponte iscrizione ↔ meal_slots)
--   5) Seed dei 9 pasti per l'edizione 2026

SET NAMES utf8mb4;

-- ============================================================
-- 1) iscrizioni: nuove colonne
-- ============================================================
ALTER TABLE iscrizioni
  ADD COLUMN edit_token CHAR(32)      NOT NULL DEFAULT '' AFTER notes,
  ADD COLUMN diet       VARCHAR(255)  DEFAULT NULL          AFTER notes,
  ADD COLUMN updated_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- ============================================================
-- 2) Backfill edit_token random per ogni iscrizione esistente.
--    MariaDB non ha bin2hex/random_bytes nativo, ma LPAD(HEX(RANDOM_BYTES(16)),32,'0')
--    funziona da MariaDB 10.10+. Per compatibilità usiamo MD5(UUID()+id).
-- ============================================================
UPDATE iscrizioni
   SET edit_token = MD5(CONCAT(UUID(), '-', id, '-', UNIX_TIMESTAMP(NOW(6))))
 WHERE edit_token = '' OR edit_token IS NULL;

-- Solo dopo il backfill aggiungiamo l'UNIQUE.
ALTER TABLE iscrizioni
  ADD UNIQUE KEY uniq_edit_token (edit_token);

-- ============================================================
-- 3) meal_slots
-- ============================================================
CREATE TABLE IF NOT EXISTS meal_slots (
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
-- 4) iscrizione_meals
-- ============================================================
CREATE TABLE IF NOT EXISTS iscrizione_meals (
  iscrizione_id INT UNSIGNED NOT NULL,
  meal_slot_id  INT UNSIGNED NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (iscrizione_id, meal_slot_id),
  KEY idx_meal (meal_slot_id),
  CONSTRAINT fk_im_iscr FOREIGN KEY (iscrizione_id) REFERENCES iscrizioni(id) ON DELETE CASCADE,
  CONSTRAINT fk_im_meal FOREIGN KEY (meal_slot_id)  REFERENCES meal_slots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5) Seed 9 pasti per l'edizione 2026.
--    NB: usa il year per agganciarsi all'edizione, in modo che la migration
--    funzioni anche se l'id dell'edizione non è 1.
-- ============================================================
SET @ed26 := (SELECT id FROM editions WHERE year = 2026 LIMIT 1);

INSERT INTO meal_slots (edition_id, code, label, day_date, sort) VALUES
  (@ed26, 'thu_lunch',     'Pranzo · Giovedì 9 luglio',                   '2026-07-09', 10),
  (@ed26, 'fri_lunch',     'Pranzo · Venerdì 10 luglio',                  '2026-07-10', 20),
  (@ed26, 'fri_dinner',    'Cena · Venerdì 10 luglio',                    '2026-07-10', 30),
  (@ed26, 'sat_breakfast', 'Colazione · Sabato 11 luglio',                '2026-07-11', 40),
  (@ed26, 'sat_lunch',     'Pranzo · Sabato 11 luglio',                   '2026-07-11', 50),
  (@ed26, 'sat_dinner',    'Cena · Sabato 11 luglio',                     '2026-07-11', 60),
  (@ed26, 'sat_grill',     'Grigliata di mezzanotte · Sabato 11 luglio',  '2026-07-11', 70),
  (@ed26, 'sun_breakfast', 'Colazione · Domenica 12 luglio',              '2026-07-12', 80),
  (@ed26, 'sun_lunch',     'Pranzo · Domenica 12 luglio',                 '2026-07-12', 90);

-- Done.
