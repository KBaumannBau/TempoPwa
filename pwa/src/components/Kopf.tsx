import { Link } from 'react-router';
import { tx } from '../i18n';
import { useOnline } from '../offline/online';
import { Logo } from './Logo';

/**
 * Blaue Kopfleiste mit dem Logo K. Baumann, wie in TempoVibe (src/components/Rahmen.tsx, Kopf).
 * Sprachwahl und Hilfe kommen mit den ersten Bildschirmen dazu. Neu: Hinweis, wenn das Handy offline ist.
 */
export function Kopf() {
  const online = useOnline();
  return (
    <header className="kopf">
      <Link to="/" aria-label={tx('Startseite')} className="kopf-logo"><Logo /></Link>
      <div className="kopf-rechts">
        {!online && <span className="kopf-offline" role="status">{tx('offline')}</span>}
      </div>
    </header>
  );
}
