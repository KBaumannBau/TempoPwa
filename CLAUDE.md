# CLAUDE.md – Tempo (Stundenerfassung K. Baumann Bau)

Hinweise für Claude und andere Entwickler, die in diesem Repo arbeiten.

## Worum es geht

Tempo ist die echte Stundenerfassung für eine Baufirma, bestehend aus einer PWA und einer REST-API.
Grundlage ist die klickbare Demo **TempoVibe**. Unser Auftrag hier: **alle Demos aus TempoVibe
strukturiert als echte PWA nachbauen**, mit Server, Datenbank und echter Anmeldung.

Die App hat drei Bereiche und eine Zusatz-App:

- **Mitarbeiter-App (Handy):** stempeln, Tag abschließen, Anträge
- **Polier / Bauleitung (Handy):** Kolonne, Freigaben, Korrekturen
- **PC / Büro:** Personal, Disposition, Lohnbuchhaltung, Export (ZEITERF.DAT), Einkauf, Leitung, Admin
- **NU-App:** für Personen von Nachunternehmern

## Die Referenz: TempoVibe

Pfad: `~/Developer/privat/baumann/TempoVibe` (eigenes Repo, eigenes Claude-Projekt)

- **Zugriff:** Claude hat in diesem Projekt **immer vollen Zugriff** auf das Nebenprojekt TempoVibe
  (lesen und schreiben). Dafür muss nicht extra nachgefragt werden; fehlt der Ordner in der Sitzung,
  wird er direkt mit angefordert.
- TempoVibe ist die **fachliche Wahrheit** für Abläufe, Bildschirme, Texte und Regeln. Bei Unklarheit
  zuerst dort nachsehen, dann fragen.
- Standard ist: aus TempoVibe wird gelesen und übernommen. Geändert wird dort nur, wenn es ausdrücklich verlangt ist.
- Wichtige Dateien dort:
  - `CLAUDE.md`: fachliche Regeln (unten übernommen)
  - `docs/demo-app.md`: alle Rollen, Bildschirme und die Ketten zum Ausprobieren
  - `docs/datenbankschema.sql`: PostgreSQL-Schema, Entwurf 1 (63 Tabellen, Schemas `se` und `protokoll`, RLS)
  - `src/domain/types.ts`: Datenmodell der Demo
  - `src/domain/store.tsx`: alle Aktionen, also die spätere API-Oberfläche
  - `src/domain/zeit.ts`, `lohn.ts`, `zuschlaege.ts`, `zugang.ts`: Fachlogik
  - `src/domain/seed.ts`: Demo-Daten (Vorlage für Fixtures)
  - `src/screens/{ma,polier,bau,pc,nu}`, `src/components/`: UI
  - `src/i18n/*.json`, `src/domain/texte/*.json`: Übersetzungen und Rechtstexte

## Aufbau des Repos

```
Tempo/
├── compose.yaml            # Dienste: nginx, php (PHP-FPM), database (PostgreSQL 17), pwa (Node/Vite)
├── .env.example            # Vorlage für Ports und Geheimnisse (.env selbst ist ignoriert)
├── docker/
│   ├── php/Dockerfile
│   ├── php/myPhp.ini       # gemountet als conf.d/zzz_myPhp.ini
│   ├── php/home/           # Home des Users www (Composer-Cache), ignoriert
│   ├── nginx/conf/         # nginx.conf, default.conf, pwa-dev.conf, pwa-prod.conf
│   ├── nginx/start.sh      # wählt PWA_MODUS (dev/prod), erzeugt optional Basic Auth
│   └── postgres/           # Init-Skripte (nur beim ersten Start, z. B. Erweiterungen)
├── log/                    # nginx- und PHP-Logs, ignoriert
├── api/                    # Symfony + API Platform
├── pwa/                    # Vite + React + TypeScript + vite-plugin-pwa
├── deploy/mittwald/        # Apache-Dateien und Server-Skript für die Testinstanz
├── deployment.sh           # Deploy (Push auf main) und Release (Version, Tag) über Git
├── VERSION                 # aktuelle Version, gelesen von API (Swagger, Health), PWA (Fußzeile), README (Badge)
├── README.md               # Einstieg: Umgebungen, Start, Befehle, Deployment
├── .github/workflows/      # Build, Test und Deploy nach Mittwald
├── docs/                   # Architekturentscheidungen (adr/), Offline-Konzept (pwa/), Übernahme-Stand (uebernahme.md)
└── CLAUDE.md
```

