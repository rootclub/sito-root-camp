-- Prenotazione maglietta dell'evento.
-- Da eseguire una sola volta su prod (phpMyAdmin) PRIMA di deployare il codice.
--
-- editions: impostazioni per edizione (abilitazione, foto, testo, prezzo informativo).
-- iscrizioni: taglia scelta dall'iscritto (NULL/'' = nessuna maglietta).

ALTER TABLE editions
  ADD COLUMN tshirt_enabled     TINYINT(1)   NOT NULL DEFAULT 0 AFTER marquee_words,
  ADD COLUMN tshirt_photo_url   VARCHAR(255) DEFAULT NULL       AFTER tshirt_enabled,
  ADD COLUMN tshirt_intro       TEXT         DEFAULT NULL       AFTER tshirt_photo_url,
  ADD COLUMN tshirt_price_label VARCHAR(60)  DEFAULT NULL       AFTER tshirt_intro;

ALTER TABLE iscrizioni
  ADD COLUMN tshirt_size VARCHAR(8) DEFAULT NULL AFTER notes;
