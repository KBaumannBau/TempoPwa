# ADR 0001: Monorepo mit nginx und PHP-FPM, ein Origin für PWA und API

- Status: angenommen
- Datum: 20.11.2026

## Kontext

Tempo besteht aus einer REST-API (Symfony + API Platform) und einer PWA (Vite + React). Beide ändern sich
fachlich fast immer gemeinsam: Jede Kette aus TempoVibe braucht neue API-Operationen, neu generierte Typen und
neue Bildschirme. Die anderen Projekte des Teams laufen mit nginx und PHP-FPM in Docker (Vorlage `als-crm-api`).
Die API-Platform-Distribution bringt dagegen FrankenPHP/Caddy und eigene Docker-Dateien mit.

Die PWA soll sich per Cookie anmelden (HttpOnly, SameSite) und offline stempeln können (Service Worker).

## Entscheidung

1. **Ein Repository** (`KBaumannBau/TempoPwa`) mit `api/` und `pwa/`, kein Git-Submodule. API, generierte
   Typen und Bildschirme ändern sich in einem Commit.
2. **nginx + PHP-FPM** nach dem Muster der übrigen Projekte, kein FrankenPHP, kein Caddy, keine Docker-Dateien
   der API-Platform-Distribution. Dienste `nginx`, `php`, `database`, `pwa` mit Präfix `tempo-`, Netzwerk `app_network`.
3. **Ein Origin:** nginx ist der einzige Einstieg.
   - `/api`, `/bundles`, `/_profiler`, `/_wdt` → FastCGI an `php:9000` (Document Root `api/public`)
   - `/` → dev: Proxy auf den Vite-Devserver `pwa:5173` inkl. HMR-Websocket; prod: statischer Build `pwa/dist`
     (`try_files $uri /index.html`, `sw.js`/`index.html`/Manifest ohne Cache)
   - Umschalten über `PWA_MODE=dev|prod` (`docker/nginx/start.sh`).
4. **Keine Geheimnisse in versionierten Dateien.** `compose.yaml` ist versioniert und liest alles aus `.env`
   (ignoriert); Pflichtwerte brechen den Start mit Meldung ab. Vorlage: `.env.example`.
5. Node läuft nur im Container (`node:22`), `node_modules` liegt im Bind-Mount, damit die IDE die Typen sieht.
   npm-Befehle laufen über `docker compose exec pwa …`.
6. Die PWA nutzt den **BrowserRouter** mit echten Pfaden (TempoVibe: HashRouter). nginx liefert dafür in prod
   für unbekannte Pfade `index.html`; der Service Worker schließt `/api` & Co. vom Navigations-Fallback aus.

## Folgen

- Kein CORS, Cookies und Service-Worker-Scope bleiben einfach.
- Lokal und auf dem Server gleicher Aufbau; für prod fehlt noch der Build-Schritt der PWA im Deployment
  (Betrieb noch offen, siehe `CLAUDE.md`).
- Lokal ist 8011 der Standard (`HTTP_PORT`), weil `als-crm-api` schon 8001 belegt; so laufen beide parallel.
  Im Container lauscht nginx weiter auf 8001 (`listen 8001`, `API_SCHEMA_URL`).
- Die Content-Security-Policy erlaubt `ws:`/`wss:` und `'unsafe-inline'` (Vite-HMR, Swagger UI). Für prod
  später verschärfen.
