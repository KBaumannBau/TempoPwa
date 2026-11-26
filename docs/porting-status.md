# Übernahme aus TempoVibe – Stand

Inventar aller Bildschirme und Ketten aus TempoVibe (`src/App.tsx`, `src/screens/`, `docs/demo-app.md`),
gruppiert nach Bereich und in der Reihenfolge aus `CLAUDE.md`:

**Anmeldung/Zugang → Stunden-Kette → Anträge → Disposition → NU → Kosten/Leitung**

Abhaken, wenn ein Bildschirm an der API hängt und die Kette im Browser gegen die Demo durchgeklickt ist.
Spalte „Quelle“ = Datei und Komponente in TempoVibe. Pfade in Tempo wie in der Demo, aber ohne `#`
(BrowserRouter). Tabellen beziehen sich auf `TempoVibe/docs/datenbankschema.sql` (Schema `se`, falls nicht anders angegeben).

Legende: `[ ]` offen · `[~]` begonnen · `[x]` fertig und durchgeklickt

## Grundgerüst

- [x] Docker (nginx, php, database, pwa), ein Origin, HMR hinter nginx
- [x] Symfony + API Platform, OpenAPI unter `/api/docs`, `GET /api/health`
- [x] PWA (Vite, React 19, React Router, vite-plugin-pwa), Typen aus OpenAPI (`npm run api:types`)
- [ ] Rahmen und gemeinsame Bausteine aus `src/components/` (Rahmen, Kopf, Teile, Pc, Icons, Logo) und `styles.css`
- [ ] Datenbank-Grundlage: Schemas `se`/`protokoll`, Aufzählungen, Rollen `se_app`/`se_loeschlauf`,
      Request-Listener für `app.person_id`, `app.rolle`, `app.warum`, Protokoll-Trigger
- [ ] Fixtures aus `src/domain/seed.ts` (erfundene Personen)

---

## 1. Anmeldung und Zugang

Kette: Brief mit Einmal-Passwort → erste Anmeldung → eigenes Passwort → Gerät bleibt angemeldet
(Geräte-Token) → tägliches Öffnen; PC mit E-Mail, Passwort und zweitem Faktor; Sperre nach 5 Fehlversuchen.

**Tabellen:** `rolle`, `person`, `person_rolle`, `person_rolle_baustelle`, `baustelle` (Grunddaten für Rollen je Baustelle),
`vertretung` (für `se.hat_rolle`), `zugang`, `einladung`, `anmeldecode`, `geraet`, `zweiter_faktor`,
`rechtstext`, `bestaetigung`, `einstellung`, `protokoll.eintrag`

| | Pfad | Bildschirm | Quelle |
|---|---|---|---|
| [~] | `/` | Anmeldung Handy (Benutzername, Passwort; offline über Tresor, `docs/pwa/authentication.md`) – PWA und API fertig, im Browser durchgeklickt (Brief, eigenes Passwort, Polnisch, Sperren, offline anmelden, Gerät vom Büro abgemeldet); auf dem Handy offen | `screens/Anmeldung.tsx` · `Anmeldung` |
| [~] | `/registration` | Registrierung (Einmal-Passwort und eigenes Passwort fertig, PWA und API; Datenschutz, Ausweis, Standort offen) | `screens/Zugang.tsx` · `Registrierung` |
| [~] | `/sperre` | ersetzt durch „App sperren“ + Anmeldung auf `/` (geht offline) | `screens/Zugang.tsx` · `Sperre` |
| [ ] | `/abgemeldet` | Gerät abgemeldet / Zugang gesperrt | `screens/ma/Rest.tsx` · `Abgemeldet` |
| [ ] | `/intro` | Einführung | `screens/ma/Extra.tsx` · `Intro` |
| [ ] | `/datenschutz` | Datenschutz (Rechtstext, versioniert) | `screens/ma/Extra.tsx` · `Datenschutz` |
| [ ] | `/hilfe` | Hilfe, Nachricht an die Verwaltung | `screens/ma/Extra.tsx` · `Hilfe` |
| [ ] | `/faq` | Häufige Fragen | `screens/ma/Extra.tsx` · `Faq` |
| [ ] | `/ma/pin` | PIN ändern | `screens/ma/Rest.tsx` · `Pin` |
| [ ] | `/ma/geraete` | Geräte anzeigen und abmelden | `screens/ma/Rest.tsx` · `Geraete` |
| [ ] | `/ma/mehr`, `/p/mehr`, `/b/mehr`, `/nu/mehr` | Menü „Mehr“ inkl. Sprache | `Rest.tsx` · `Mehr`, `Kolonne.tsx` · `PMehr`, `Bauleitung.tsx` · `BMehr`, `nu/Nu.tsx` · `NuMehr` |
| [ ] | `/pc` | PC-Anmeldung (E-Mail, Passwort, 2. Faktor, Sperre) | `screens/pc/Login.tsx` · `PcLogin` |
| [ ] | `/pc/admin` | Benutzer, Rollen, technisches Protokoll | `screens/pc/Leitung.tsx` · `PcAdmin` |
| [ ] | `/pc/mitarbeiter` (Teil) | Registrierungsbrief erstellen, Zugang setzen | `screens/pc/Personal.tsx` · `PcMitarbeiter` |