Ein Repo, kein Git-Submodule. API und PWA ändern sich gemeinsam in einem Commit.

## Technik

**Gemeinsam**
- Alles läuft über `docker compose`. Lokale PHP- oder Node-Installationen sind keine Voraussetzung.
- **Webserver ist nginx mit PHP-FPM**, wie in den anderen Projekten. Kein FrankenPHP, kein Caddy. Die
  `compose.yaml` der API-Platform-Distribution wird dafür nicht übernommen, sondern nach dem Muster der
  übrigen Projekte aufgebaut.
- Ein Origin: nginx liefert `/` als PWA aus (dev: Proxy auf den Vite-Devserver mit HMR-Websocket, prod:
  statischer Build) und `/api` per FastCGI an PHP-FPM. Kein CORS, Cookies und Service-Worker-Scope
  bleiben einfach.
- Konkrete Versionen (festgelegt beim Aufsetzen am 20.11.2026, Lockfiles sind maßgeblich):

  | Teil | Version |
  |---|---|
  | PHP | 8.4 (`php:8.4-fpm`) |
  | Symfony | 8.1 (`8.1.*`) |
  | API Platform | 5.0 (`api-platform/symfony`, `api-platform/doctrine-orm`, Tests: `api-platform/test`) |
  | Doctrine | ORM 3.8, DoctrineBundle 3.4, Migrations-Bundle 4.0 |
  | PHPUnit | 13 |
  | PostgreSQL | 17 (`postgres:17`, wie die Testinstanz bei Supabase) |
  | Node | 22 (`node:22`) |
  | Vite | 8.3 |
  | React / React DOM | 19.3 |
  | React Router | 8.5 (Paket `react-router`, BrowserRouter) |
  | TypeScript | 5.9 (nicht 7: `openapi-typescript` verlangt TypeScript 5) |
  | vite-plugin-pwa | 2.0 |
  | openapi-typescript / openapi-fetch | 7.13 / 0.17 |
  | nginx | `nginx:latest` |

- Symfony 8.x statt 7.4 LTS: Minor-Updates alle sechs Monate sind ohne Brüche, so entfällt später ein großer
  Sprung von 7.4 auf 8.4 LTS (November 2027).

**Befehle**
- Start: `cp .env.example .env` (Werte setzen), dann `docker compose up -d`; PWA unter `http://localhost:${HTTP_PORT}`.
- PHP: `docker compose exec php bin/console …`, `docker compose exec php composer …`, Tests `docker compose exec php vendor/bin/phpunit`
  (vorher einmal `bin/console doctrine:database:create --env=test`).
- PWA: `docker compose exec pwa npm run build`, Typen neu erzeugen `docker compose exec pwa npm run api:types`.
- Prod-Modus lokal prüfen: `npm run build`, dann `PWA_MODUS=prod docker compose up -d nginx`.
- Aufbau der API: je Kontext `src/<Kontext>/{Domain,Application,Infrastructure,Api}`; API-Resources (DTOs) und
  State Provider/Processor unter `Api/`, Doctrine-Entities unter `Infrastructure/Doctrine/`. Beispiel: `src/System` (Health).

**Docker-Setup (Muster wie in den anderen Projekten, Vorlage: `als-crm-api`)**
- Kein FrankenPHP, kein Caddy, keine Docker-Dateien aus der API-Platform-Distribution.
- Dienste mit festen Namen `nginx`, `php`, `database`, `pwa`; `container_name` mit Präfix `tempo-`
  (z. B. `tempo-nginx`); ein Netzwerk `app_network` (bridge).
