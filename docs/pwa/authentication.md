# Anmeldung in der PWA: online zuerst, danach auch offline

Stand: 21.11.2026 · gehört zu ADR 0002 (Konten in der eigenen Datenbank), ADR 0004 (Geräte-Token statt JWT) und `offline.md` (Abschnitt 6).
Tabellen heißen im Code englisch (Schema `tempo`, Zuordnung in `docs/database/naming.md`); Abschnitt 2 nennt noch die Namen aus dem TempoVibe-Schema.

## 1. Anforderung

- Der erste Bildschirm der App ist die Anmeldung.
- Die **erste** Anmeldung auf einem Handy geht **nur online**. Der Server prüft Benutzername und Passwort
  bzw. das Einmal-Passwort aus dem Brief.
- Danach soll man sich in der App **auch ohne Netz** anmelden können.
- Vom Auftraggeber festgelegt: Nach der Registrierung **bleibt das Handy angemeldet**.

## 2. Was das Datenbankkonzept schon hergibt (TempoVibe `docs/datenbankschema.sql`)

| Tabelle | Zweck für die Anmeldung | Bemerkung |
|---|---|---|
| `se.person` | Benutzername = `pers_nr`, `nutzt_app`, `gesperrt_am` | NU-Personen haben keine `pers_nr`, brauchen eine eigene Kennung (offen) |
| `se.zugang` | `status`, `passwort_hash`, `pin_hash`, `fehlversuche`, `gesperrt_bis`, `sprache`, `registriert_am` | Kommentar „`passwort_hash` nur PC-Rollen“ ist überholt: Seit dem Brief-Verfahren hat **jeder** App-Nutzer ein eigenes Passwort. Umgesetzt in `tempo.account.password_hash`. |
| `se.einladung` | Brief mit Einmal-Passwort: `token_hash`, `gueltig_bis` (14 Tage), `genutzt_am`, `gedruckt = true` | wie in `porting-status.md` vorgeschlagen; ein neuer Brief setzt die alten auf ungültig |
| `se.anmeldecode` | 6-stelliger Code, 15 Minuten | für die Anmeldung per Code, hier nicht gebraucht |
| `se.geraet` | Geräte-Token: `schluessel_hash`, `status`, `zuletzt_gesehen`, `abgemeldet_am` | **ein** aktives Gerät je Person (`geraet_ein_aktives_je_person`) |
| `se.zweiter_faktor` | TOTP/Passkey für den PC | nicht fürs Handy |
| `protokoll.eintrag` | jede Anmeldung, jeder Fehlversuch, jede Abmeldung | nur anfügbar |

Für die Offline-Anmeldung braucht die **Datenbank nichts Neues**. Was offline gebraucht wird, liegt auf dem Handy (Abschnitt 4).

## 3. Ablauf

```
Brief (Einmal-Passwort) ──online──▶ POST /api/auth/login    ──▶ passwordChangeRequired = true
                                     POST /api/auth/password ──▶ tempo.device angelegt, Geräte-Cookie gesetzt
                                                                    Handy legt den Tresor an
Neues Handy / nach Abmelden ─online─▶ POST /api/auth/login ──▶ Geräte-Cookie, Tresor
Täglich öffnen ─────────────────────▶ Konto liegt auf dem Handy → App öffnet sofort (auch offline)
                                       mit Netz: GET /api/auth/me bestätigt das Gerät
App gesperrt ──────────────────────▶ Passwort eingeben: online prüft der Server,
                                       offline öffnet die App den Tresor
Gerät abgemeldet / Zugang gesperrt ─▶ beim nächsten Kontakt löscht die App alles auf dem Handy
```

Drei Zustände auf dem Handy:

| Zustand | Was liegt auf dem Handy | Wie kommt man rein |
|---|---|---|
| **angemeldet** | Konto (Speicher `konto`), Tresor, Cookies | gar nicht nötig, die App öffnet direkt |
| **gesperrt** („App sperren“) | Tresor, Cookies; kein Konto | Passwort, **auch offline** |
| **abgemeldet** („Dieses Handy abmelden“, oder vom Server) | nichts | nur online, mit Passwort oder neuem Brief |

## 4. Offline anmelden: der Tresor

Offline kann nur das Handy selbst prüfen. Dafür legt die App nach **jeder erfolgreichen Online-Anmeldung**
einen Tresor in IndexedDB an (`pwa/src/auth/vault.ts`):