Aktionen aus `store.tsx`: `briefErstellen`, `passwortSetzen`, `pinAendern`, `geraetAbmelden`, `zugangSetzen`,
`spracheSetzen`, `introGesehen`, `benutzerRolle`, `rolleJeBaustelle`, `oberbauleitungSetzen`, `vertretungSetzen`, `techLog`.
Fachlogik: `domain/zugang.ts` (Einmal-Passwort, Passwortregeln).

> Klären: Das Einmal-Passwort aus dem Brief (XXXX-XXXX, 14 Tage) hat im Schema keine eigene Tabelle.
> Vorschlag: `se.einladung` mit `gedruckt = true` und `token_hash` = Hash des Einmal-Passworts; `se.anmeldecode`
> (6 Ziffern, 15 Minuten) bleibt für die Anmeldung per Code.

## 2. Stunden-Kette

Kette (demo-app.md, Nr. 1): Mitarbeiter stempelt (auch Baustellenwechsel) und sendet → Polier gibt frei
(Handy oder PC) → Bauleitung gibt frei (Tag eines Poliers) bzw. Oberbauleitung (Tag eines Bauleiters)
→ Lohnbuchhaltung übernimmt → ZEITERF.DAT (55 Zeichen, CRLF, Windows-1252; zweiter Export im Monat = Nachtrag).
Vier-Augen-Regeln setzt der Server durch.

**Tabellen:** `tag`, `tag_statuswechsel`, `stempelung` (+ Sicht `v_stempelung_gueltig`), `zuschlag_art`, `zuschlag`,
`korrektur`, `vergessen_meldung`, `ausfall`, `ausfall_person`, `helfer_eintrag`, `bestaetigung`, `rechtstext`,
`person_arbeitszeit`, `kontobuchung` (+ Sicht `v_kontostand`, SIKOFlex/Mehrstunden), `tag_uebernahme`,
`export`, `export_tag`, `export_zeile`, `export_offen`, `nachtstunden_meldung`, `mitteilung`, `einstellung`, `datei` (Export-Datei)

### 2a Mitarbeiter (Handy)

| | Pfad | Bildschirm | Quelle |
|---|---|---|---|
| [ ] | `/ma/heute` | Heute: stempeln (Beginn, Pause, Weiter, Wechsel, Ende), offline mit Warteschlange | `screens/ma/Stempeln.tsx` · `Heute` |
| [ ] | `/ma/standort` | Standortvergleich mit der Baustelle | `screens/ma/Stempeln.tsx` · `Standort` |
| [ ] | `/ma/baustelle` | Baustelle wählen / wechseln | `screens/ma/Stempeln.tsx` · `BaustelleWaehlen` |
| [ ] | `/ma/vergessen` | „Vergessen zu stempeln“ melden | `screens/ma/Stempeln.tsx` · `Vergessen` |
| [ ] | `/ma/tag/:datum`, `/ma/tag/:datum/zuschlaege` | Tagesabschluss Schritt 1: Zuschläge | `screens/ma/Tag.tsx` · `TagAbschluss`, `Zuschlaege` |
| [ ] | `/ma/tag/:datum/pruefen` | Schritt 2: Tag prüfen und senden | `screens/ma/Tag.tsx` · `TagPruefen` |
| [ ] | `/ma/tag/:datum/fahrt` | alte Fahrt-Seite (Weiterleitung, Mitarbeiter trägt keine Fahrt ein) | `screens/ma/Tag.tsx` · `AlteFahrt` |
| [ ] | `/ma/gesendet/:datum` | Tag gesendet | `screens/ma/Tag.tsx` · `Gesendet` |
| [ ] | `/ma/korrektur/:tagId` | Korrektur bestätigen oder widersprechen | `screens/ma/Tag.tsx` · `Korrektur` |
| [ ] | `/ma/woche` | Wochenübersicht | `screens/ma/Rest.tsx` · `Woche` |
| [ ] | `/ma/konto` | Konto (Urlaub, SIKOFlex/Mehrstunden) | `screens/ma/Rest.tsx` · `Konto` |

