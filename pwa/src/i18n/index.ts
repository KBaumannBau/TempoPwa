// Languages of the phone app (workers, subcontractors, registration, help).
// Taken over from TempoVibe (src/i18n.ts), adapted to real paths instead of hash routing.
// The key is the German text. Without a translation the German text is shown.
// Office pages and the pages of site foreman and site manager stay German.
// Legal texts: the German version is binding (in the real app from the database).
import pl from './pl.json';
import ro from './ro.json';
import hr from './hr.json';
import en from './en.json';
import fr from './fr.json';
import it from './it.json';

export type Language = 'de' | 'pl' | 'ro' | 'hr' | 'en' | 'fr' | 'it';
export const LANGUAGES: { code: Language; name: string }[] = [
  { code: 'de', name: 'Deutsch' }, { code: 'pl', name: 'Polski' }, { code: 'ro', name: 'Română' },
  { code: 'hr', name: 'Hrvatski / Srpski / Bosanski' }, { code: 'en', name: 'English' },
  { code: 'fr', name: 'Français' }, { code: 'it', name: 'Italiano' },
];
const TRANSLATIONS: Record<Exclude<Language, 'de'>, Record<string, string>> = { pl, ro, hr, en, fr, it };

// Only the display language of this device, no business data. The language of the person
// lives on the server (tempo.account.language) and is synced after sign-in.
const STORAGE_KEY = 'tempo-language';
/** Key used before the rename; read once so a stored choice is not lost */
const LEGACY_STORAGE_KEY = 'tempo-sprache';

const isLanguage = (value: string | null): value is Language => !!value && LANGUAGES.some((x) => x.code === value);

export function storedLanguage(): Language {
  try {
    const stored = localStorage.getItem(STORAGE_KEY) ?? localStorage.getItem(LEGACY_STORAGE_KEY);
    return isLanguage(stored) ? stored : 'de';
  } catch { return 'de'; }
}

function storeLanguage(value: Language) {
  try {
    localStorage.setItem(STORAGE_KEY, value);
    localStorage.removeItem(LEGACY_STORAGE_KEY);
  } catch { /* without storage the app stays German */ }
}

/** Office pages and the phone pages of site foreman (/p/) and site manager (/b/) are always German */
export const germanOnly = (path: string = typeof location !== 'undefined' ? location.pathname : '/') =>
  /^\/(pc|p|b)(\/|$)/.test(path);

/** Language this page was loaded with (office, site foreman, site manager always German) */
export const language: Language = germanOnly() ? 'de' : storedLanguage();
if (typeof document !== 'undefined') document.documentElement.lang = language;

/** Translate a text; {0}, {1} … are replaced by the values */
export function tx(text: string, ...values: unknown[]): string {
  const translated = language === 'de' ? text : TRANSLATIONS[language][text] || text;
  return values.length ? translated.replace(/\{(\d+)\}/g, (_, i: string) => String(values[Number(i)] ?? '')) : translated;
}

/** Change the language: store it and reload, so all texts are built again */
export function chooseLanguage(value: Language) {
  storeLanguage(value);
  location.reload();
}

/**
 * Take over the language of the signed-in person (from the server, tempo.account.language) on this device.
 * Reloads only if the page currently runs in another language.
 */
export function syncLanguage(target: Language) {
  if (!isLanguage(target)) return;
  if (storedLanguage() !== target) storeLanguage(target);
  if (!germanOnly() && target !== language) location.reload();
}