1. Zufälliges Salz (16 Byte) und IV (12 Byte).
2. Schlüssel = **PBKDF2-HMAC-SHA-256, 600 000 Runden** (Empfehlung OWASP) aus dem Passwort, per WebCrypto.
3. Das Konto (Person, Benutzername, Rolle, Sprache) wird mit **AES-256-GCM** verschlüsselt;
   der Benutzername ist als Zusatzdaten gebunden.
4. Gespeichert werden Salz, IV, Runden, Chiffrat, Zeitpunkt des letzten Serverkontakts, Fehlversuche.

Offline anmelden = Tresor mit dem eingegebenen Passwort öffnen. Ein falsches Passwort scheitert an der
Prüfsumme von AES-GCM. Es liegt kein Prüfwert und kein Server-Hash auf dem Handy.

Regeln:

- **Server zuerst.** Ist der Server erreichbar, entscheidet er. Sagt er „falsch“, wird **nicht** auf den Tresor
  ausgewichen. Nur wenn er nicht erreichbar ist (kein Netz, Zeitüberschreitung 8 s, 5xx), prüft der Tresor.
- **Kein Tresor, keine Offline-Anmeldung.** Erste Anmeldung und Anmeldung nach dem Abmelden brauchen Netz.
- **Höchstens 14 Tage ohne Serverkontakt** (`OFFLINE_MAX_DAYS`, Vorschlag). Danach einmal online anmelden.
  Jeder Kontakt mit dem Server verlängert die Frist.
- **5 Fehlversuche → 15 Minuten Pause**, wie online.
- Neues Passwort am Server (anderes Handy, neuer Brief): Der Tresor wird bei der nächsten Online-Anmeldung
  ersetzt. Bis dahin öffnet das alte Passwort offline noch den Tresor. Das ist vertretbar (nächster Punkt).
- **Offline heißt: nur das eigene Handy.** Die Offline-Anmeldung öffnet nur, was schon auf dem Handy liegt.
  Alles, was die App sendet, prüft der Server mit dem Geräte-Token. Ein gesperrtes oder abgemeldetes Gerät
  wird abgewiesen, und die App löscht beim nächsten Kontakt Konto, Tresor, Lesestand und Ausgang
  (`offline.md`, Abschnitte 5 und 6).

### Sicherheit, ehrlich betrachtet

| Bedrohung | Schutz |
|---|---|
| Fremder findet das gesperrte Handy | Sperre des Handys (Betriebssystem), dann Tresor; die App-Daten sind ohne Entsperren nicht lesbar |
| Kollege greift zum offenen Handy | Die App bleibt angemeldet (Vorgabe). Dagegen hilft nur „App sperren“ oder eine automatische Sperre (offen, Abschnitt 8) |
| Jemand zieht die App-Daten vom Handy und rät Passwörter | PBKDF2 mit 600 000 Runden und die Passwortregel (8 Zeichen, Buchstabe und Ziffer) machen das teuer, aber nicht unmöglich. Darum gibt die Offline-Anmeldung keine Rechte am Server, und das Gerät lässt sich sperren. |
| Handy verloren | Lohnbuchhaltung oder Mitarbeiter meldet das Gerät ab (`tempo.device.status = 'signed_out'`). Ab dann nimmt der Server nichts mehr an, was zeitlich danach liegt. |
| Geräte-Token gestohlen (XSS) | HttpOnly-Cookie, für JavaScript nicht lesbar; CSP ohne fremde Skripte |

**Bewusst nicht gewählt: eine kurze PIN für die Offline-Anmeldung.** Eine 4- bis 6-stellige PIN ist offline in
Sekunden durchprobiert, egal wie viele Runden. Die PIN bleibt, wie entschieden, den Mitarbeitern ohne Handy
vorbehalten (Bestätigung am Handy des Poliers, online geprüft).

**Ausbaustufe (später, wenn die Handys es können): Fingerabdruck/Gesicht über einen Passkey mit
WebAuthn-PRF.** Der Passkey liefert einen geräte­gebundenen Schlüssel, mit dem sich der Tresor öffnen lässt,
ohne dass ein Passwort durchprobiert werden kann. Läuft auf aktuellem Android (Chrome) und iOS ab 18.
Vorher an den echten Diensthandys prüfen. Das Passwort bleibt als Rückfall.

