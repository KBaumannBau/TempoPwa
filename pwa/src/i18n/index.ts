// Sprachen der Handy-App (Mitarbeiter, Nachunternehmer, Registrierung, Hilfe).
// Übernommen aus TempoVibe (src/i18n.ts), angepasst an echte Pfade statt Hash-Routing.
// Schlüssel ist der deutsche Text. Fehlt eine Übersetzung, erscheint Deutsch.
// PC-Seiten sowie die Seiten von Polier und Bauleitung bleiben deutsch.
// Rechtstexte: maßgeblich ist die deutsche Fassung (in der echten App aus se.rechtstext).
import pl from './pl.json';
import ro from './ro.json';
import hr from './hr.json';
import en from './en.json';
import fr from './fr.json';
import it from './it.json';

export type Sprache = 'de' | 'pl' | 'ro' | 'hr' | 'en' | 'fr' | 'it';
export const SPRACHEN: { code: Sprache; name: string }[] = [
  { code: 'de', name: 'Deutsch' }, { code: 'pl', name: 'Polski' }, { code: 'ro', name: 'Română' },
  { code: 'hr', name: 'Hrvatski / Srpski / Bosanski' }, { code: 'en', name: 'English' },
  { code: 'fr', name: 'Français' }, { code: 'it', name: 'Italiano' },
];
const WOERTER: Record<Exclude<Sprache, 'de'>, Record<string, string>> = { pl, ro, hr, en, fr, it };

// Nur die Anzeigesprache dieses Geräts, keine Fachdaten. Die Sprache der Person
// liegt auf dem Server (se.zugang.sprache) und wird nach der Anmeldung abgeglichen.
const SCHLUESSEL = 'tempo-sprache';

export function gespeicherteSprache(): Sprache {
  try {
    const s = localStorage.getItem(SCHLUESSEL) as Sprache | null;
    return s && SPRACHEN.some((x) => x.code === s) ? s : 'de';
  } catch { return 'de'; }
}

/** PC-Seiten und die Handy-Seiten von Polier (/p/) und Bauleitung (/b/) sind immer deutsch */
export const nurDeutsch = (pfad: string = typeof location !== 'undefined' ? location.pathname : '/') =>
  /^\/(pc|p|b)(\/|$)/.test(pfad);

/** Sprache, mit der diese Seite geladen wurde (PC, Polier, Bauleitung immer Deutsch) */
export const sprache: Sprache = nurDeutsch() ? 'de' : gespeicherteSprache();
if (typeof document !== 'undefined') document.documentElement.lang = sprache;

/** Text übersetzen; {0}, {1} … werden durch die Werte ersetzt */
export function tx(s: string, ...werte: unknown[]): string {
  const v = sprache === 'de' ? s : WOERTER[sprache][s] || s;
  return werte.length ? v.replace(/\{(\d+)\}/g, (_, i: string) => String(werte[Number(i)] ?? '')) : v;
}

/** Sprache wechseln: speichern und neu laden, damit alle Texte neu aufgebaut werden */
export function spracheWaehlen(s: Sprache) {
  try { localStorage.setItem(SCHLUESSEL, s); } catch { /* ohne Speicher bleibt Deutsch */ }
  location.reload();
}

/**
 * Sprache der angemeldeten Person (vom Server, se.zugang.sprache) auf diesem Gerät übernehmen.
 * Lädt nur neu, wenn die Seite gerade in einer anderen Sprache läuft.
 */
export function spracheAbgleichen(ziel: Sprache) {
  if (!SPRACHEN.some((x) => x.code === ziel)) return;
  if (gespeicherteSprache() !== ziel) try { localStorage.setItem(SCHLUESSEL, ziel); } catch { /* egal */ }
  if (!nurDeutsch() && ziel !== sprache) location.reload();
}
