import { useState } from 'react';
import { Link } from 'react-router';
import { LANGUAGES, chooseLanguage, germanOnly, language, tx, type Language } from '../i18n';
import { useOnline } from '../offline/online';
import { IconGlobe } from './Icons';
import { Logo } from './Logo';

/**
 * Blue header with the K. Baumann logo and the language picker, as in TempoVibe (src/components/Rahmen.tsx, Kopf).
 * The language can be chosen before sign-in. After sign-in the language of the person from the server applies;
 * a change here is sent there as soon as the operation exists (docs/pwa/authentication.md, section 6).
 * New compared with the demo: a hint when the phone is offline.
 */
export function Header() {
  const online = useOnline();
  const [open, setOpen] = useState(false);
  const current = LANGUAGES.find((x) => x.code === language) ?? LANGUAGES[0]!;
  const choose = (value: Language) => (value === language ? setOpen(false) : chooseLanguage(value));

  return (
    <header className="header">
      <Link to="/" aria-label={tx('Startseite')} className="header-logo"><Logo /></Link>
      <div className="header-right">
        {!online && <span className="header-offline" role="status">{tx('offline')}</span>}
        {!germanOnly() && (
          <button type="button" className="header-button" aria-label={`${tx('Sprache wählen')}: ${current.name}`} aria-expanded={open} onClick={() => setOpen(!open)}>
            <IconGlobe size={20} />{language.toUpperCase()}
          </button>
        )}
      </div>
      {open && !germanOnly() && (
        <div className="language-picker" role="dialog" aria-label={tx('Sprache wählen')}>
          <span className="bold">{tx('Sprache wählen')}</span>
          <ul>
            {LANGUAGES.map((x) => (
              <li key={x.code}>
                <button type="button" lang={x.code} aria-pressed={x.code === language} onClick={() => choose(x.code)}>
                  <span className="code">{x.code.toUpperCase()}</span>{x.name}{x.code === language ? ' ✓' : ''}
                </button>
              </li>
            ))}
          </ul>
          <span className="small">{tx('Maßgeblich ist die deutsche Fassung. Die Seiten für Polier, Bauleitung und Büro bleiben deutsch.')}</span>
        </div>
      )}
    </header>
  );
}
