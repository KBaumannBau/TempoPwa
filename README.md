# Tempo – Stundenerfassung K. Baumann Bau

![Version](https://img.shields.io/badge/version-0.1.0-1f3a5f)
[![Deploy to Mittwald](https://github.com/KBaumannBau/TempoPwa/actions/workflows/deploy.yml/badge.svg)](https://github.com/KBaumannBau/TempoPwa/actions/workflows/deploy.yml)
![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777bb4)
![Symfony 8.1](https://img.shields.io/badge/Symfony-8.1-000000)
![React 19](https://img.shields.io/badge/React-19-61dafb)
![PostgreSQL 17](https://img.shields.io/badge/PostgreSQL-17-336791)

Tempo ist die echte Stundenerfassung für K. Baumann Bau: eine **PWA** für Handy und PC und eine **REST-API**
mit Datenbank und echter Anmeldung. Fachliche Vorlage ist die klickbare Demo **TempoVibe**. Ihre Bildschirme
und Abläufe werden hier Kette für Kette nachgebaut.

| Bereich | Wer | Was |
|---|---|---|
| Mitarbeiter-App (Handy) | Facharbeiter, Helfer, Azubis | stempeln, Tag abschließen, Anträge |
| Polier / Bauleitung (Handy) | Polier, Bauleitung | Kolonne, Freigaben, Korrekturen |
| PC / Büro | Lohnbuchhaltung, Disposition, Einkauf, Leitung, Admin | Personal, Disposition, Export ZEITERF.DAT |
| NU-App | Personen von Nachunternehmern | Stunden, Nachweise, Mindestlohnerklärung |

## Umgebungen

| | Adresse | Inhalt |
|---|---|---|
| **App (PWA)** | https://tempo-app.p-yt27xz.project.space/ | Testinstanz der PWA, Basic Auth wie bei TempoVibe |
| **API** | https://tempo-api.p-yt27xz.project.space/api/docs | REST-API mit Swagger-Oberfläche, Smoke-Test: [`/api/health`](https://tempo-api.p-yt27xz.project.space/api/health) |
| **TempoVibe** | https://tempo-vibe.p-yt27xz.project.space/ | klickbarer Prototyp, fachliche Referenz (eigenes Repo) |
| **Lokal** | http://localhost:8001 | PWA mit HMR, API unter `/api`, Profiler unter `/_profiler` |

Die laufende Version steht in der **Fußzeile der App**, als **Badge im Kopf von `/api/docs`** und im Feld
`version` von `/api/health`. Testdeploys aus `main` tragen die Commit-Kennung (`0.1.0+abc1234`), Releases nur
die Nummer (`0.1.0`).

## Aufbau

```
Tempo/
├── api/                 Symfony + API Platform (PHP 8.4), Fachlogik je Kontext unter src/<Kontext>/
├── pwa/                 Vite + React 19 + TypeScript, Bildschirme unter src/screens/{ma,polier,bau,pc,nu}
├── docker/              php (PHP-FPM), nginx, postgres – lokale Umgebung
├── deploy/mittwald/     Apache-Dateien und Server-Skript für die Testinstanz
├── docs/                Architekturentscheidungen (adr/) und Übernahme-Stand (uebernahme.md)
├── .github/workflows/   Build, Test und Deploy nach Mittwald
├── compose.yaml         lokale Dienste: nginx, php, database, pwa
├── deployment.sh        Deploy und Release über Git
├── VERSION              aktuelle Version (Quelle für API, PWA und dieses README)
└── CLAUDE.md            verbindliche Regeln für alle, die hier entwickeln
```

Ein Origin: nginx liefert lokal `/` als PWA aus (Vite-Devserver mit HMR) und gibt `/api`, `/bundles`,
`/_profiler` und `/_wdt` per FastCGI an PHP-FPM weiter. Details: [ADR 0001](docs/adr/0001-monorepo-nginx-php-fpm.md).

## Lokal starten

Voraussetzung ist nur Docker (z. B. OrbStack). PHP und Node laufen in Containern.

```bash
cp .env.example .env            # Werte setzen: APP_SECRET, POSTGRES_PASSWORD (Platzhalter „change-me“ ersetzen)
docker compose up -d            # baut das PHP-Image beim ersten Mal
docker compose exec php composer install
```

Danach:

- PWA: http://localhost:8001 (Port über `HTTP_PORT` in `.env` änderbar)
- API-Doku: http://localhost:8001/api/docs
- Health: http://localhost:8001/api/health

Der `pwa`-Container installiert die npm-Pakete beim Start selbst und startet den Vite-Devserver.

## Wichtige Befehle

| Zweck | Befehl |
|---|---|
| Symfony-Konsole | `docker compose exec php bin/console …` |
| Composer | `docker compose exec php composer …` |
| Tests (PHPUnit) | `docker compose exec php vendor/bin/phpunit` (einmalig vorher `bin/console doctrine:database:create --env=test`) |
| PWA bauen (Typecheck + Build) | `docker compose exec pwa npm run build` |
| API-Typen für die PWA erzeugen | `docker compose exec pwa npm run api:types` |
| Prod-Build lokal ansehen | `PWA_MODUS=prod docker compose up -d nginx` (vorher `npm run build`) |
| Logs | `docker compose logs -f nginx php pwa` |

Die Typen in `pwa/src/api/generated/` entstehen aus dem OpenAPI-Schema der API. Nicht von Hand ändern, nach
jeder Änderung an der API neu erzeugen.

## Deployment und Versionen

Deployt wird ausschließlich über **GitHub Actions** ([`deploy.yml`](.github/workflows/deploy.yml)).
Erst werden die API (PHPUnit gegen PostgreSQL 17) und die PWA (Typecheck und Build) gebaut. Nur wenn beides grün
ist, kopiert rsync die Pakete nach Mittwald:

- API → `/home/p-yt27xz/html/tempo-api` (danach auf dem Server: `.env.local` ergänzen, Cache, Migrationen)
- PWA → `/home/p-yt27xz/html/tempo-app`

Ausgelöst wird der Deploy mit [`deployment.sh`](deployment.sh):

```bash
./deployment.sh                         # Testinstanz: main pushen (vorher PHPUnit und PWA-Build)
./deployment.sh -m "Nachricht"          # offene Änderungen vorher committen, dann pushen
./deployment.sh release                 # Release: Patch-Version erhöhen, Commit, Tag vX.Y.Z, Push
./deployment.sh release minor           # … oder minor / major / eine feste Version wie 1.0.0
./deployment.sh --help                  # alle Optionen (--ohne-tests, --ja)
```

| Auslöser | Version | Ergebnis |
|---|---|---|
| Push auf `main` | `X.Y.Z+<commit>` | Deploy auf die Testinstanz |
| Tag `vX.Y.Z` (aus `deployment.sh release`) | `X.Y.Z` | Deploy auf die Testinstanz und GitHub-Release |

Die Version steht in der Datei [`VERSION`](VERSION). `deployment.sh release` zieht sie in diesem README
(Badge) und in `pwa/package.json` nach. Ein Tag, das nicht zu `VERSION` passt, bricht die Pipeline ab.
Ein eigenes Prod-System gibt es noch nicht. Der Betrieb ist offen, siehe [CLAUDE.md](CLAUDE.md).

### Secrets und Variablen im Repo

Settings → Secrets and variables → Actions:

| Name | Art | Zweck |
|---|---|---|
| `TEMPO_SSH_PRIVATE_KEY`, `TEMPO_SSH_HOST`, `TEMPO_SSH_USER`, `TEMPO_SSH_PORT` | Secret | Zugang zu Mittwald (wie TempoVibe) |
| `TEMPO_API_DATABASE_URL` | Secret, optional | `DATABASE_URL` für `tempo-api/.env.local`, nur wenn sie dort fehlt oder ein Platzhalter ist |
| `TEMPO_BASIC_AUTH_USER`, `TEMPO_BASIC_AUTH_PASSWORD` | Secret, optional | eigene Basic Auth vor der PWA (sonst wie TempoVibe) |
| `TEMPO_API_URL` | Variable | Adresse der API für die PWA (`https://tempo-api.p-yt27xz.project.space`) |

Geheimnisse der API liegen nur in `tempo-api/.env.local` auf dem Server. Was dort von Hand steht, bleibt
erhalten. Ein Beispiel liegt in [`deploy/mittwald/api/.env.local.example`](deploy/mittwald/api/.env.local.example)
und wird bei jedem Deploy mit kopiert. Details: [ADR 0003](docs/adr/0003-deployment-mittwald.md).

## Datenbank

- **Lokal und CI:** PostgreSQL 17 im Container.
- **Testinstanz:** Supabase-Projekt `tempo-test` (Frankfurt, PostgreSQL 17), Zugriff über den Session-Pooler mit
  TLS. **Nur Testdaten.** Im Free-Plan pausiert Supabase das Projekt nach etwa einer Woche ohne Aktivität. Dann
  meldet `/api/health` bei der Datenbank „gestoert“, und das Projekt muss im Supabase-Dashboard wieder gestartet werden.
- Zielschema: `TempoVibe/docs/datenbankschema.sql` (Schemas `se` und `protokoll`, Row Level Security). Es wird
  Kette für Kette als Doctrine-Migration übernommen.

## Wie hier gearbeitet wird

- **[CLAUDE.md](CLAUDE.md)** ist verbindlich: Architektur (DDD, DTO-Resources statt Entities, RLS), fachliche
  Regeln, Sprachen, Datengrundsätze.
- **[docs/uebernahme.md](docs/uebernahme.md)** ist die Checkliste aller Bildschirme und Ketten aus TempoVibe, mit
  den Tabellen je Kette. Reihenfolge: Anmeldung/Zugang → Stunden → Anträge → Disposition → NU → Kosten/Leitung.
- **TempoVibe** ist die fachliche Wahrheit und wird nicht verändert.
- Keine Geheimnisse ins Repo (`.env`, `.env.local`, Schlüssel). Vorlagen sind `.env.example` und
  `deploy/mittwald/api/.env.local.example`.
- Keine Namen echter Personen der Firma in Fixtures, Tests oder Beispielen.

### Architekturentscheidungen

- [ADR 0001 – Monorepo, nginx mit PHP-FPM, ein Origin](docs/adr/0001-monorepo-nginx-php-fpm.md)
- [ADR 0002 – Anmeldung mit Konten in der eigenen Datenbank statt OIDC](docs/adr/0002-anmeldung-db-statt-oidc.md)
- [ADR 0003 – Deployment nach Mittwald per GitHub Actions](docs/adr/0003-deployment-mittwald.md)