### 2b Polier (Handy und PC)

| | Pfad | Bildschirm | Quelle |
|---|---|---|---|
| [ ] | `/p/heute` | Übersicht Polier | `screens/polier/Freigabe.tsx` · `PHeute` |
| [ ] | `/p/freigabe` | Tage zur Freigabe | `screens/polier/Freigabe.tsx` · `PFreigabe` |
| [ ] | `/p/tag/:id` | Tag ansehen, Zuschläge entscheiden | `screens/polier/Freigabe.tsx` · `PTag` |
| [ ] | `/p/tag/:id/korrektur` | Zeit korrigieren (mit Grund) | `screens/polier/Freigabe.tsx` · `PKorrektur` |
| [ ] | `/p/vorschlag`, `/b/vorschlag` | Meldungen „vergessen“ übernehmen/ablehnen | `screens/polier/Freigabe.tsx` · `PVorschlag` |
| [ ] | `/p/freigegeben` | freigegebene Tage | `screens/polier/Freigabe.tsx` · `PFreigegeben` |
| [ ] | `/p/ohne-handy` | Stunden für Mitarbeiter ohne Handy | `screens/polier/Kolonne.tsx` · `POhneHandy` |
| [ ] | `/p/ohne-handy/pin/:pid/:datum` | Bestätigung mit PIN auf dem Handy des Poliers | `screens/polier/Kolonne.tsx` · `PPinBestaetigen` |
| [ ] | `/p/nachtragen` | Zeiten nachtragen (Mitarbeiter bestätigt) | `screens/polier/Kolonne.tsx` · `PNachtragen` |
| [ ] | `/p/helfer` | Helfer- und NU-Stunden eintragen | `screens/polier/Kolonne.tsx` · `PHelfer` |
| [ ] | `/p/ausfall` | Ausfall (Schlechtwetter u. a.) | `screens/polier/Kolonne.tsx` · `PAusfall` |
| [ ] | `/p/kolonne` | Kolonne | `screens/polier/Kolonne.tsx` · `PKolonne` |
| [ ] | `/pc/polier` | PC: Übersicht Polier | `screens/pc/Leitung.tsx` · `PcPolier` |
| [ ] | `/pc/polier/freigabe` | PC: Freigabe Polier | `screens/pc/Leitung.tsx` · `PcPolierFreigabe` |
| [ ] | `/pc/polier/helfer` | PC: Helfer und NU | `screens/pc/Leitung.tsx` · `PcPolierHelfer` |

### 2c Bauleitung und Oberbauleitung