**Ausbaustufe Lesestand verschlüsseln:** Der Tresor kann zusätzlich einen zufälligen Datenschlüssel tragen,
mit dem Lesestand und Ausgang in IndexedDB verschlüsselt werden. Solange angemeldet, liegt der Schlüssel als
nicht exportierbarer `CryptoKey` in IndexedDB; „App sperren“ löscht ihn. Lohnt sich, sobald im Lesestand mehr
als die eigenen Zeiten liegt (z. B. beim Polier die Kolonne).

## 5. API (umgesetzt, live unter `/api/docs`, Tag „Authentication“)

Kontext `api/src/Access/{Domain,Application,Infrastructure,Api}`. Alle Antworten mit Header `Date`. Ablehnungen als
`application/problem+json` mit `code` (die App zeigt ihren eigenen, übersetzten Text) und deutschem `detail`.

| Operation | Eingabe | Antwort |
|---|---|---|
| `POST /api/auth/login` | `username`, `password` (eigenes oder Einmal-Passwort), `language` (Anzeigesprache des Geräts), `deviceLabel` | 200 `{ account, passwordChangeRequired }`. Eigenes Passwort: Cookie `tempo_device`, `tempo.device` angelegt, altes Gerät abgemeldet. Einmal-Passwort: nur Cookie `tempo_registration` (10 Minuten, HMAC-signiert, ohne Serverzustand). |
| `POST /api/auth/password` | `newPassword` | 204; Konto aktiv, Brief eingelöst, Sprache aus der Anmeldung übernommen, Gerät angelegt, Cookie `tempo_device` |
| `GET /api/auth/me` | – (Cookie `tempo_device`) | 200 `AccountView` (`personId`, `username`, `firstName`, `lastName`, `role`, `roles`, `language`) |
| `POST /api/auth/logout` | – | 204; Gerät sofort ungültig, Cookie gelöscht |

| `code` | HTTP | Bedeutung |
|---|---|---|
| `invalid_credentials` | 401 | falsches Passwort, mit `remainingAttempts` |
| `temporarily_locked` | 429 | 5 Fehlversuche (15 Minuten) oder zu viele Versuche dieses Clients; `lockedUntil`, `Retry-After` |
| `account_locked` | 403 | vom Büro gesperrt |
| `one_time_password_expired` | 410 | Brief älter als 14 Tage |
| `registration_expired` | 401 | Registrierungs-Cookie fehlt oder abgelaufen |
| `password_policy_violated` | 422 | `detail` = Regeltext (Übersetzungsschlüssel) |
| `device_signed_out` | 401 | Gerät abgemeldet (vom Mitarbeiter, vom Büro oder durch Anmeldung auf neuem Handy) |
| `unauthenticated` | 401 | kein Geräte-Cookie |

**Geräte-Token statt JWT** (ADR 0004): Cookie `tempo_device` = `<device id>.<32 Byte Zufall, base64url>`, in
`tempo.device.key_hash` steht SHA-256 des Zufallsteils. Jede Anfrage wird darüber angemeldet (eine indizierte
Abfrage, keine Server-Sitzung, passt zu `stateless: true`). Sperren wirkt sofort.
Cookie: `HttpOnly; SameSite=Strict; Path=/api`, `Secure` bei HTTPS, 400 Tage, bei Nutzung erneuert (höchstens alle
5 Minuten zusammen mit `last_seen_at`). CSRF: gleicher Origin, `SameSite=Strict`, POST nur als JSON.

**Getrennte Domains auf Mittwald:** PWA (`tempo-app.p-yt27xz.project.space`) und API (`tempo-api.…`) sind
verschiedene Origins, aber **dieselbe Site**: `project.space` steht in der Public Suffix List (Eintrag von Mittwald),
die registrierbare Domain ist also `p-yt27xz.project.space`. Darum sendet der Browser das `SameSite=Strict`-Cookie mit.
Nötig sind CORS mit `allow_credentials` (vorhanden) und `expose_headers` für `Date` und `Retry-After` (ergänzt).
Lokal bleibt es bei einem Origin mit Pfad `/api` (ADR 0001); ein eigener Port brächte nur CORS ohne Nutzen.

