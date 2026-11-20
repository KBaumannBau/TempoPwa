# Offline-Betrieb der PWA und Abgleich mit dem Server

- Stand: 21.11.2026, Entwurf 1
- Gilt für: Mitarbeiter-App und NU-App auf dem Handy. Polier, Bauleitung und PC siehe Abschnitt 9.

## 1. Anforderung

Mitarbeiter installieren die PWA als App auf dem Handy. Auf der Baustelle gibt es oft kein Netz.
Trotzdem müssen sie die App öffnen, stempeln und den Tag abschließen können. Alles, was offline
entsteht, geht beim nächsten Netz von selbst an den Server. Dabei darf nichts verloren gehen, nichts
doppelt ankommen und nichts still überschrieben werden.

## 2. Der Grundgedanke: Das Handy meldet Ereignisse, es gleicht keinen Zustand ab

Die meisten Offline-Konflikte entstehen, weil zwei Seiten **denselben Datensatz ändern** und danach
jemand entscheiden muss, wer gewinnt. Das vermeiden wir von Anfang an:

1. **Das Handy schickt nur Ereignisse**, nie einen geänderten Zustand. „Beginn um 06:58 auf Baustelle X“
   ist ein Ereignis. „Der Tag hat jetzt 8,5 Stunden“ ist ein Zustand und wird nie vom Handy gesendet.
2. **Ereignisse werden nur angefügt.** Das passt zum Datengrundsatz „Nichts wird überschrieben“
   und zum Schema: `se.stempelung` ist nur anfügbar, Korrekturen sind neue Zeilen mit `ersetzt_id`.
3. **Jedes Ereignis bekommt auf dem Handy seine endgültige ID** (UUID v7, zeitlich sortierbar). Diese
   ID wird zum Primärschlüssel in der Datenbank. Kommt dasselbe Ereignis zweimal an, erkennt der
   Server es an der ID und speichert es nicht noch einmal (Idempotenz).
4. **Der Server ist führend.** Er rechnet Stunden, Pausen, Zuschläge und Status aus den Ereignissen.
   Das Handy zeigt offline eine Vorschau, die beim nächsten Abgleich durch den Stand des Servers
   ersetzt wird.
5. **Jeder Tag hat zu jedem Zeitpunkt genau einen Besitzer.** Solange der Tag `offen` ist, schreibt nur
   der Mitarbeiter. Nach „Tag senden“ gehört er dem Polier, danach der Bauleitung und so weiter
   (`se.tag_status`). Zwei Seiten schreiben also nie gleichzeitig am selben Tag.

Damit bleibt kein technischer Konflikt übrig, den man zusammenführen müsste. Was übrig bleibt, sind
**fachliche Klärfälle** (Abschnitt 5). Die löst ein Mensch im normalen Ablauf, nicht der Rechner.

## 3. Bausteine auf dem Handy

| Baustein | Technik | Aufgabe |
|---|---|---|
| App-Hülle | Service Worker (Workbox über `vite-plugin-pwa`), Precache | App startet ohne Netz, alle Dateien liegen auf dem Handy |
| Lesestand | IndexedDB, Speicher `stand` | letzter Stand vom Server: Person, Einteilung, Baustellen (nur Umriss für den Standortvergleich), eigene Tage, Konto, Zuschlagskatalog, Texte |
| Ausgang | IndexedDB, Speicher `ausgang` | Warteschlange aller Ereignisse, die noch nicht beim Server bestätigt sind |
| Abgleich | eigenes kleines Modul `sync/` in der PWA | leert den Ausgang, holt danach den neuen Lesestand |
| Anzeige | Kopfleiste und Statuszeile | „offline“, „3 Einträge noch nicht gesendet“, „zuletzt abgeglichen 07:12“ |

Für IndexedDB reicht die kleine Bibliothek `idb`. Ein Sync-Framework (PouchDB, RxDB, Replicache und
ähnliche) brauchen wir nicht. Die würden einen Zustand zwischen Handy und Server spiegeln, und genau
das wollen wir nicht.

**localStorage bleibt tabu für Fachdaten** (CLAUDE.md). Der Lesestand ist eine Kopie vom Server. Der
Ausgang ist die einzige Stelle, an der das Handy Daten hält, die es auf dem Server noch nicht gibt.

## 4. Ablauf