| | Pfad | Bildschirm | Quelle |
|---|---|---|---|
| [ ] | `/b/heute` | Übersicht Bauleitung | `screens/bau/Bauleitung.tsx` · `BHeute` |
| [ ] | `/b/freigabe` | Freigabe Bauleitung | `screens/bau/Bauleitung.tsx` · `BFreigabe` |
| [ ] | `/b/tag/:id` | Tag ansehen | `screens/bau/Bauleitung.tsx` · `BTag` |
| [ ] | `/b/tag/:id/zurueck` | zurück an den Polier (Grund Pflicht) | `screens/bau/Bauleitung.tsx` · `BZurueck` |
| [ ] | `/b/gegenzeichnen` | Helfer- und NU-Stunden gegenzeichnen | `screens/bau/Bauleitung.tsx` · `BGegenzeichnen` |
| [ ] | `/b/meine-zeit` | eigene Zeit an die Oberbauleitung | `screens/bau/Bauleitung.tsx` · `BMeineZeit` |
| [ ] | `/pc/bl` | PC: Übersicht Bauleitung | `screens/pc/Leitung.tsx` · `PcBl` |
| [ ] | `/pc/bl/freigabe` | PC: Freigabe | `screens/pc/Leitung.tsx` · `PcBlFreigabe` |
| [ ] | `/pc/bl/gegenzeichnen` | PC: Gegenzeichnen | `screens/pc/Leitung.tsx` · `PcBlGegenzeichnen` |
| [ ] | `/pc/bl/widersprueche` | PC: Widersprüche entscheiden | `screens/pc/Leitung.tsx` · `PcBlWidersprueche` |
| [ ] | `/pc/obl` | PC: Tage der Bauleiter (Oberbauleitung) | `screens/pc/Leitung.tsx` · `PcObl` |

### 2d Lohnbuchhaltung und Export

| | Pfad | Bildschirm | Quelle |
|---|---|---|---|
| [ ] | `/pc/stunden` | Stunden übernehmen, Export ZEITERF.DAT, Nachtrag | `screens/pc/Stunden.tsx` · `PcStunden` |
| [ ] | `/pc/nachtstunden` | Nachtstunden-Meldung je Monat | `screens/pc/Stunden.tsx` · `PcNachtstunden` |
| [ ] | `/pc/mehrstunden` | Mehrstunden Angestellte | `screens/pc/Personal.tsx` · `PcMehrstunden` |

Aktionen aus `store.tsx`: `stempeln`, `wechseln`, `setDemoStandort` (wird echter Standortvergleich), `vergessenMelden`,
`zuschlaegeSetzen`, `fahrtSetzen` (nur Lohnbuchhaltung), `tagSenden`, `korrekturAntworten`, `zuschlagEntscheiden`,
`zeitKorrigieren`, `polierFreigeben`, `vorschlagEntscheiden`, `zeitenErfassen`, `mitPinBestaetigt`, `helferSpeichern`,
`ausfallMelden`, `bauleitungFreigeben`, `oblFreigeben`, `zurueckAnPolier`, `gegenzeichnen`, `eigenenTagSenden`,
`widerspruchEntscheiden`, `ohneRueckmeldungEntschieden`, `uebernehmen`, `exportErstellen`, `exportZuruecknehmen`,
`nachtMailVersendet`. Fachlogik auf den Server: `domain/zeit.ts` (Pausen, Feiertage Bayern, Nacht 20–05 Uhr, Konto),
`domain/zuschlaege.ts` (BRTV Bau), `domain/lohn.ts` (Monatsübersicht, Export), `domain/sel.ts` (Auswahlen je Rolle).

## 3. Anträge

Ketten (demo-app.md, Nr. 2, 3, 6): **Urlaub** Antrag → Oberbauleitung → Geschäftsführung (2. Stufe) → Abwesenheiten.
**Arbeitsbefreiung** Antrag mit Anlass → Genehmigung unter Vorbehalt → Nachweis nachreichen → Büro „Nachweis gesehen“;
ohne Nachweis Ersatz wählen (Urlaubstag oder unbezahlt). **Betriebsurlaub** Büro legt fest → Mitarbeiter wählt
24./31.12. und stimmt dem Kontoabzug zu. Dazu Krankmeldung, Kind krank, Frei vom Konto.

**Tabellen:** `antrag`, `antrag_schritt`, `antrag_datei`, `datei`, `datei_abruf` (Gesundheitsdaten), `betriebsurlaub`,
`betriebsurlaub_wahl`, `kontobuchung` (+ `v_kontostand`), `person_urlaubsanspruch`, `person_kind`, `vertretung`,
`mitteilung`, `bestaetigung`

