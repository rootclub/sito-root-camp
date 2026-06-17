-- Consenso art. 9 GDPR per allergie / regime alimentare + versione informativa.
-- Da eseguire una sola volta su prod (phpMyAdmin). Idempotente solo se eseguito
-- una volta: se le colonne esistono già MariaDB darà errore "Duplicate column".
--
-- - privacy_version:     versione dell'informativa "presa in visione" all'iscrizione.
-- - health_consent_at:   timestamp del consenso al trattamento dei dati di salute/dieta.
-- - health_consent_text: testo esatto mostrato accanto alla casella (onere della prova).

ALTER TABLE iscrizioni
  ADD COLUMN privacy_version     VARCHAR(40) DEFAULT NULL      AFTER privacy_consent_at,
  ADD COLUMN health_consent_at   TIMESTAMP   NULL DEFAULT NULL AFTER privacy_version,
  ADD COLUMN health_consent_text TEXT        DEFAULT NULL      AFTER health_consent_at;
