-- /RooT-Camp — seed iniziale edizione 2026
-- Da eseguire UNA VOLTA dopo schema.sql, se vuoi precaricare i dati attuali.
-- Idempotente solo se la tabella editions è vuota: se rilanciato fallisce per UNIQUE(year).

SET NAMES utf8mb4;

-- ============================================================
-- Edizione
-- ============================================================
INSERT INTO editions (
  year, slug, name, subtitle,
  is_current, is_published, registrations_open,
  date_start, date_end, date_setup, date_teardown, date_label, date_label_short,
  loc_name, loc_city, loc_region, loc_venue_note,
  ticket_price_eur, ticket_label, ticket_note, ticket_cards_note, card_price_eur,
  contact_email, contact_matrix, contact_telegram, contact_mastodon,
  food_intro, sleep_intro,
  hero_video_url
) VALUES (
  2026, '2026', 'RooT-Camp 2026', 'prima edizione',
  1, 1, 1,
  '2026-07-10', '2026-07-11', '2026-07-09', '2026-07-12',
  '10 — 11 — 12 luglio 2026', '10–11 lug 2026',
  'Fratta Terme', 'Bertinoro (FC)', 'Emilia-Romagna',
  'prato a fianco della struttura — tende benvenute',
  15, '15 €',
  'biglietto minimo d''ingresso — include il braccialetto di riconoscimento',
  'cibo, bevande e gadget si pagano con tessere prepagate a foratura',
  10,
  'camp@rootclub.it', '#tab-camp:matrix.org', '@tabcamp', '@tab@mastodon.uno',
  'Cucina da campo, birra artigianale, caffè decente.',
  'Due modi per dormire.',
  'assets/hero.mp4'
);

SET @ed := LAST_INSERT_ID();

-- ============================================================
-- Tracks
-- ============================================================
INSERT INTO schedule_tracks (edition_id, position, name) VALUES
  (@ed, 0, 'Sala A'),
  (@ed, 1, 'Sala B'),
  (@ed, 2, 'Prato');

SET @t0 := (SELECT id FROM schedule_tracks WHERE edition_id=@ed AND position=0);
SET @t1 := (SELECT id FROM schedule_tracks WHERE edition_id=@ed AND position=1);
SET @t2 := (SELECT id FROM schedule_tracks WHERE edition_id=@ed AND position=2);

-- ============================================================
-- Schedule (placeholder TBD)
-- ============================================================
INSERT INTO schedule_items
  (edition_id, day_date, day_label, start_time, duration_min, track_id, kind, title, speaker)
VALUES
  (@ed, '2026-07-10', 'Venerdì 10', '16:00', 60,  @t0, 'opening',  'Check-in & braccialetti',                 NULL),
  (@ed, '2026-07-10', 'Venerdì 10', '17:00', 50,  @t0, 'talk',     'Keynote d''apertura',                     '— TBD —'),
  (@ed, '2026-07-10', 'Venerdì 10', '17:00', 50,  @t1, 'workshop', 'Workshop: saldatura per principianti',    '— TBD —'),
  (@ed, '2026-07-10', 'Venerdì 10', '18:00', 50,  @t0, 'talk',     'Self-hosting in famiglia',                '— TBD —'),
  (@ed, '2026-07-10', 'Venerdì 10', '18:00', 90,  @t2, 'kids',     'Laboratorio per bambini',                 '— TBD —'),
  (@ed, '2026-07-10', 'Venerdì 10', '19:30', 90,  @t2, 'food',     'Cena — griglia accesa',                   NULL),
  (@ed, '2026-07-10', 'Venerdì 10', '21:00', 60,  @t0, 'talk',     'Lightning talks (aperti)',                'chiunque'),
  (@ed, '2026-07-10', 'Venerdì 10', '22:00', 180, @t2, 'music',    'DJ set — fino a tardi',                   '— TBD —'),

  (@ed, '2026-07-11', 'Sabato 11',  '09:00', 60,  @t2, 'food',     'Colazione',                               NULL),
  (@ed, '2026-07-11', 'Sabato 11',  '10:00', 50,  @t0, 'talk',     'Privacy per chi non è paranoico',         '— TBD —'),
  (@ed, '2026-07-11', 'Sabato 11',  '10:00', 110, @t1, 'workshop', 'Workshop: Home Assistant da zero',        '— TBD —'),
  (@ed, '2026-07-11', 'Sabato 11',  '11:00', 50,  @t0, 'talk',     'Reti mesh e comunità',                    '— TBD —'),
  (@ed, '2026-07-11', 'Sabato 11',  '12:30', 90,  @t2, 'food',     'Pranzo',                                  NULL),
  (@ed, '2026-07-11', 'Sabato 11',  '14:30', 50,  @t0, 'talk',     'Hardware hacking: smontiamo una cosa',    '— TBD —'),
  (@ed, '2026-07-11', 'Sabato 11',  '14:30', 110, @t1, 'workshop', 'Workshop: introduzione a Linux',          '— TBD —'),
  (@ed, '2026-07-11', 'Sabato 11',  '16:00', 50,  @t0, 'talk',     'Archivio digitale e memoria',             '— TBD —'),
  (@ed, '2026-07-11', 'Sabato 11',  '17:00', 50,  @t0, 'talk',     'Sovranità tecnologica locale',            '— TBD —'),
  (@ed, '2026-07-11', 'Sabato 11',  '18:00', 60,  @t2, 'kids',     'Giochi nel prato',                        NULL),
  (@ed, '2026-07-11', 'Sabato 11',  '19:30', 90,  @t2, 'food',     'Cena sociale',                            NULL),
  (@ed, '2026-07-11', 'Sabato 11',  '21:00', 90,  @t0, 'closing',  'Closing & premiazioni lightning',         NULL),
  (@ed, '2026-07-11', 'Sabato 11',  '22:30', 180, @t2, 'music',    'Live + DJ — in the wild',                 '— TBD —');