| | Pfad | Bildschirm | Quelle |
|---|---|---|---|
| [ ] | `/ma/antraege` | Anträge und Status | `screens/ma/Rest.tsx` · `Antraege` |
| [ ] | `/ma/urlaub` | Urlaub / Frei vom Konto beantragen | `screens/ma/Rest.tsx` · `Urlaub` |
| [ ] | `/ma/krank` | Krankmeldung, Kind krank | `screens/ma/Rest.tsx` · `Krank` |
| [ ] | `/ma/krank/:id` | Rückmeldung gesund / Folgebescheinigung | `screens/ma/Extra.tsx` · `KrankRueckmeldung` |
| [ ] | `/ma/arbeitsbefreiung` | Arbeitsbefreiung mit Anlass | `screens/ma/Extra.tsx` · `Arbeitsbefreiung` |
| [ ] | `/ma/nachweis/:id` | Nachweis nachreichen, Ersatz wählen | `screens/ma/Extra.tsx` · `NachweisNachreichen` |
| [ ] | `/ma/betriebsurlaub` | Wahl 24./31.12., Zustimmung Kontoabzug | `screens/ma/Extra.tsx` · `BetriebsurlaubWahl` |
| [ ] | `/b/urlaub` | Anträge (Bauleitung, Sicht ohne Grund) | `screens/bau/Bauleitung.tsx` · `BUrlaub` |
| [ ] | `/b/antrag/:id` | Antrag entscheiden | `screens/bau/Bauleitung.tsx` · `BAntrag` |
| [ ] | `/pc/bl/antraege` | PC: Urlaub und Anträge | `screens/pc/Leitung.tsx` · `PcBlAntraege` |
| [ ] | `/pc/gl` | PC: Geschäftsleitung, 2. Stufe Urlaub, Vertretung Lohnbüro | `screens/pc/Leitung.tsx` · `PcGl` |
| [ ] | `/pc/abwesenheiten` | Abwesenheiten, Nachweis gesehen | `screens/pc/Personal.tsx` · `PcAbwesenheiten` |
| [ ] | `/pc/betriebsurlaub` | Betriebsurlaub festlegen | `screens/pc/Personal.tsx` · `PcBetriebsurlaub` |

Aktionen aus `store.tsx`: `antragStellen`, `antragEntscheiden`, `glAntragEntscheiden`, `nachweisHochladen`, `ersatzWaehlen`,
`krankRueckmeldung`, `betriebsurlaubWaehlen` sowie die Betriebsurlaub- und Abwesenheitsaktionen des Büros.

> Klären: `CLAUDE.md` sagt „Anträge gehen an die Oberbauleitung“, die Demo-Seiten liegen aber bei der Bauleitung
> (`/b/urlaub`, `/pc/bl/antraege`, Status `bauleitung_ok`). Vor dem Bau festlegen, wer die 1. Stufe ist.

## 4. Disposition

Kette (demo-app.md, Nr. 4): Person oder Gerät wählen, „+ hier“, „½“ für halbe Tage, „Änderungen senden“ → Mitteilung
in der App. NU mit ungültigen Unterlagen sind rot markiert.

**Tabellen:** `einteilung`, `helfer_dispo`, `baugeraet`, `baugeraet_einteilung`, `mitteilung`; lesend `baustelle`,
`person`, `nu_unterlage` (Warnung), `antrag` (Abwesenheiten)

| | Pfad | Bildschirm | Quelle |
|---|---|---|---|
| [ ] | `/pc/disposition` | Disposition (Personal, Helfer, Geräte je Tag/Tageshälfte) | `screens/pc/Disposition.tsx` · `PcDisposition` |
| [ ] | `/pc/polier/einteilung` | PC: Einteilung Polier | `screens/pc/Leitung.tsx` · `PcPolierEinteilung` |
| [ ] | `/ma/einteilung` | Einteilung des Mitarbeiters | `screens/ma/Rest.tsx` · `Einteilung` |
| [ ] | `/ma/mitteilungen` | Mitteilungen | `screens/ma/Extra.tsx` · `Mitteilungen` |

Aktionen aus `store.tsx`: Disposition (Einteilen, halbe Tage, Änderungen senden), `mitteilungGelesen`.

## 5. Nachunternehmer (NU)

Kette (demo-app.md, Nr. 5): Unterlagen hochladen und prüfen, Ausweis eintragen (ohne Kopie), Mindestlohnerklärung
einmal erinnern → NU-Person unterschreibt in der NU-App.

**Tabellen:** `nu_firma`, `person` (NU-Personen), `nu_unterlage`, `nu_stundensatz` (ohne Protokoll, nur Einkauf/GL),
`ausweis_sichtung`, `entsendung_nachweis`, `mindestlohnerklaerung`, `nu_rechnungsabgleich`, `helfer_eintrag`, `datei`,
`rechtstext`, `bestaetigung`

