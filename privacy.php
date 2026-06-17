<!DOCTYPE html>
<html lang="it" data-tone="ironico">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Privacy · /RooT-Camp 2026</title>
  <meta name="robots" content="index,follow">
  <link rel="stylesheet" href="styles/global.css">
  <style>
    .page-hero {
      background: var(--cream);
      padding: 70px 0 40px;
      border-bottom: 2px dashed var(--ink);
    }
    .page-hero h1 {
      font-size: clamp(40px, 6vw, 72px);
      letter-spacing: -.025em;
      max-width: 22ch;
    }
    .page-hero .dim { max-width: 56ch; margin-top: 16px; font-size: 18px; }

    .privacy-wrap {
      max-width: 78ch;
      margin: 0 auto;
      padding: 56px 0 80px;
    }
    .privacy-wrap section { margin-bottom: 44px; }
    .privacy-wrap h2 {
      font-family: var(--font-display);
      font-size: clamp(22px, 2.6vw, 30px);
      letter-spacing: -.01em;
      margin-bottom: 14px;
      padding-top: 6px;
      border-top: 2px dashed rgba(15,42,26,.18);
      padding-top: 22px;
    }
    .privacy-wrap section:first-of-type h2 { border-top: none; padding-top: 0; }
    .privacy-wrap p,
    .privacy-wrap li {
      font-family: var(--font-body, var(--font-ui));
      font-size: 16px;
      line-height: 1.65;
      color: var(--ink);
    }
    .privacy-wrap p { margin-bottom: 12px; }
    .privacy-wrap ul { padding-left: 22px; margin-bottom: 12px; }
    .privacy-wrap li { margin-bottom: 6px; }
    .privacy-wrap a {
      color: var(--ink);
      text-decoration: underline;
      text-underline-offset: 3px;
    }
    .privacy-wrap a:hover { color: var(--hot); }

    .kv-table {
      border: 2px solid var(--ink);
      border-radius: var(--r-md);
      overflow: hidden;
      box-shadow: 4px 4px 0 var(--ink);
      background: var(--cream);
      margin: 16px 0 8px;
    }
    .kv-table dl {
      display: grid;
      grid-template-columns: minmax(180px, 28%) 1fr;
    }
    .kv-table dt,
    .kv-table dd {
      padding: 12px 16px;
      border-top: 1px solid rgba(15,42,26,.12);
      font-family: var(--font-ui);
      font-size: 14px;
      margin: 0;
    }
    .kv-table dt {
      font-weight: 700;
      letter-spacing: .04em;
      text-transform: uppercase;
      font-size: 11px;
      background: rgba(15,42,26,.04);
      color: var(--ink-dim);
    }
    .kv-table dl > dt:first-of-type,
    .kv-table dl > dd:first-of-type { border-top: none; }

    .meta-row {
      font-family: var(--font-ui);
      font-size: 12px;
      color: var(--ink-dim);
      letter-spacing: .04em;
      margin-top: 32px;
      padding-top: 18px;
      border-top: 2px dashed rgba(15,42,26,.18);
    }
  </style>
  <template id="__bundler_thumbnail" data-bg-color="#fffef5">
    <svg viewBox="0 0 1200 800" xmlns="http://www.w3.org/2000/svg">
      <rect width="1200" height="800" fill="#fffef5"/>
      <text x="600" y="430" font-family="Space Grotesk, sans-serif" font-size="120" font-weight="800" text-anchor="middle" fill="#0f2a1a">Privacy</text>
    </svg>
  </template>
  <?php require __DIR__ . '/inc/jsonld_event.php'; ?>
