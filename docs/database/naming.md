# Namen in Datenbank und Code (Englisch)

Code, Dateien, Klassen, Tabellen, Spalten, API-Pfade und JSON-Felder sind **englisch** (CLAUDE.md).
Sichtbare Texte bleiben deutsch und laufen über `tx()`. Grundlage ist das deutsche Schema aus TempoVibe
(`docs/datenbankschema.sql`); diese Liste hält fest, wie wir die Begriffe übersetzen, damit alle dieselben Wörter benutzen.

## Bereits umgesetzt (Kontext Access, Schema `tempo`)

| TempoVibe (`se`) | Tempo (`tempo`) | Bemerkung |
|---|---|---|
| Schema `se` | `tempo` | |
| `rolle` (`schluessel`, `bezeichnung`, `je_baustelle`, `eigene_rolle`) | `role` (`key`, `label`, `per_site`, `own_staff`) | Bezeichnungen bleiben deutsch (Anzeige) |
| `person` (`pers_nr`, `vorname`, `nachname`, `beschaeftigung`, `taetigkeit`, `eintritt`, `austritt`, `unter_18`, `alleinerziehend`, `vorgesetzter_id`, `nutzt_app`, `handy_kontakt`, `angelegt_am`, `angelegt_von`, `gesperrt_am`) | `person` (`personnel_number`, `first_name`, `last_name`, `employment_type`, `job_title`, `entry_date`, `exit_date`, `under_18`, `single_parent`, `supervisor_id`, `uses_app`, `contact_phone`, `created_at`, `created_by`, `locked_at`) | `nu_firma_id` kommt mit den Nachunternehmern |
| `person_rolle` (`von`, `bis`, `vergeben_von`, `vergeben_am`) | `person_role` (`valid_from`, `valid_to`, `granted_by`, `granted_at`) | |
| `zugang` | `account` | neu: `username`; `fehlversuche` → `failed_attempts`, `gesperrt_bis` → `locked_until`, `sprache` → `language`, `intro_gesehen` → `intro_seen`, `registriert_am` → `registered_at` |
| `einladung` | `invitation` | `token_hash` → `secret_hash`, `gueltig_bis` → `valid_until`, `genutzt_am` → `used_at`, neu `revoked_at` |
| `geraet` | `device` | `bezeichnung` → `label`, `schluessel_hash` → `key_hash`, `zuletzt_gesehen` → `last_seen_at`, `abgemeldet_am/_von` → `signed_out_at/_by` |

Aufzählungswerte: `beschaeftigung` gewerblich/angestellt/azubi → `wage_earner`/`salaried`/`apprentice`;
`zugang_status` ohne/eingeladen/aktiv/gesperrt → `none`/`invited`/`active`/`locked`; `geraet_status` aktiv/abgemeldet →
`active`/`signed_out`.

Rollen: gl → `management`, kaufm_leitung → `commercial_management`, buchhaltung → `accounting`, lohnbuero → `payroll`,
abrechnung → `billing`, arbeitsvorbereitung → `work_preparation`, einkauf → `purchasing`, oberbauleitung →
`senior_site_manager`, bauleitung → `site_manager`, polier → `site_foreman`, vorarbeiter → `foreman`, facharbeiter →
`skilled_worker`, helfer → `helper`, nu_person → `subcontractor_worker`, nu_firma → `subcontractor_contact`, admin → `admin`.

## Fachbegriffe (Vorschlag für den Umbau)

| Deutsch | Englisch |
|---|---|
| Stundenerfassung | time tracking |
| Stempelung / stempeln | clock event / clock in, clock out |
| Arbeitstag (Tag) | work day |
| Tag senden / freigeben | submit day / approve day |
| Baustelle | construction site (`site`) |
| Polier / Bauleitung / Oberbauleitung | site foreman / site manager / senior site manager |
| Kolonne | crew |
| Zuschlag | allowance |
| Korrektur | correction |
| Antrag (Urlaub, Arbeitsbefreiung) | request (leave, special leave) |
| Konto (Arbeitszeitkonto) | time account |
| Lohnbuchhaltung | payroll |
| Nachunternehmer (NU) | subcontractor |
| Disposition / Einteilung | scheduling / assignment |
| Ausgang (Offline-Warteschlange) | outbox |
| Lesestand | read model |
| Tresor (Offline-Anmeldung) | vault |