### 4.1 Stempeln ohne Netz

```
Tippen auf „Beginn“
  → Ereignis bauen: { id: uuidv7, art: beginn, zeit_handy, zeit_handy_iso, baustelle_id,
                      standort: passt|weicht_ab|ohne, entfernung_m, begruendung, version: 1 }
  → in den Ausgang schreiben (eine IndexedDB-Transaktion)
  → Vorschau sofort aktualisieren, Hinweis „auf dem Handy gespeichert“
  → Abgleich anstoßen (läuft nur, wenn Netz da ist)
```

Der Standortvergleich läuft auf dem Handy gegen den Umriss der Baustelle aus dem Lesestand. Gespeichert
und gesendet wird nur das Ergebnis (`passt`, `weicht_ab` mit ungefährer Entfernung, `ohne`), nie die
Koordinate der Person.

### 4.2 Abgleich

Ausgelöst wird er beim Öffnen der App, beim Wechsel in den Vordergrund (`visibilitychange`), beim
Ereignis `online`, nach jeder Aktion und alle zwei Minuten, solange die App offen ist.

```
1. Gibt es Einträge im Ausgang? Der Reihe nach senden (älteste zuerst, einer nach dem anderen).
   Jeder Eintrag geht an seine eigene fachliche Operation, z. B.
     POST /api/stempelungen          { id, … }
     POST /api/tage/{datum}/senden   { id, basis: <Stand des Tages, den das Handy kannte>, zuschlaege: … }
2. Antwort des Servers je Eintrag:
     201 angenommen   → aus dem Ausgang löschen
     200 schon da     → (gleiche ID) aus dem Ausgang löschen, gilt als Erfolg
     409 / 422 fachlich abgelehnt → in „abgelehnt“ verschieben, Grund anzeigen, nicht wiederholen
     401              → Sitzung über das Geräte-Token erneuern, dann weiter
     5xx / kein Netz  → abbrechen, später erneut (wachsende Wartezeit, höchstens 5 Minuten)
3. Ausgang leer? Lesestand neu holen: GET /api/ich/stand
4. Vorschau verwerfen, Stand des Servers anzeigen.
```

Wichtig:

- **Reihenfolge einhalten.** Ein „Tag senden“ darf erst raus, wenn alle Stempelungen dieses Tages
  angenommen sind. Darum sendet der Abgleich streng nacheinander und bricht beim ersten technischen
  Fehler ab.
- **Nur ein Abgleich gleichzeitig**, auch wenn die App in zwei Tabs offen ist (Web Locks API,
  `navigator.locks.request('tempo-sync', …)`).
- **Nie still verwerfen.** Ein abgelehnter Eintrag bleibt sichtbar, bis der Mitarbeiter ihn gesehen hat.
  Der Server protokolliert die Ablehnung ebenfalls.
- **Background Sync** (Senden bei geschlossener App) gibt es nur unter Android/Chrome, nicht auf dem
  iPhone. Wir nutzen es dort als Zusatz. Verlassen dürfen wir uns nicht darauf. Grundlage ist der
  Abgleich bei geöffneter App.

### 4.3 Was der Server mit einem nachgesendeten Ereignis macht

- `id` vom Handy übernehmen, `INSERT … ON CONFLICT (id) DO NOTHING`. Gab es die ID schon, antwortet er
  mit dem gespeicherten Ergebnis (200).
- `eingang_server = now()`. Liegt der Eingang deutlich nach `zeit_handy`, ist die Herkunft
  `nachgesendet`, sonst `gestempelt`. `geraet_id` aus dem Geräte-Token.
- Den Tag (`se.tag`) bestimmt der **Server** aus `zeit_handy` nach deutscher Zeit (Schicht über
  Mitternacht gehört zum Tag des Beginns). Das Handy schickt keine `tag_id`.
- Die Regeln (Vier-Augen-Prinzip, Status, Plausibilität) prüft der Server bei jedem Eintrag, egal ob
  er sofort oder drei Tage später kommt.

## 5. Fachliche Klärfälle statt technischer Konflikte

