# ADR 0002: Anmeldung mit Konten in der eigenen Datenbank statt OIDC/Entra ID

- Status: angenommen
- Datum: 20.11.2026

## Kontext

Die TempoVibe-README beschreibt eine Anmeldung per SSO über Entra ID mit Token-Exchange. Viele Nutzer
(gewerbliche Mitarbeiter, Helfer, NU-Personen) haben aber kein Firmenkonto. Das Onboarding ist fachlich
festgelegt: Brief per Post mit Einmal-Passwort (XXXX-XXXX, 14 Tage), sofort eigenes Passwort, danach bleibt das
Handy angemeldet; PIN nur noch für Mitarbeiter ohne Handy. Das Schema enthält dafür bereits `se.zugang`,
`se.einladung`, `se.anmeldecode`, `se.geraet` und `se.zweiter_faktor`.

## Entscheidung

1. **Kein OIDC/Entra.** Das SSO aus der TempoVibe-README ist gekippt und wird nicht gebaut.
2. **Konten in der eigenen Datenbank** nach Schema. Symfony Security mit eigenem User Provider auf diese Tabellen.
3. Passwörter, PIN und Codes **nur als Hash** (Symfony PasswordHasher, Algorithmus `auto`).
4. **Handy:** Einmal-Passwort aus dem Brief → eigenes Passwort → langlebiges, widerrufbares **Geräte-Token**
   (`se.geraet`, nur der Hash des Schlüssels wird gespeichert; eine neue Registrierung meldet das alte Gerät ab).
5. **PC:** E-Mail und Passwort, zweiter Faktor (TOTP/Passkey) laut Schema; Sperre nach 5 Fehlversuchen.
6. **Sitzung** über ein HttpOnly-, Secure-, SameSite-Cookie auf demselben Origin (ADR 0001).
   Keine Tokens im localStorage.
7. **Login-Rate-Limit** (Symfony RateLimiter) und Protokoll der Anmeldungen (`protokoll.eintrag`).
8. Die Anmeldung liegt **hinter einer Schnittstelle** im Kontext Zugang, damit später ein SSO-Anbieter ergänzt
   werden kann, ohne die Fachlogik anzufassen.
9. Nach der Anmeldung setzt ein zentraler Request-Listener je Anfrage `app.person_id`, `app.rolle` und `app.warum`
   für die Row Level Security (Rolle `se_app`).

## Folgen

- Kein externer Identitätsdienst nötig; Onboarding per Brief funktioniert für alle Gruppen.
- Passwort-Zurücksetzen, Sperren und Geräteverwaltung liegen bei uns (PC: Administrator/Lohnbüro).
- `api_platform.defaults.stateless` muss für die Sitzungs-Endpunkte bzw. die Firewall angepasst werden.
- Offen: Ablage des Brief-Einmal-Passworts im Schema (Vorschlag in `docs/porting-status.md`, Abschnitt 1).
