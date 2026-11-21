import { useState } from 'react';
import { Link } from 'react-router';
import { SPRACHEN, nurDeutsch, sprache, spracheWaehlen, tx, type Sprache } from '../i18n';
import { useOnline } from '../offline/online';
import { IconGlobe } from './Icons';
import { Logo } from './Logo';

/**
 * Blaue Kopfleiste mit dem Logo K. Baumann und der Sprachwahl, wie in TempoVibe (src/components/Rahmen.tsx, Kopf).
 * Die Sprachwahl geht schon vor der Anmeldung. Nach der Anmeldung gilt die Sprache der Person vom Server;
 * eine Änderung hier wird dorthin übertragen, sobald es die Operation gibt (docs/pwa/anmeldung.md, Abschnitt 6).
 * Neu gegenüber der Demo: Hinweis, wenn das Handy offline ist.
 */
export function Kopf() {
  const online = useOnline();
  const [offen, setOffen] = useState(false);
  const aktuell = SPRACHEN.find((x) => x.code === sprache) ?? SPRACHEN[0]!;
  const waehlen = (s: Sprache) => (s === sprache ? setOffen(false) : spracheWaehlen(s));

  return (
    <header className="kopf">
      <Link to="/" aria-label={tx('Startseite')} className="kopf-logo"><Logo /></Link>
      <div className="kopf-rechts">
        {!online && <span className="kopf-offline" role="status">{tx('offline')}</span>}
        {!nurDeutsch() && (
          <button type="button" className="kopf-knopf" aria-label={`${tx('Sprache wählen')}: ${aktuell.name}`} aria-expanded={offen} onClick={() => setOffen(!offen)}>
            <IconGlobe size={20} />{sprache.toUpperCase()}
          </button>
        )}
      </div>
      {offen && !nurDeutsch() && (
        <div className="sprach-wahl" role="dialog" aria-label={tx('Sprache wählen')}>
          <span className="fett">{tx('Sprache wählen')}</span>
          <ul>
            {SPRACHEN.map((x) => (
              <li key={x.code}>
                <button type="button" lang={x.code} aria-pressed={x.code === sprache} onClick={() => waehlen(x.code)}>
                  <span className="code">{x.code.toUpperCase()}</span>{x.name}{x.code === sprache ? ' ✓' : ''}
                </button>
              </li>
            ))}
          </ul>
          <span className="klein">{tx('Maßgeblich ist die deutsche Fassung. Die Seiten für Polier, Bauleitung und Büro bleiben deutsch.')}</span>
        </div>
      )}
    </header>
  );
}