- Ports immer über Variablen mit Default: `${HOST_IP:-0.0.0.0}:${HTTP_PORT:-8001}:8001`, ebenso für die Datenbank.
- Das ganze Repo wird nach `/var/www/html` gemountet (`:cached`), in `nginx` und `php`.
- **php:** eigenes Image `docker/php/Dockerfile` auf Basis `php:8.4-fpm`.
  - Erweiterungen nur nach Bedarf: `pdo_pgsql`, `intl`, `opcache`, `bcmath`, `zip`; dazu Xdebug, Composer, Symfony CLI.
  - Läuft als User `www` (UID/GID 1000), Port 9000, `CMD ["php-fpm"]`.
  - `docker/php/myPhp.ini` wird nach `/usr/local/etc/php/conf.d/zzz_myPhp.ini` gemountet: `date.timezone = Europe/Berlin`,
    Xdebug mit `client_host = host.docker.internal`, Port 9003, `idekey = phpstorm`.
  - `PHP_IDE_CONFIG: 'serverName=docker'` für PhpStorm.
  - `docker/php/home/` nach `/home/www` gemountet.
- **nginx:** `image: nginx:latest`, Konfiguration aus `docker/nginx/conf/{nginx.conf,default.conf}` gemountet, Logs nach `./log`.
  - `/api`, `/bundles`, `/_profiler` und `/_wdt` gehen per FastCGI an `php:9000`, Document Root ist `/var/www/html/api/public`.
  - `/` geht in dev per `proxy_pass` an `pwa:5173` (mit `Upgrade`/`Connection` für den HMR-Websocket) und
    in prod an den statischen Build `pwa/dist` (`try_files $uri /index.html`; `sw.js` und `index.html` ohne Cache).
  - Security-Header und `deny` für versteckte Dateien und Symfony-Verzeichnisse wie in der Vorlage.
  - Optional Basic Auth für Testinstanzen: `.htpasswd` wird beim Start aus `BASIC_AUTH_USERS` (`user:pass,user:pass`)
    erzeugt, wie in der Vorlage. Die App-Anmeldung läuft über ein Cookie und kommt der Basic Auth nicht in die Quere.
- **database:** `postgres:17`, Daten in einem benannten Volume, Init-Skripte aus `docker/postgres/`.
- **pwa:** `node:22`, startet `npm run dev -- --host 0.0.0.0` in `pwa/`. In prod nur Build, kein laufender Node-Dienst.
- **Geheimnisse:** Anders als in `als-crm-api` ist die `compose.yaml` hier versioniert. Darum stehen in ihr **keine**
  Passwörter, Schlüssel oder Tokens. Alles kommt aus `.env` (ignoriert) über `${VAR}` oder `env_file`,
  dazu eine `.env.example` mit Platzhaltern.

**API (`api/`)**
- Symfony + API Platform, PHP strict types, PostgreSQL 17
- DDD und Clean Architecture: Fachlogik liegt in der Domäne, nicht in Controllern oder Entities.
  - Doctrine-Entities sind **keine** API-Resources. Die API arbeitet mit eigenen DTO-Resources und
    State Providers / Processors.
  - Fachliche Aktionen (z. B. „Tag senden“, „Tag freigeben“, „Antrag genehmigen“) sind eigene Operationen
    mit eigenem Processor, kein generisches PATCH auf Status-Felder.
- Datenbank nach `docs/datenbankschema.sql` aus TempoVibe. Das Schema wird in Doctrine-Migrationen
  überführt; Abweichungen werden in `docs/` begründet.
- **Row Level Security:** Die App verbindet sich mit der Rolle `se_app` und setzt **je Anfrage**
  `app.person_id`, `app.rolle` und `app.warum` (siehe Schema, Funktion `se.ich()` und Protokoll-Trigger).
  Das passiert zentral (Doctrine-Middleware / Request-Listener), nie verstreut im Code. Die Rechte prüft
  die Datenbank, die App prüft zusätzlich über Voter.
- Tests: PHPUnit; API-Tests über den API-Platform-Test-Client.

