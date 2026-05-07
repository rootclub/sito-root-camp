-- Aggiunge la colonna marquee_words alla tabella editions.
-- Da eseguire una sola volta su prod (phpMyAdmin).
-- Una frase per riga; vuoto/NULL => fallback JS al set di default.

ALTER TABLE editions
  ADD COLUMN marquee_words TEXT DEFAULT NULL AFTER hero_poster_url;

-- Seed opzionale per l'edizione 2026 (commenta se non desiderato):
UPDATE editions
   SET marquee_words = '/RooT-Camp 2026
10-11 LUGLIO
FRATTA TERME
HACKER CAMP
TALK
WORKSHOP
BIRRA
PRATO
TENDA
FAMILY FRIENDLY (fino alle 11)
IN THE WILD (dopo)
PRIMA EDIZIONE'
 WHERE year = 2026;