</head>
<body>
  <div data-slot="topbar"></div>

  <section class="page-hero">
    <div class="wrap">
      <div class="sec-eyebrow">informativa ex art. 13 reg. (UE) 2016/679</div>
      <h1>Informativa sul trattamento dei dati personali.</h1>
      <p class="dim">
        La presente informativa è resa ai sensi e per gli effetti dell'art. 13 del Regolamento
        (UE) 2016/679 del Parlamento europeo e del Consiglio del 27 aprile 2016 (di seguito, "GDPR")
        nonché del D.Lgs. 30 giugno 2003, n. 196, come novellato dal D.Lgs. 10 agosto 2018, n. 101,
        in favore dei soggetti i cui dati personali sono trattati dal Titolare in occasione del
        procedimento di iscrizione all'evento e della successiva esecuzione del rapporto.
        In ossequio al combinato disposto dell'art. 122 del D.Lgs. 196/2003 e dell'art. 5, paragrafo 3,
        della Direttiva 2002/58/CE, non sono installati cookie di profilazione, né strumenti di analisi
        statistica del traffico, né tecnologie funzionalmente equivalenti.
      </p>
    </div>
  </section>

  <main class="wrap">
    <div class="privacy-wrap">

      <section>
        <h2>Identità e dati di contatto del Titolare del trattamento</h2>
        <div class="kv-table">
          <dl>
            <dt>Titolare</dt>
            <dd>Associazione Root APS</dd>
            <dt>Contatto privacy</dt>
            <dd><a href="mailto:presidente@rootclub.it">presidente@rootclub.it</a></dd>
          </dl>
        </div>
        <p>
          L'Interessato può rivolgere ogni istanza, richiesta o comunicazione concernente il
          trattamento dei propri dati personali al recapito di posta elettronica sopra indicato.
          Il Titolare si impegna a fornire riscontro senza ingiustificato ritardo e, comunque,
          entro il termine massimo di trenta giorni dal ricevimento dell'istanza, ai sensi e
          per gli effetti dell'art. 12, paragrafo 3, del GDPR, salva la facoltà di proroga del
          predetto termine di ulteriori due mesi, ove ciò sia reso necessario dalla complessità
          o dal numero delle richieste, previa adeguata motivazione comunicata all'Interessato.
        </p>
      </section>

      <section>
        <h2>Categorie di dati personali oggetto di trattamento</h2>
        <p>
          In occasione della trasmissione del modulo di iscrizione, accessibile alla URL
          <a href="iscrizione.php">/iscrizione</a>, formano oggetto di trattamento le seguenti
          categorie di dati personali:
        </p>
        <ul>
          <li><strong>Dati identificativi e di contatto</strong>: nominativo o pseudonimo, indirizzo di posta elettronica e, in via facoltativa, recapito telefonico, conferiti dall'Interessato ai fini della gestione del procedimento di iscrizione e dell'instaurazione delle conseguenti comunicazioni di servizio;</li>
          <li><strong>Fascia di età indicativa</strong> (maggiorenne, minorenne accompagnato dall'esercente la responsabilità genitoriale, ovvero "preferisco non specificare"), conferita per finalità di natura organizzativa e di sicurezza dei partecipanti;</li>
          <li><strong>Dati relativi alle modalità di partecipazione</strong>: opzione di pernottamento prescelta, numero di tessere prepagate, eventuali annotazioni libere e aree tematiche di interesse, conferiti in funzione delle esigenze logistiche e di predisposizione del palinsesto;</li>
          <li><strong>Categorie particolari di dati personali (art. 9 GDPR)</strong>: le informazioni in materia di allergie e di regime alimentare seguito, conferite in via meramente facoltativa, costituiscono dati relativi alla salute e possono altresì rivelare convinzioni religiose o filosofiche dell'Interessato; al loro trattamento è dedicata l'apposita sezione che segue, fondata sullo specifico consenso dell'Interessato;</li>
          <li><strong>Dati tecnici di connessione</strong>: indirizzo IP e <em>user-agent</em> del programma di navigazione utilizzato al momento dell'invio del modulo, trattati per finalità di sicurezza informatica, prevenzione di abusi e diagnostica del sistema;</li>
          <li><strong>Dati attinenti al rilascio dei consensi</strong>: data e ora della presa visione della presente informativa e relativa versione, nonché, ove prestato, data e ora del consenso al trattamento dei dati di cui all'art. 9 unitamente al testo esatto della dichiarazione di consenso sottoscritta, trattati al fine di consentire al Titolare l'assolvimento dell'onere della prova di cui all'art. 7, paragrafo 1, del GDPR.</li>
        </ul>
        <p>
          Il Titolare, conscio della centralità del valore della tutela dei dati personali quale diritto
          fondamentale dell'individuo riconosciuto e garantito dall'art. 8 della Carta dei diritti
          fondamentali dell'Unione europea, nel più scrupoloso e devoto ottemperamento al combinato
          disposto dell'art. 5, paragrafo 3, della Direttiva 2002/58/CE (cd. "ePrivacy"), come modificata
          dalla Direttiva 2009/136/CE, dell'art. 122 del D.Lgs. 30 giugno 2003, n. 196 (Codice in materia
          di protezione dei dati personali), come novellato dal D.Lgs. 10 agosto 2018, n. 101, nonché in
          piena adesione alle "Linee guida sull'utilizzo di cookie e di altri strumenti di tracciamento"
          adottate dall'Autorità Garante per la protezione dei dati personali con provvedimento n. 231
          del 10 giugno 2021 (pubblicato in G.U. n. 163 del 9 luglio 2021), e tenuto altresì conto della
          consolidata giurisprudenza della Corte di Giustizia dell'Unione europea in materia
          (<em>ex multis</em>, sentenza Planet49, C-673/17, del 1° ottobre 2019),
          <strong>attesta solennemente</strong> che il presente sito web istituzionale non installa,
          né consente l'installazione da parte di soggetti terzi, di cookie di profilazione di prima o
          terza parte, cookie analitici (a mero titolo esemplificativo e non esaustivo: Google Analytics,
          Meta Pixel, Hotjar, Matomo, Adobe Analytics e affini), cookie di marketing, di retargeting o
          di social media plug-in, né impiega tecniche di <em>fingerprinting</em>, <em>web beacon</em>,
          pixel di tracciamento, <em>local storage</em> a fini di profilazione o qualsivoglia ulteriore
          tecnologia funzionalmente equivalente ai cookie ai sensi della normativa <em>de qua</em>.
        </p>
        <p>
          L'unico cookie tecnico eventualmente installato è il cookie di sessione associato all'accesso
          al pannello di amministrazione riservato, la cui installazione è esente dall'obbligo di acquisizione
          del preventivo consenso dell'interessato ai sensi dell'art. 122, comma 1, del D.Lgs. 196/2003 e
          dell'art. 5, paragrafo 3, secondo periodo, della Direttiva 2002/58/CE, in quanto strettamente
          necessario alla fornitura del servizio della società dell'informazione esplicitamente richiesto
          dall'amministratore autenticato. Conseguentemente, nessun dato personale è oggetto di comunicazione,
          diffusione o cessione a terze parti per finalità commerciali, di marketing o ulteriori rispetto
          a quelle puntualmente indicate nella presente informativa.
        </p>
      </section>

      <section>
        <h2>Finalità del trattamento e basi giuridiche di liceità ai sensi dell'art. 6 GDPR</h2>
        <p>I dati personali sono oggetto di trattamento per le seguenti finalità, in forza delle corrispondenti basi giuridiche di liceità:</p>
        <ul>
          <li>
            <strong>Gestione della procedura di iscrizione e partecipazione all'evento</strong> — base giuridica: esecuzione di misure precontrattuali adottate su richiesta dell'Interessato e successiva esecuzione del rapporto, ai sensi dell'art. 6, paragrafo 1, lettera b), del GDPR.
          </li>
          <li>
            <strong>Esecuzione delle comunicazioni di servizio</strong> afferenti all'evento (conferma dell'avvenuta iscrizione, informazioni logistiche, comunicazione di eventuali variazioni organizzative) — medesima base giuridica di cui al punto che precede.
          </li>
          <li>
            <strong>Tutela della sicurezza informatica, prevenzione di condotte abusive e diagnostica del sistema</strong> (trattamento dell'indirizzo IP e dello <em>user-agent</em>) — base giuridica: perseguimento del legittimo interesse del Titolare alla protezione delle proprie infrastrutture tecnologiche, ai sensi dell'art. 6, paragrafo 1, lettera f), del GDPR, previo positivo bilanciamento con i diritti e le libertà fondamentali dell'Interessato.
          </li>
          <li>
            <strong>Adempimento di obblighi di legge</strong> cui è soggetto il Titolare, ivi incluso il riscontro a richieste legittimamente formulate dall'Autorità giudiziaria o da pubbliche amministrazioni — base giuridica: art. 6, paragrafo 1, lettera c), del GDPR.
          </li>
        </ul>
        <p>
          Si dà espressamente atto che il Titolare <strong>non pone in essere alcuna attività di
          marketing diretto</strong>: non procede all'iscrizione dell'Interessato a servizi di
          <em>newsletter</em>, non cede i dati personali a terze parti per finalità promozionali, né
          trasmette comunicazioni a carattere pubblicitario. La sola corrispondenza elettronica
          intrattenuta con l'Interessato è quella strettamente funzionale al procedimento di iscrizione.
        </p>
      </section>

      <section>
        <h2>Trattamento di categorie particolari di dati: allergie e regime alimentare (art. 9 GDPR)</h2>
        <p>
          Il campo del modulo di iscrizione destinato all'indicazione di <strong>allergie e del regime
          alimentare seguito</strong> è di compilazione <strong>meramente facoltativa</strong>. Le
          informazioni ivi eventualmente conferite integrano <strong>dati relativi alla salute</strong> e,
          potendo una determinata scelta alimentare disvelare <strong>convinzioni religiose o filosofiche</strong>
          dell'Interessato, sono in ogni caso riconducibili alle <strong>categorie particolari di dati
          personali</strong> di cui all'art. 9, paragrafo 1, del GDPR, come tali assistite da una tutela rafforzata.
        </p>
        <p>
          <strong>Base giuridica.</strong> Il trattamento dei predetti dati è fondato esclusivamente sul
          <strong>consenso esplicito</strong> manifestato dall'Interessato, ai sensi del combinato disposto
          dell'art. 6, paragrafo 1, lettera a), e dell'art. 9, paragrafo 2, lettera a), del GDPR. Il consenso
          è raccolto, al momento dell'invio del modulo, mediante una <strong>casella di spunta separata,
          specifica e non preselezionata</strong>, distinta dalla dichiarazione di presa visione della presente
          informativa e dall'accettazione del regolamento, e si rende visibile unicamente qualora il campo
          relativo alle allergie o al regime alimentare risulti effettivamente valorizzato.
        </p>
        <p>
          <strong>Finalità.</strong> I dati sono trattati al solo fine di garantire la <strong>sicurezza
          alimentare</strong> dell'Interessato e di consentire la <strong>corretta organizzazione logistica
          del servizio di ristorazione</strong> (catering) durante lo svolgimento dell'evento.
        </p>
        <p>
          <strong>Modalità.</strong> Il trattamento è effettuato con modalità in parte automatizzate e in parte
          manuali, da parte dei soli volontari e responsabili dell'organizzazione espressamente autorizzati,
          nel rispetto dei principi di minimizzazione e di limitazione della conservazione. I dati non sono
          oggetto di diffusione né di comunicazione a terzi.
        </p>
        <p>
          <strong>Natura facoltativa e assenza di condizionamento.</strong> Il conferimento di tali dati e la
          prestazione del relativo consenso sono <strong>del tutto facoltativi</strong>: l'iscrizione si
          perfeziona regolarmente anche in difetto della loro compilazione e il rifiuto non determina alcuna
          conseguenza pregiudizievole, salva l'impossibilità per il Titolare di tenere conto delle specifiche
          esigenze alimentari non comunicate. Il consenso è <strong>revocabile in qualsiasi momento</strong>,
          con la medesima agevolezza con cui è stato prestato, mediante semplice comunicazione all'indirizzo
          <a href="mailto:presidente@rootclub.it">presidente@rootclub.it</a>, senza che ciò pregiudichi la
          liceità del trattamento effettuato anteriormente alla revoca (art. 7, paragrafi 3 e 4, del GDPR).
        </p>
      </section>

      <section>
        <h2>Periodo di conservazione dei dati personali (art. 5, par. 1, lett. e, GDPR)</h2>
        <p>
          In ossequio al principio di limitazione della conservazione di cui all'art. 5, paragrafo 1,
          lettera e), del GDPR, i dati personali raccolti in sede di iscrizione sono conservati per il
          tempo strettamente necessario al conseguimento delle finalità per le quali sono stati raccolti
          e, segnatamente, per un periodo non superiore a <strong>dodici (12) mesi decorrenti dalla data
          di conclusione dell'evento</strong>, termine ritenuto congruo dal Titolare al fine di consentire
          la gestione di eventuali richieste di rimborso, di contestazioni, di contenziosi e di
          comunicazioni post-evento. Decorso il predetto termine, i dati personali sono
          <strong>cancellati ovvero resi anonimi in via irreversibile</strong>.
        </p>
        <p>
          In deroga al termine generale che precede e in applicazione del principio di minimizzazione, i
          <strong>dati relativi alla salute e al regime alimentare</strong> (categorie particolari ex art. 9
          GDPR) sono soggetti a un <strong>distinto e più breve termine di conservazione</strong> e vengono
          <strong>cancellati alla conclusione dell'evento</strong>, essendo il loro trattamento funzionale alle
          sole esigenze di sicurezza alimentare e di organizzazione del servizio di ristorazione durante il
          medesimo. La revoca del consenso prestato dall'Interessato comporta in ogni caso la cancellazione
          anticipata dei predetti dati.
        </p>
        <p>
          Sono fatti salvi i diversi e più ampi termini di conservazione previsti da specifiche
          disposizioni normative cui il Titolare è tenuto a conformarsi: a titolo meramente esemplificativo,
          la documentazione contabile e fiscale, ove dovuta, è conservata per il periodo di dieci anni ai
          sensi dell'art. 2220 del codice civile e della pertinente normativa tributaria.
        </p>
      </section>

      <section>
        <h2>Categorie di destinatari e trasferimento dei dati verso Paesi terzi</h2>
        <p>
          I dati personali sono accessibili e trattati, nei limiti delle rispettive competenze, dalle
          seguenti categorie di destinatari:
        </p>
        <ul>
          <li>i volontari e i responsabili preposti all'organizzazione dell'evento, espressamente autorizzati dal Titolare al trattamento ai sensi dell'art. 29 del GDPR e dell'art. 2-<em>quaterdecies</em> del D.Lgs. 196/2003, esclusivamente per le finalità indicate nella presente informativa;</li>
          <li>il fornitore dei servizi di <em>hosting</em> del sito web (rootclub.it / Plesk), debitamente designato Responsabile del trattamento ai sensi dell'art. 28 del GDPR, la cui infrastruttura server è ubicata sul territorio della Repubblica italiana, con conseguente insussistenza di qualsivoglia trasferimento di dati al di fuori dello Spazio Economico Europeo;</li>
          <li>il fornitore del servizio SMTP preposto al recapito della comunicazione di conferma dell'iscrizione, parimenti designato Responsabile del trattamento ex art. 28 del GDPR, limitatamente ai dati personali strettamente necessari all'erogazione del servizio (nominativo, indirizzo di posta elettronica, contenuto della comunicazione);</li>
          <li>l'Autorità giudiziaria e le pubbliche amministrazioni, esclusivamente in ottemperanza a specifici obblighi di legge ovvero a provvedimenti legittimamente adottati dalle medesime.</li>
        </ul>
        <p>
          <strong>I dati personali non sono oggetto di trasferimento verso Paesi terzi al di fuori
          dell'Unione europea o dello Spazio Economico Europeo, né verso organizzazioni internazionali</strong>,
          ai sensi e per gli effetti del Capo V del GDPR. Il Titolare, parimenti, non procede ad alcuna
          forma di cessione, vendita o comunicazione dei dati personali a soggetti terzi per finalità
          di marketing proprio o di terzi.
        </p>
      </section>

      <section>
        <h2>Diritti dell'Interessato (artt. 15–22 e 77 del GDPR)</h2>
        <p>
          L'Interessato può esercitare in qualsiasi momento, mediante istanza inoltrata all'indirizzo
          di posta elettronica <a href="mailto:presidente@rootclub.it">presidente@rootclub.it</a>,
          i diritti riconosciuti dagli articoli da 15 a 22 del GDPR, e segnatamente:
        </p>
        <ul>
          <li>il <strong>diritto di accesso</strong> ai dati personali che lo riguardano e di ottenerne copia, ai sensi dell'art. 15;</li>
          <li>il <strong>diritto di rettifica</strong> dei dati personali inesatti, nonché di integrazione di quelli incompleti, ai sensi dell'art. 16;</li>
          <li>il <strong>diritto alla cancellazione</strong> ("diritto all'oblio") dei dati personali, qualora ricorra una delle ipotesi tassativamente previste dall'art. 17 e non sussistano motivi legittimi prevalenti per la loro ulteriore conservazione;</li>
          <li>il <strong>diritto di limitazione</strong> del trattamento, nelle ipotesi previste dall'art. 18;</li>
          <li>il <strong>diritto alla portabilità</strong> dei dati personali forniti al Titolare, in un formato strutturato, di uso comune e leggibile da dispositivo automatico, ai sensi dell'art. 20;</li>
          <li>il <strong>diritto di opposizione</strong> al trattamento fondato sul perseguimento del legittimo interesse del Titolare, ai sensi dell'art. 21;</li>
          <li>il <strong>diritto di revocare il consenso</strong> prestato al trattamento dei dati relativi alla salute e al regime alimentare (art. 9 GDPR), in qualsiasi momento e con la medesima agevolezza con cui è stato prestato, senza che ciò pregiudichi la liceità del trattamento effettuato sulla base del consenso prestato anteriormente alla revoca, ai sensi dell'art. 7, paragrafo 3, del GDPR; alla revoca consegue la cancellazione dei dati interessati.</li>
        </ul>
        <p>
          È fatto in ogni caso salvo il diritto di proporre <strong>reclamo all'Autorità Garante per la
          protezione dei dati personali</strong>
          (<a href="https://www.garanteprivacy.it" target="_blank" rel="noopener noreferrer">www.garanteprivacy.it</a>),
          ai sensi dell'art. 77 del GDPR, ovvero di adire la competente Autorità giurisdizionale ai sensi
          dell'art. 79 del medesimo Regolamento, qualora l'Interessato ritenga che il trattamento dei
          propri dati personali avvenga in violazione delle disposizioni in materia.
        </p>
      </section>

      <section>
        <h2>Natura del conferimento dei dati e conseguenze del rifiuto</h2>
        <p>
          Il conferimento dei dati personali contrassegnati quali obbligatori nel modulo di iscrizione
          riveste <strong>carattere necessario</strong> ai fini del perfezionamento del procedimento di
          iscrizione e dell'instaurazione del relativo rapporto: l'eventuale rifiuto, totale o parziale,
          di fornire i predetti dati comporta l'oggettiva impossibilità per il Titolare di dare seguito
          alla richiesta dell'Interessato. Il conferimento dei restanti dati personali riveste invece
          carattere meramente <strong>facoltativo</strong> e il loro mancato inserimento non produce alcuna
          conseguenza pregiudizievole in capo all'Interessato, fatta salva una eventuale minore efficacia
          nella predisposizione organizzativa da parte del Titolare.
        </p>
      </section>

      <section>
        <h2>Processo decisionale automatizzato e profilazione (art. 22 GDPR)</h2>
        <p>
          Il Titolare non effettua, nei confronti dell'Interessato, alcun processo decisionale fondato
          unicamente sul trattamento automatizzato dei dati personali, ivi compresa la profilazione,
          che produca effetti giuridici nei suoi confronti ovvero che incida in modo analogo
          significativamente sulla sua persona, ai sensi e per gli effetti dell'art. 22 del GDPR.
        </p>
      </section>

      <section>
        <h2>Aggiornamenti alla presente informativa</h2>
        <p>
          Il Titolare si riserva la facoltà di apportare modifiche e integrazioni alla presente
          informativa, anche al fine di recepire sopravvenute esigenze organizzative ovvero mutamenti
          del quadro normativo o giurisprudenziale di riferimento. La versione tempo per tempo vigente
          è resa costantemente disponibile presso il presente indirizzo URL. Le modifiche che rivestano
          carattere sostanziale e siano rilevanti rispetto al trattamento dei dati personali
          dell'Interessato saranno tempestivamente partecipate ai soggetti iscritti mediante posta
          elettronica.
        </p>
      </section>

      <p class="meta-row">
        Versione della presente informativa in vigore alla data del <?= htmlspecialchars(PRIVACY_VERSION_LABEL, ENT_QUOTES, 'UTF-8') ?>.
      </p>
    </div>
  </main>

  <div data-slot="footer"></div>

  <script src="api/edition.js.php"></script>
  <script src="scripts/partials.js"></script>
  <script src="scripts/runtime.js"></script>
  <script>
    window.TAB_mountPartials('privacy');
  </script>
</body>
</html>
