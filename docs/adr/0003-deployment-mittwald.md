# ADR 0003: Deployment nach Mittwald per GitHub Actions

- Status: angenommen (Testinstanz)
- Datum: 20.11.2026

## Kontext

Wie TempoVibe soll jeder Push auf `main` automatisch auf die Testinstanz bei Mittwald gehen. Das Repo enthält
API und PWA; auf dem Server liegen sie in zwei Ordnern. Mittwald liefert über Apache aus (`.htaccess`), nicht
über unser nginx.

## Entscheidung

Workflow `.github/workflows/deploy.yml` mit drei Jobs:

1. **api:** PHP 8.4, `composer install`, PHPUnit gegen PostgreSQL 16 (Service-Container), `lint:container` für prod;
   danach `composer install --no-dev`, `assets:install`, Apache-Dateien aus `deploy/mittwald/api/` einfügen, packen.
2. **app:** Node 22, `npm ci`, `npm run build` (Typecheck + Vite), `.htaccess` aus `deploy/mittwald/app/htaccess.vorlage`,
   optional Basic Auth (Secrets `TEMPO_BASIC_AUTH_USER`/`_PASSWORD`).
3. **deploy** (nur wenn beide grün): rsync über SSH (`easingthemes/ssh-deploy`, Secrets `TEMPO_SSH_*` wie in TempoVibe)
   - API → `/home/p-yt27xz/html/tempo-api/`, danach auf dem Server `nach-dem-deploy.sh`
     (`.env.local` beim ersten Mal anlegen, Cache aufbauen, Migrationen, falls die Datenbank erreichbar ist)
   - PWA → `/home/p-yt27xz/html/tempo-app/`

Geheimnisse der API (`APP_SECRET`, `DATABASE_URL`, `CORS_ALLOW_ORIGIN`) liegen nur in `tempo-api/.env.local`
auf dem Server; rsync löscht sie nie. `tempo-api/.htaccess` leitet alles nach `public/`, damit Code und `.env.local`
auch dann nicht erreichbar sind, wenn die Domain auf `tempo-api` statt auf `tempo-api/public` zeigt.

## Folgen

- Zwei Ordner heißt meist zwei Origins. Das weicht von ADR 0001 ab (ein Origin). Bis das geklärt ist:
  die PWA bekommt die API-Adresse beim Build (`vars.TEMPO_API_URL` → `VITE_API_URL`), die API lässt den Origin
  der PWA per `CORS_ALLOW_ORIGIN` zu. Für die Cookie-Anmeldung (ADR 0002) sollten beide unter derselben Domain
  liegen (Pfad `/api` auf `tempo-api/public`) oder zumindest unter Subdomains derselben Site (SameSite=Lax).
- Die Datenbank muss PostgreSQL 16 sein. Ob Mittwald sie im Projekt bereitstellt, ist offen.