| Fall | Was passiert |
|---|---|
| Stempelung kommt an, der Tag ist schon beim Polier oder weiter | Wird **nicht** abgelehnt und nicht verworfen. Sie wird gespeichert (`nachgesendet`) und dem Tag als Klärfall angehängt. Der aktuelle Besitzer des Tages (Polier, Bauleitung, Lohnbuchhaltung) sieht „nachgesendete Stempelung, bitte prüfen“. Das Vier-Augen-Prinzip bleibt so erhalten. |
| Polier hat den Tag korrigiert, während das Handy offline war | Korrektur und Stempelung sind beide neue Zeilen. Nichts überschreibt etwas. Der Polier sieht beides und entscheidet. |
| „Tag senden“ mit veraltetem Stand | Das Handy schickt `basis` mit (die Version des Tages, die es kannte). Weicht sie ab, antwortet der Server mit 409. Die App holt den neuen Stand und zeigt den Tag noch einmal zum Prüfen. Danach sendet der Mitarbeiter bewusst neu. |
| Handy-Uhr falsch gestellt | `zeit_handy` und `eingang_server` werden beide gespeichert. Die App merkt sich bei jedem Abgleich den Abstand zur Serveruhr (Header `Date`) und schickt ihn mit. Ab fünf Minuten Abweichung markiert der Server den Eintrag (siehe `se.v_stempelung_gueltig.handy_uhr_weicht_ab`), der Polier sieht den Hinweis. |
| Unplausibel (in der Zukunft, älter als die Frist, zweimal „Beginn“ ohne „Ende“) | Wird gespeichert und markiert, nicht still korrigiert. Ausnahme: Ereignisse in der Zukunft (mehr als 10 Minuten nach der Serverzeit) lehnt der Server mit 422 ab. |
| Altes Handy nach Neuregistrierung | Je Person gibt es nur ein aktives Gerät (`geraet_ein_aktives_je_person`). Ein abgemeldetes Gerät darf seinen Ausgang trotzdem noch abliefern, wenn die Ereignisse zeitlich vor der Abmeldung liegen. Danach wird es abgewiesen. |

Ein Mensch kann jeden dieser Fälle im normalen Ablauf lösen, ohne dass Daten verschwinden.

## 6. Anmeldung offline

- Das Handy bleibt über das langlebige, widerrufbare Geräte-Token angemeldet (ADR 0002). Die Sitzung
  läuft über ein HttpOnly-Cookie. Die App kann das Token also nicht lesen und muss das auch nicht.
- **Offline gibt es keine Prüfung durch den Server.** Die App öffnet mit dem zuletzt bekannten Konto aus
  dem Lesestand. Das ist vertretbar, weil offline nichts den Server erreicht. Beim nächsten Abgleich prüft
  der Server jeden Eintrag mit dem Geräte-Token.
- Wird das Gerät gesperrt (Verlust, Austritt), weist der Server den Ausgang ab, und die App löscht beim
  nächsten Kontakt Lesestand und Ausgang.
- Die Abmeldung auf dem Handy löscht Lesestand und Ausgang. Ist der Ausgang nicht leer, warnt die App
  vorher deutlich.

## 7. Speicher und Updates

- Beim ersten Start `navigator.storage.persist()` anfragen, damit der Browser den Speicher nicht von
  selbst räumt. Auf dem iPhone räumt Safari Website-Daten nach sieben Tagen ohne Nutzung. Für eine **zum
  Home-Bildschirm hinzugefügte** App gilt das nicht. Darum ist die Installation für Mitarbeiter Pflicht.
- Die Daten sind klein (Stempelungen weniger Tage). Mengen sind kein Problem, nur die Haltbarkeit.
- **App-Update:** Der Service Worker lädt neue Versionen im Hintergrund. Aktiviert wird die neue Version
  erst, wenn der Ausgang leer ist oder die App neu startet. Jeder Eintrag im Ausgang trägt eine
  `version`. Der Server nimmt die Vorgängerversion jedes Ereignisses mindestens zwei Releases lang an.
  So kann ein Handy, das eine Woche offline war, seinen Ausgang noch abliefern.
- Lesestand und Ausgang haben ein eigenes Schema mit Versionsnummer (IndexedDB `onupgradeneeded`). Ein
  Update darf den Ausgang nie löschen, nur umschreiben.

## 8. Was der Mitarbeiter sieht

