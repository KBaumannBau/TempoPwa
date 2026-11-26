// Interim page after sign-in. The start pages per role (/ma/heute, /p/heute, /b/heute, /pc) come with the
// hours chain (docs/porting-status.md); until then this page shows the account, the API connection and the two
// ways out: lock (works offline) and sign out (online only).

import { useState } from 'react';
import { Navigate } from 'react-router';
import { useHealth } from '../api/health';
import { Button, Page, Spacer } from '../components/Controls';
import { tx } from '../i18n';
import { useOnline } from '../offline/online';
import { useSession } from '../auth/session';

export function Home() {
  const { session, lock, signOut } = useSession();
  const health = useHealth();
  const online = useOnline();
  const [message, setMessage] = useState('');

  if (session.state !== 'signed_in') return <Navigate to="/" replace />;
  const { account, offlineOnly } = session;

  return (
    <Page title={tx('Startseite')}>
      <div className="headings">
        <span className="subtitle">{tx('Angemeldet als {0}', `${account.firstName} ${account.lastName}`)}</span>
        <h1 className="h1 large">{tx('Hallo {0}!', account.firstName)}</h1>
      </div>
      {offlineOnly && <div className="notice blue" role="status">{tx('Ohne Internet angemeldet. Was du einträgst, geht raus, sobald wieder Netz da ist.')}</div>}

      <section className="card pad" aria-live="polite">
        <span className="bold">Verbindung zur API</span>
        {health.kind === 'loading' && <span className="small">Prüfe …</span>}
        {health.kind === 'ok' && (
          <dl>
            <dt>Status</dt><dd data-testid="health-status">{health.health.status}</dd>
            <dt>Datenbank</dt><dd>{health.health.database}</dd>
            <dt>Serverzeit</dt><dd>{new Date(health.health.time).toLocaleString('de-DE')}</dd>
          </dl>
        )}
        {health.kind === 'error' && <span className="small">{online ? `Nicht erreichbar: ${health.text}` : 'Offline, die API ist gerade nicht erreichbar.'}</span>}
      </section>

      <Spacer />
      {message && <div className="notice orange" role="alert">{message}</div>}
      <Button color="outline" onClick={() => void lock()}>{tx('App sperren')}</Button>
      <span className="small">{tx('Beim nächsten Öffnen fragt die App wieder nach dem Passwort. Das geht auch ohne Internet.')}</span>
      <Button color="outline" disabled={!online} onClick={() => void signOut().then((m) => m && setMessage(m))}>{tx('Dieses Handy abmelden')}</Button>
      <span className="small">{tx('Abmelden geht nur mit Internet. Danach braucht dieses Handy wieder eine Anmeldung mit Internet.')}</span>
    </Page>
  );
}