**PWA (`pwa/`)**
- Vite, React 19, React Router, TypeScript strict, `vite-plugin-pwa`
- API-Typen werden aus dem OpenAPI-Schema der API **generiert** (`src/api/generated/`, nicht von Hand ändern).
- Offline: Stempeln muss ohne Netz funktionieren. Stempelungen landen in einer lokalen Warteschlange
  (IndexedDB) und werden nachgesendet (Herkunft `nachgesendet`). Der Server ist führend.
  Verbindliches Konzept: `docs/pwa/offline.md` (Ereignisse mit Client-UUID statt Zustandsabgleich,
  idempotente Operationen, ein Besitzer je Tag, fachliche Klärfälle statt technischer Konflikte).
- App-Icons: `pwa/scripts/icons.mjs` (weißes „K.“ auf `#0077c8`), Installation auf dem Handy im README.
- Die Struktur der Bildschirme aus TempoVibe bleibt erhalten: `src/screens/{ma,polier,bau,pc,nu}`.
- Der Zustand aus `store.tsx` (localStorage) wird durch API-Aufrufe ersetzt. Kein localStorage für Fachdaten.
- `npm run build` (Typecheck und Build) muss fehlerfrei durchlaufen.

## Vorgehen beim Übernehmen der Demos

Bildschirm für Bildschirm, entlang der Ketten aus `docs/demo-app.md`:

1. Kette wählen (z. B. Stunden: stempeln → Polier → Bauleitung → Lohnbuchhaltung → ZEITERF.DAT).
2. Benötigte Tabellen aus dem Schema als Migration und Entities anlegen.
3. Fachlogik aus `src/domain/*.ts` **auf den Server** holen (Domäne in `api/`). Berechnungen für Lohn,
   Zuschläge, Pausen, Feiertage, Konto und Export laufen auf dem Server. Die PWA zeigt nur an
   und rechnet höchstens zur Vorschau.
4. API-Operationen anlegen, OpenAPI-Typen neu generieren.
5. Bildschirm aus TempoVibe übernehmen und an die API anschließen.
6. Kette im Browser durchklicken und mit der Demo vergleichen.
7. Stand in `docs/uebernahme.md` abhaken (welcher Bildschirm, welche Kette, was fehlt).

Reihenfolge-Vorschlag: Anmeldung/Zugang → Stunden-Kette → Anträge → Disposition → NU → Kosten/Leitung.

## Sprachen

- Texte laufen über `tx('Deutscher Text')`. Der deutsche Text ist der Schlüssel.
- Übersetzungen in `pwa/src/i18n/{en,fr,hr,it,pl,ro}.json` (aus TempoVibe übernehmen). Jeder neue `tx`-Text in
  Mitarbeiter- und NU-Bildschirmen braucht einen Eintrag in allen sechs Dateien.
- Polier, Bauleitung und PC bleiben deutsch.
- Längere Hilfe- und Rechtstexte stehen in `texte/*.json`. In der echten App kommen Rechtstexte versioniert
  aus der Datenbank (`se.rechtstext`, Bestätigungen in `se.bestaetigung`).
- Die **Datenschutztexte** sind Rechtstexte. Sie werden nur nach Freigabe durch die Geschäftsleitung oder
  den Datenschutzbeauftragten geändert.

## Grundsätze der Daten (aus dem Datenbankkonzept)

1. **Nichts wird überschrieben.** Korrekturen sind neue Einträge, das Original bleibt und verweist auf
   seinen Ersatz. Kontostände sind nur Buchungen.
2. **Jeder sieht nur, was er braucht.** Die Datenbank prüft die Rechte selbst (RLS), nicht nur die App.
3. **Jede Datenart bekommt beim Entstehen ein Löschdatum.**
- Zeitpunkte als `timestamptz` in UTC, der Arbeitstag zusätzlich als `date` nach deutscher Zeit.
- Keine Standort-Koordinate der Person, nur das Ergebnis des Vergleichs mit der Baustelle.
- PIN, Passwort und Anmelde-Code nur als Hash.
- Dateien in der getrennten Dateiablage, in der Datenbank nur der Steckbrief.
- Das Änderungsprotokoll (`protokoll.eintrag`) ist nur anfügbar.

