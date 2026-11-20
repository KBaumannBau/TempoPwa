# ADR 0003: Deployment nach Mittwald per GitHub Actions

- Status: angenommen (Testinstanz)
- Datum: 20.11.2026

## Kontext

Wie TempoVibe soll jeder Push auf `main` automatisch auf die Testinstanz bei Mittwald gehen. Das Repo enthält
API und PWA; auf dem Server liegen sie in zwei Ordnern. Mittwald liefert über Apache aus (`.htaccess`), nicht
über unser nginx.

## Entscheidung

Workflow `.github/workflows/deploy.yml` mit drei Jobs:

1. **api:** PHP 8.4, `composer install`, PHPUnit gegen PostgreSQL 17 (Service-Container), `lint:container` für prod;
   danach `composer install --no-dev`, `assets:install`, Apache-Dateien aus `deploy/mittwald/api/` einfügen, packen.
2. **app:** Node 22, `npm ci`, `npm run build` (Typecheck + Vite), `.htaccess` aus `deploy/mittwald/app/htaccess.vorlage`,
   Basic Auth wie in TempoVibe (Standard kbaumann/kbaumann, eigene Werte über die Secrets `TEMPO_BASIC_AUTH_USER`/`_PASSWORD`).
3. **deploy** (nur wenn beide grün): rsync über SSH (`easingthemes/ssh-deploy`, Secrets `TEMPO_SSH_*` wie in TempoVibe)
   - API → `/home/p-yt27xz/html/tempo-api/`, danach auf dem Server `nach-dem-deploy.sh`
     (`.env.local` beim ersten Mal anlegen, Cache aufbauen, Migrationen, falls die Datenbank erreichbar ist)
   - PWA → `/home/p-yt27xz/html/tempo-app/`

Geheimnisse der API (`APP_SECRET`, `DATABASE_URL`, `CORS_ALLOW_ORIGIN`) liegen nur in `tempo-api/.env.local`
auf dem Server; rsync löscht sie nie. Der Deploy-Job schreibt eine `.env.local.erstanlage` (DATABASE_URL aus dem
Secret `TEMPO_API_DATABASE_URL`, CORS und `DEFAULT_URI` aus dem Workflow); `nach-dem-deploy.sh` übernimmt daraus nur
fehlende Schlüssel oder Platzhalter (`change-me`), erzeugt `APP_SECRET` auf dem Server und löscht die Datei wieder.
Von Hand gepflegte Werte bleiben unangetastet. `.env.local.example` wird bei jedem Deploy mit synchronisiert. `tempo-api/.htaccess` leitet alles nach `public/`, damit Code und `.env.local`
auch dann nicht erreichbar sind, wenn die Domain auf `tempo-api` statt auf `tempo-api/public` zeigt.

## Folgen

- Zwei Ordner heißt meist zwei Origins. Das weicht von ADR 0001 ab (ein Origin). Bis das geklärt ist:
  die PWA bekommt die API-Adresse beim Build (`vars.TEMPO_API_URL` → `VITE_API_URL`), die API lässt den Origin
  der PWA per `CORS_ALLOW_ORIGIN` zu. Für die Cookie-Anmeldung (ADR 0002) sollten beide unter derselben Domain
  liegen (Pfad `/api` auf `tempo-api/public`) oder zumindest unter Subdomains derselben Site (SameSite=Lax).
- Das Mittwald-Webhosting-Projekt hat kein Container-Hosting und kein PostgreSQL. Entschieden (20.11.2026): Die
  Testinstanz nutzt **Supabase** (Projekt `tempo-test`, Region eu-central-1/Frankfurt, PostgreSQL 17), nur mit
  Testdaten. Verbindung über den Session-Pooler (IPv4) mit TLS (`sslmode=require`); die Daten-API von Supabase wird
  nicht genutzt und ist abgeschaltet. Kein MySQL, damit RLS, Trigger und Protokoll von Anfang an mitgetestet werden.
- Lokal (Docker) und CI laufen deshalb ebenfalls auf PostgreSQL 17.
- Supabase ist ein US-Unternehmen. Für Prod mit Echtdaten (Lohn, Krankmeldungen) kommt eine andere Datenbank
  (On-Prem oder EU-Anbieter mit Auftragsverarbeitungsvertrag).
- Free-Plan: Supabase pausiert Projekte nach etwa einer Woche ohne Aktivität; dann im Dashboard wieder starten.