- Die Kopfleiste zeigt offline einen kleinen Hinweis „offline“.
- Jede Stempelung zeigt, wo sie steht: **„auf dem Handy“** (Uhr-Symbol) oder **„beim Server“** (Haken).
- Steht etwas im Ausgang: „2 Einträge noch nicht gesendet. Sie gehen raus, sobald Netz da ist.“
- Ein abgelehnter Eintrag erscheint als Meldung mit Grund und dem Hinweis, wer hilft (Polier).
- „Tag senden“ geht auch offline. Der Tag steht dann auf „wird gesendet“, nicht auf „beim Polier“.
  Erst die Bestätigung des Servers setzt den echten Status.

## 9. Umfang: was offline geht und was nicht

| Bereich | Offline |
|---|---|
| Mitarbeiter: stempeln, Pause, Wechsel, Ende | **ja**, über den Ausgang |
| Mitarbeiter: Tag abschließen (Zuschläge wählen, prüfen, senden) | **ja**, über den Ausgang |
| Mitarbeiter: Einteilung, Konto, eigene Tage ansehen | **ja**, nur lesen (Stand vom letzten Abgleich, mit Zeitangabe) |
| Mitarbeiter: Anträge stellen | zunächst nur online. Später über den Ausgang möglich, nach demselben Muster |
| NU-App: Stunden erfassen | **ja**, wie Mitarbeiter |
| Polier, Bauleitung: Freigaben, Korrekturen | nein. Lesen aus dem Stand ja, Schreiben nur online. Freigaben sind Entscheidungen über die Tage anderer und sollen auf aktuellem Stand fallen. |
| PC / Büro | nein, nur online |

Das hält den Offline-Teil klein: Offline schreibt nur, wer **eigene** Ereignisse meldet. Wer **über
fremde** Daten entscheidet, braucht Netz.

## 10. Was die API dafür bereitstellen muss

- Jede Operation, die offline entstehen kann, nimmt eine **ID vom Client** an und ist idempotent.
  Bei gleicher ID und gleichem Inhalt antwortet sie 200 mit dem gespeicherten Ergebnis. Bei gleicher
  ID und anderem Inhalt antwortet sie 409.
- Fachliche Ablehnungen kommen als 409/422 mit maschinenlesbarem `code` und deutschem Text (für `tx`).
- `GET /api/ich/stand` liefert alles für den Lesestand in einem Aufruf, mit `stand_vom` (Serverzeit)
  und einer Version je Tag (für `basis` beim Senden).
- Die Antworten tragen den Header `Date`, damit die App die Abweichung der Handy-Uhr kennt.
- Kein generisches PATCH auf Status-Felder (CLAUDE.md). „Tag senden“ ist eine eigene Operation mit
  eigenem Processor. Genau das macht sie offline-tauglich.
- Später möglich: eine Sammel-Operation `POST /api/geraet/ausgang`, die mehrere Ereignisse in einem
  Aufruf annimmt und je ID ein Ergebnis liefert. Nötig ist sie für den Anfang nicht.

## 11. Umsetzung in Schritten

1. **Erledigt mit der ersten PWA-Version:** installierbare App mit Icon, App-Hülle offline (Precache),
   Anzeige online/offline.
2. Modul `pwa/src/sync/`: IndexedDB (`idb`), Ausgang, Abgleich mit Web Lock, Statusanzeige. Tests mit
   `fake-indexeddb`.
3. API: `POST /api/stempelungen` idempotent mit Client-ID, `GET /api/ich/stand`.
4. Bildschirm Stempeln aus TempoVibe übernehmen und auf Ausgang und Lesestand umstellen.
5. „Tag senden“ mit `basis` und 409-Ablauf, Klärfall „nachgesendet“ beim Polier.
6. Durchtesten: Flugmodus, App schließen, Handy neu starten, Uhr verstellen, zwei Tabs, Update während
   voller Ausgang, Gerät sperren.

## 12. Bewusst nicht gewählt

- **Zustand abgleichen mit „letzter Schreiber gewinnt“:** einfach, verliert aber still Daten und
  widerspricht „Nichts wird überschrieben“.
- **CRDTs, Sync-Frameworks:** lösen ein Problem, das wir durch Ereignisse und klare Besitzer gar nicht
  haben. Sie kosten Abhängigkeiten und machen RLS und Protokoll schwerer.
- **Verlassen auf Background Sync:** auf dem iPhone nicht vorhanden.
- **Fachlogik doppelt auf dem Handy:** Das Handy rechnet nur zur Vorschau. Verbindlich ist der Server.