-- ============================================================
-- Organizers
-- ============================================================
INSERT INTO organizers (edition_id, name, role, is_placeholder, sort) VALUES
  (@ed, 'Associazione Root APS',           'organizzazione principale', 0, 10),
  (@ed, 'Comune di Bertinoro',             'con il patrocinio di',      0, 20),
  (@ed, 'Biblioteca GIBS',                 'partner',                   0, 30),
  (@ed, '— altre in via di definizione —', 'placeholder',               1, 40);

-- ============================================================
-- Rules
-- ============================================================
INSERT INTO rules (edition_id, icon, title, body, sort) VALUES
  (@ed, 'ticket', 'Biglietto minimo',
   'L''ingresso richiede l''acquisto di un biglietto minimo. In cambio ricevi un braccialetto di riconoscimento: tienilo al polso, è la tua chiave per tutto il camp.', 10),
  (@ed, 'card', 'Tessere a foratura',
   'Cibo, bevande e gadget non si pagano in contanti. Compri una tessera prepagata e la fori per ogni consumazione. Zero code, zero cassa.', 20),
  (@ed, 'clock', 'Fino alle 23 family-friendly',
   'Dalle mattina alle 23 è un posto per tutti: bambini, famiglie, volumi ragionevoli, contenuti adatti a chiunque.', 30),
  (@ed, 'moon', 'Dopo le 23: in the wild',
   'Dalle 23 in poi cambia clima. Musica più alta, talk meno istituzionali, birre in mano. Non è più il momento dei bambini.', 40),
  (@ed, 'volume', 'Niente silenzio notturno',
   'Non garantiamo il silenzio nelle ore notturne. Se dormi in tenda e sei dormiglione, tappi per le orecchie obbligatori. Ti avvertiamo.', 50),
  (@ed, 'tent', 'Campeggio nel prato',
   'Puoi piantare la tenda nel prato a fianco della struttura. Porta il tuo sacco a pelo, materassino e un minimo di buonsenso.', 60);

-- ============================================================
-- Food
-- ============================================================
INSERT INTO food_items (edition_id, label, note, sort) VALUES
  (@ed, 'Colazione',   'caffè, brioche, frutta',                   10),
  (@ed, 'Pranzo/cena', 'piatti semplici, opzione veg garantita',   20),
  (@ed, 'Birra',       'spina, artigianale locale',                30),
  (@ed, 'Snack',       'per i momenti tra un talk e l''altro',     40);

-- ============================================================
-- Sleep
-- ============================================================
INSERT INTO sleep_options (edition_id, kind, title, body, price_eur, sort) VALUES
  (@ed, 'camping', 'Tenda nel prato',
   'Gratis con il biglietto. Porti tu la tenda, il sacco a pelo, il materassino. Bagni e docce nella struttura.',
   0, 10),
  (@ed, 'offsite', 'Alloggio esterno',
   'Se preferisci un B&B/hotel, Fratta Terme e Bertinoro sono piene di opzioni. Ci arrivi in macchina in 5 minuti.',
   0, 20);

-- ============================================================
-- Meal slots (prenotazione pasti — solo headcount per la cucina)
-- Il prezzo, se presente, va dentro il label (es. "(10 €)").
-- ============================================================
INSERT INTO meal_slots (edition_id, code, label, day_date, sort) VALUES
  (@ed, 'thu_lunch',     'Pranzo · Giovedì 9 luglio',                   '2026-07-09', 10),
  (@ed, 'fri_lunch',     'Pranzo · Venerdì 10 luglio',                  '2026-07-10', 20),
  (@ed, 'fri_dinner',    'Cena · Venerdì 10 luglio',                    '2026-07-10', 30),
  (@ed, 'sat_breakfast', 'Colazione · Sabato 11 luglio',                '2026-07-11', 40),
  (@ed, 'sat_lunch',     'Pranzo · Sabato 11 luglio',                   '2026-07-11', 50),
  (@ed, 'sat_dinner',    'Cena · Sabato 11 luglio',                     '2026-07-11', 60),
  (@ed, 'sat_grill',     'Grigliata di mezzanotte · Sabato 11 luglio',  '2026-07-11', 70),
  (@ed, 'sun_breakfast', 'Colazione · Domenica 12 luglio',              '2026-07-12', 80),
  (@ed, 'sun_lunch',     'Pranzo · Domenica 12 luglio',                 '2026-07-12', 90);
