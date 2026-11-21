# ADR 0004: Geräte-Token im HttpOnly-Cookie statt JWT

- Status: angenommen
- Datum: 21.11.2026

## Kontext

Die Handy-App muss sich bei der API ausweisen. Zur Wahl standen JWT (im Header oder im Cookie, mit Refresh-Token)
und ein zufälliges Geräte-Token mit Prüfung in der Datenbank. PWA und API liegen auf demselben Origin (ADR 0001).
Ein verlorenes Handy oder ein ausgeschiedener Mitarbeiter muss **sofort** gesperrt werden können. Offline prüft
ohnehin kein Server.

## Entscheidung

Geräte-Token im HttpOnly-Cookie `tempo_device`: `<device id>.<32 Byte Zufall>`, in `tempo.device` nur SHA-256 des
Zufallsteils. Jede Anfrage wird darüber angemeldet (`DeviceCookieAuthenticator`, stateless). Kein JWT.

## Begründung

- **Sofort widerrufbar:** Abmelden setzt `status = 'signed_out'`, die nächste Anfrage scheitert. Ein JWT gilt bis zum
  Ablauf weiter; dagegen bräuchte es Sperrliste oder kurze Laufzeit mit Refresh-Token, also doch wieder eine
  Datenbankabfrage.
- **Nichts in JavaScript:** Das Token liegt nur im HttpOnly-Cookie (CLAUDE.md: keine Tokens im localStorage).
- **Einfach:** kein Schlüsselmanagement, kein zusätzliches Paket, eine indizierte Abfrage je Anfrage.
- JWT spielt seine Stärken bei fremden Clients und verteilten Diensten aus. Beides haben wir nicht.

## Folgen

- Kein Sitzungsspeicher nötig, passt zum Mittwald-Webhosting und zu `api_platform.defaults.stateless: true`.
- Je Anfrage ein Lesezugriff auf `tempo.device` (Primärschlüssel); `last_seen_at` wird höchstens alle 5 Minuten geschrieben.
- Kommen später fremde Clients (z. B. Lohnbuchhaltung per Schnittstelle), bekommen diese eigene Zugänge
  (z. B. OAuth Client Credentials), ohne die Handy-Anmeldung zu ändern.