**Weitere Regeln:** ein aktives Gerät je Person (Exclusion-Constraint, beim Commit geprüft); Antwortzeit bei
unbekanntem Benutzer wie bei falschem Passwort; 30 Versuche je Client und 15 Minuten (`CacheLoginThrottle`, später
gegen `symfony/rate-limiter` tauschbar; Paket ließ sich hier nicht installieren); ein neuer Brief macht ältere Briefe
und das bisherige Passwort ungültig.

**Werkzeuge bis zu den Büro-Bildschirmen:**

```
docker compose exec php bin/console tempo:access:create-person 1001 Stefan Maurer --role=skilled_worker
docker compose exec php bin/console tempo:access:issue-letter 1001
```

## 6. Mehrsprachigkeit

**Keine XLIFF-Dateien in der PWA.** Das System steht schon und kommt aus TempoVibe:

- `tx('Deutscher Text', …werte)` in `pwa/src/i18n/index.ts`; der deutsche Text ist der Schlüssel, Platzhalter `{0}`.
- Je Sprache eine flache JSON-Datei (`en, fr, hr, it, pl, ro`). Fehlt ein Eintrag, erscheint Deutsch.
- Sprachwechsel lädt die Seite neu. Polier, Bauleitung und PC bleiben deutsch (`germanOnly()`).
- Datum und Uhrzeit über `Intl` bzw. `toLocale…String(sprache)`.

Warum kein i18next/FormatJS und kein XLIFF: Die Texte sind kurz, Mehrzahlformen kaum nötig, und der deutsche
Schlüssel hält den Code lesbar. JSON lädt Vite direkt; die Sprachen sind im Precache und gehen offline. XLIFF
lohnt sich nur als **Austauschformat**, falls ein Übersetzungsbüro damit arbeiten will. Dann erzeugt ein
kleines Skript XLIFF aus den JSON-Dateien und liest es zurück. Die API sendet keine übersetzten Texte, sondern
`code` und deutschen `detail`; Rechtstexte kommen versioniert aus `se.rechtstext`.

Sprache und Anmeldung:

- Die Sprachwahl in der Kopfleiste geht **vor** der Anmeldung (wichtig für Mitarbeiter, die kein Deutsch lesen).
- Bei der ersten Anmeldung schickt die App die gewählte Sprache mit, der Server speichert sie in `tempo.account.language`.
- Danach gilt die Sprache vom Server (`syncLanguage` in `pwa/src/i18n/index.ts`). Eine Änderung in der Kopfleiste nach der Anmeldung
  muss noch an den Server gehen (Operation „Sprache setzen“, kommt mit „Mehr“).

## 7. Umsetzung

Erledigt:

- API: Kontext `Access` (Domäne ohne Framework, Use Cases, Doctrine-XML-Mapping, Authenticator für das
  Geräte-Cookie, problem+json), Migration `Version20261121230000` (Schema `tempo`), Unit- und API-Tests
  (`tests/Access`).
- PWA: `pwa/src/auth/` (`storage`, `vault`, `api`, `session`, `passwordPolicy`), Bildschirme `/` (Login),
  `/registration`, `/home` (vorläufig), Sprachwahl in der Kopfleiste, Texte in allen sechs Sprachen.

Nächste Schritte:

1. Erledigt: Typen aus OpenAPI erzeugt, `auth/account.ts` und `auth/api.ts` nutzen sie.
2. Registrierung vervollständigen: Datenschutz (Rechtstext), Ausweis, Standort; Bildschirme „abgemeldet“ und „Geräte“.
3. Protokoll der Anmeldungen in `protokoll.eintrag` (jetzt nur Log), RLS mit `app.person_id`.
4. Beim Abmelden warnen, wenn der Ausgang nicht leer ist (`offline.md`, Abschnitt 6).
5. Durchtesten auf dem Handy: Flugmodus, App schließen, falsches Passwort offline, Gerät abmelden, 14-Tage-Frist.

## 8. Offen (mit dem Auftraggeber klären)

- Soll die App **sich selbst sperren**, z. B. jeden Morgen oder nach einigen Stunden ohne Nutzung? Bisher nein
  (Vorgabe „bleibt angemeldet“); gesperrt wird nur von Hand. Die Technik dafür ist da.
- Frist ohne Serverkontakt: 14 Tage?
- Benutzername für NU-Personen (ohne Personalnummer).