| | Pfad | Bildschirm | Quelle |
|---|---|---|---|
| [ ] | `/nu/heute` | NU-App: Heute | `screens/nu/Nu.tsx` · `NuHeute` |
| [ ] | `/nu/stunden` | NU-App: Stunden | `screens/nu/Nu.tsx` · `NuStunden` |
| [ ] | `/nu/nachweise` | NU-App: Nachweise, Mindestlohnerklärung | `screens/nu/Nu.tsx` · `NuNachweise` |
| [ ] | `/ma/ausweis` | Ausweis-Sichtung (ohne Kopie) | `screens/ma/Extra.tsx` · `AusweisSichtung` |
| [ ] | `/pc/nu` | Nachunternehmer, Unterlagen, Stundensätze | `screens/pc/Nu.tsx` · `PcNu` |
| [ ] | `/pc/auswertung/nu` | Auswertung Helfer / Nachunternehmer | `screens/pc/Nu.tsx` · `PcAuswertungNu` |

Aktionen aus `store.tsx`: `unterlageSetzen`, `nuPersonAnlegen`, `stundensatzSetzen`, `ausweisEintragen`,
`mindestlohnErinnern`, `mindestlohnUnterschreiben`.

## 6. Kosten und Leitung

**Tabellen:** `baustelle`, `projekt_soll`, `nachtrag`, `mittellohn`, `kostenmonat`, `kosten_monat_summe`,
`eingangsrechnung` (+ Sicht `v_projekt_soll`), `nachricht`, `loeschsperre`, `loeschlauf`, `protokoll.eintrag`;
lesend `tag`/`stempelung` für Ist-Stunden

| | Pfad | Bildschirm | Quelle |
|---|---|---|---|
| [ ] | `/pc/baustellen` | Baustellen | `screens/pc/Baustellen.tsx` · `PcBaustellen` |
| [ ] | `/pc/archiv` | Baustellenarchiv | `screens/pc/Baustellen.tsx` · `PcArchiv` |
| [ ] | `/pc/projektkosten` | Projektkosten | `screens/pc/Baustellen.tsx` · `PcProjektkosten` |
| [ ] | `/pc/auswertung/baustelle` | Auswertung je Baustelle | `screens/pc/Auswertung.tsx` · `PcAuswertungBaustelle` |
| [ ] | `/pc/auswertung/mitarbeiter` | Auswertung je Mitarbeiter | `screens/pc/Auswertung.tsx` · `PcAuswertungMitarbeiter` |
| [ ] | `/pc/mitarbeiter` | Mitarbeiter-Stammdaten (Arbeitszeit, Urlaubsanspruch mit Gültig-ab) | `screens/pc/Personal.tsx` · `PcMitarbeiter` |
| [ ] | `/p/auswertung` | Auswertung Polier | `screens/polier/Kolonne.tsx` · `PAuswertung` |
| [ ] | `/p/kosten/:id`, `/b/kosten/:id` | Kosten einer Baustelle (ohne NU-Zeile) | `screens/polier/Kolonne.tsx` · `PKosten` |
| [ ] | `/b/baustellen` | Baustellen der Bauleitung | `screens/bau/Bauleitung.tsx` · `BBaustellen` |
| [ ] | `/b/baustelle/:id` | Baustelle | `screens/bau/Bauleitung.tsx` · `BBaustelle` |
| [ ] | `/b/nachtrag/:id` | Nachtrag anlegen | `screens/bau/Bauleitung.tsx` · `BNachtrag` |

Aktionen aus `store.tsx`: `personAnlegen`, `personAendern`, `ursprungsSollSetzen`, `kostenMonatSetzen`, `rechnungBuchen`,
`mittellohnSetzen`, `nachtragAnlegen`, `nachrichtSenden`, `nachrichtErledigt`.

---

## Nicht übernommen

- `screens/ma/Rest.tsx` · `Demo` und die Knöpfe „Demo – anmelden als …“ in `PcLogin`: nur Demo
- localStorage `stundenerfassung-demo` und `stundenerfassung-handy`: ersetzt durch API und Geräte-Token