## Fachliche Regeln (vom Auftraggeber festgelegt)

**Tagesabschluss im Handy.** Nach „Arbeitsende“ kommen zwei Schritte:
1. Zuschläge wählen
2. Tag prüfen, dann an den Prüfer (Polier) senden

Fahrtkosten, Verpflegung und Wegezeit trägt der Mitarbeiter nicht ein. Die Lohnbuchhaltung ermittelt sie
aus den Zeiten und der Baustelle.

**Freigabe nach dem Vier-Augen-Prinzip.**
- Niemand gibt seinen eigenen Tag frei: Den Tag eines Poliers gibt die Bauleitung frei, den Tag eines
  Bauleiters die Oberbauleitung.
- Wer Zeiten nachträgt, gibt sie nicht frei. Der Mitarbeiter muss nachgetragene Zeiten bestätigen.
- Bei einem Baustellenwechsel gibt jeder Polier die Stunden seiner eigenen Baustelle frei.
- Diese Regeln setzt der **Server** durch, nicht nur die Oberfläche.

**Anträge.** Urlaub, Frei vom Konto und Arbeitsbefreiung gehen an die Oberbauleitung. Urlaub geht danach
noch an die Geschäftsführung (2. Stufe).

**Zuschläge.** Nach BRTV Bau (Logik in `zuschlaege.ts`). Jeder Info-Text sagt eindeutig, wann der Zuschlag
bezahlt wird und wann nicht. Nachtzeit 20–05 Uhr, Feiertage Bayern.

**Export.** ZEITERF.DAT: 55 Zeichen je Zeile, CRLF, Windows-1252. Ein zweiter Export im selben Monat ist ein
Nachtrag. Wird auf dem Server erzeugt.

**Onboarding.**
- Der Mitarbeiter bekommt per Post einen Brief mit Einmal-Passwort (Format XXXX-XXXX, 14 Tage gültig).
- Bei der ersten Anmeldung legt er sofort ein eigenes Passwort fest.
- Danach bleibt das Handy angemeldet (in der echten App über ein langlebiges, widerrufbares Geräte-Token,
  `se.geraet`).
- Die PIN gibt es nur noch für Mitarbeiter ohne Handy.
- Passwörter nur als Hash auf dem Server.

## Anmeldung

**Entschieden:** Keine OIDC/Entra-Anmeldung. Die TempoVibe-README beschreibt noch SSO über Entra ID mit
Token-Exchange, das ist **gekippt** und wird nicht gebaut. Vorerst gibt es eine normale Anmeldung mit
Konten in der eigenen Datenbank.

- Konten und Zugänge nach Schema: `se.zugang`, `se.einladung`, `se.anmeldecode`, `se.geraet`,
  `se.zweiter_faktor`. Symfony Security mit eigenem User Provider auf diese Tabellen.
- Passwörter, PIN und Codes nur als Hash (Symfony PasswordHasher, `auto`).
- Handy: Einmal-Passwort aus dem Brief → eigenes Passwort → das Gerät bleibt angemeldet über ein
  langlebiges, widerrufbares Geräte-Token (`se.geraet`). PIN nur für Mitarbeiter ohne Handy.
- PC: E-Mail und Passwort, zweiter Faktor (TOTP/Passkey) laut Schema. Sperre nach 5 Fehlversuchen wie in der Demo.
- Sitzung über ein HttpOnly-, Secure-, SameSite-Cookie (gleicher Origin). Keine Tokens im localStorage.
- Login-Rate-Limit (Symfony RateLimiter) und Protokoll der Anmeldungen.
- Die Anmeldung ist hinter einer Schnittstelle gekapselt, damit später ein SSO-Anbieter ergänzt werden kann,
  ohne die Fachlogik anzufassen.

## Wortwahl und Daten

- Sag „Lohnbuchhaltung“, nicht den Namen des Dienstleisters.
- Sag „IT“ für den IT-Dienstleister.
- Keine Namen echter Personen der Firma in Fixtures, Tests oder Beispielen. Die erfundenen Personen aus
  `seed.ts` dürfen als Fixtures übernommen werden.

## Git und Geheimnisse

- Remote: `https://github.com/KBaumannBau/TempoPwa.git` (das Repo heißt historisch „TempoPwa“, enthält aber
  das ganze Monorepo mit API und PWA). Das leere Repo `TempoRest` wird nicht verwendet.
- **Niemals committen oder pushen.** Änderungen bleiben im Working Tree; committet wird von Hand.
- Keine Zugangsdaten, Schlüssel oder `.env`-Werte ins Repo. Nur `.env.example` mit Platzhaltern.
- Die Demo TempoVibe deployt bei jedem Push auf `main` automatisch auf die Testadresse (Mittwald, GitHub
  Actions).
- **Tempo deployt ebenso bei jedem Push auf `main`** (`.github/workflows/deploy.yml`, ADR 0003): erst API bauen und
  testen sowie PWA bauen, nur wenn beides grün ist per rsync nach Mittwald:
  API → `/home/p-yt27xz/html/tempo-api`, PWA → `/home/p-yt27xz/html/tempo-app`. Ein Push ist also sofort live.
  - Secrets wie in TempoVibe: `TEMPO_SSH_PRIVATE_KEY`, `TEMPO_SSH_HOST`, `TEMPO_SSH_USER`, `TEMPO_SSH_PORT`;
    optional `TEMPO_BASIC_AUTH_USER`/`TEMPO_BASIC_AUTH_PASSWORD` (sonst Basic Auth kbaumann/kbaumann wie in TempoVibe)
    und die Variable `TEMPO_API_URL`.
  - Apache-Dateien und Server-Skript für Mittwald liegen in `deploy/mittwald/`.
  - **Versionen:** Quelle ist `VERSION`. Push auf `main` deployt `X.Y.Z+<commit>`, ein Tag `vX.Y.Z` deployt `X.Y.Z`
    und legt ein GitHub-Release an. `./deployment.sh release [patch|minor|major|X.Y.Z]` erhöht die Version
    (VERSION, README-Badge, `pwa/package.json`), committet, taggt und pusht – das Skript führt der Mensch aus,
    nicht Claude (keine Commits/Pushes durch Claude).
  - Geheimnisse der API nur in `tempo-api/.env.local` auf dem Server. Der Deploy legt sie an bzw. ergänzt nur fehlende
    Werte und Platzhalter: `APP_SECRET` entsteht auf dem Server, `DATABASE_URL` kommt aus dem Secret
    `TEMPO_API_DATABASE_URL`, CORS und `DEFAULT_URI` aus dem Workflow. Von Hand Geändertes bleibt erhalten.
    Das Beispiel `deploy/mittwald/api/.env.local.example` wird mit auf den Server synchronisiert.

## Offene Punkte

- [ ] Betrieb: On-Prem-VM (laut README); Pipeline für die Testinstanz bei Mittwald ist eingerichtet (ADR 0003)
- [ ] Mittwald: Domains für tempo-app/tempo-api (ein Origin mit Pfad `/api`?)
- Datenbank der Testinstanz: Supabase-Projekt `tempo-test` (Region eu-central-1, PostgreSQL 17), Verbindung über den
  Session-Pooler `aws-0-eu-central-1.pooler.supabase.com:5432` mit TLS (ADR 0003). Nur Testdaten.
- [ ] Offene Punkte aus dem Schema: Verschlüsselung NU-Stundensätze, RLS für `ausweis_sichtung`,
      wer setzt Löschsperren, restliche RLS-Regeln (Kosten, Disposition), Aufbewahrung 8 oder 10 Jahre
- [ ] Format ZEITERF.DAT, Nachtrag und Stunden über 9,99 mit der Lohnbuchhaltung bestätigen
- [ ] Noch nicht gebaut in der Demo: E-Mail und Push, Abruf der Krankschreibung, PDF (Stundenzettel,
      Nachweis für den Bauherrn)
- [ ] Fristen und Texte mit „Vorschlag“ oder „rechtlich prüfen“ sind noch nicht bestätigt
