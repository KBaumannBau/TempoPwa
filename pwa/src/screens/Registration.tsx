// First sign-in with the letter: one-time password → own password → done.
// Model: TempoVibe src/screens/Zugang.tsx · Registrierung (steps 1, 2 and 6).
// Still open from the demo: privacy notice (legal text from the database), ID check, location (docs/porting-status.md).
// Online only: the one-time password is checked by the server alone.

import { useState, type FormEvent } from 'react';
import { Link, Navigate, useNavigate } from 'react-router';
import { Button, Loading, Page, PasswordField, Spacer } from '../components/Controls';
import { tx } from '../i18n';
import { useOnline } from '../offline/online';
import { useBusy } from '../offline/idle';
import { passwordViolation } from '../auth/passwordPolicy';
import { useSession } from '../auth/session';

export function Registration() {
  const { session } = useSession();
  const [done, setDone] = useState(false);

  if (session.state === 'checking') return <Page title={tx('Erste Anmeldung')}><Loading text={tx('Einen Moment …')} /></Page>;
  if (session.state === 'password_change_required') return <OwnPassword onDone={() => setDone(true)} />;
  if (session.state === 'signed_in') return done ? <Done firstName={session.account.firstName} /> : <Navigate to="/home" replace />;
  return <OneTimePassword />;
}

function OneTimePassword() {
  const { signIn } = useSession();
  const online = useOnline();
  const navigate = useNavigate();
  const [username, setUsername] = useState('');
  const [oneTimePassword, setOneTimePassword] = useState('');
  const [message, setMessage] = useState('');
  const [blocked, setBlocked] = useState(false);
  const [busy, setBusy] = useState(false);
  useBusy(busy);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (!online || !username.trim() || !oneTimePassword || blocked || busy) return;
    setBusy(true);
    setMessage('');
    try {
      const result = await signIn(username, oneTimePassword);
      if (result.kind === 'ok') navigate('/home', { replace: true });
      else if (result.kind === 'message') { setMessage(result.text); setBlocked(!!result.blocked); }
    } finally {
      setBusy(false);
    }
  };

  return (
    <Page title={tx('Erste Anmeldung')}>
      <form className="column" onSubmit={(e) => void submit(e)} noValidate>
        <Link to="/" className="link">{tx('‹ Zur Anmeldung')}</Link>
        <h1 className="h1">{tx('Erste Anmeldung')}</h1>
        <p className="text">{tx('Trage den Benutzernamen und das Einmal-Passwort aus deinem Brief ein.')}</p>
        {!online && <div className="notice orange" role="status">{tx('Für die erste Anmeldung braucht das Handy Internet.')}</div>}
        <div className="field">
          <label htmlFor="registration-username">{tx('Benutzername')}</label>
          <input id="registration-username" className="input" autoComplete="username" autoCapitalize="characters" autoCorrect="off" spellCheck={false}
            value={username} onChange={(e) => { setUsername(e.target.value); setMessage(''); setBlocked(false); }} />
        </div>
        <PasswordField label={tx('Einmal-Passwort aus dem Brief')} value={oneTimePassword} onChange={(v) => { setOneTimePassword(v); setMessage(''); }} autoComplete="one-time-code" />
        {message && <div className="notice orange" role="alert">{message}</div>}
        <Spacer />
        <Button type="submit" disabled={!online || !username.trim() || !oneTimePassword || blocked || busy}>{busy ? tx('Einen Moment …') : tx('Anmelden')}</Button>
        <span className="small">{tx('Brief verloren oder abgelaufen? Die Lohnbuchhaltung schickt dir einen neuen. Der alte gilt dann nicht mehr.')}</span>
      </form>
    </Page>
  );
}

function OwnPassword({ onDone }: { onDone: () => void }) {
  const { session, setPassword } = useSession();
  const [first, setFirst] = useState('');
  const [second, setSecond] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  useBusy(true); // no app update in the middle of the registration

  const forbidden = session.state === 'password_change_required' ? [session.oneTimePassword, session.account.username] : [];
  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (!first || !second || busy) return;
    const violation = passwordViolation(first, forbidden);
    if (violation) return setError(tx(violation));
    if (first !== second) return setError(tx('Die beiden Passwörter sind nicht gleich.'));
    setBusy(true);
    try {
      const failure = await setPassword(first);
      if (failure) setError(failure); else onDone();
    } finally {
      setBusy(false);
    }
  };

  return (
    <Page title={tx('Eigenes Passwort')}>
      <form className="column" onSubmit={(e) => void submit(e)} noValidate>
        <h1 className="h1">{tx('Lege jetzt dein eigenes Passwort fest')}</h1>
        <p className="text">{tx('Das Einmal-Passwort aus dem Brief gilt ab jetzt nicht mehr. Ohne eigenes Passwort geht es nicht weiter.')}</p>
        <PasswordField label={tx('Neues Passwort')} value={first} onChange={(v) => { setFirst(v); setError(''); }} autoComplete="new-password" />
        <PasswordField label={tx('Neues Passwort wiederholen')} value={second} onChange={(v) => { setSecond(v); setError(''); }} autoComplete="new-password" />
        <span className="small">{tx('Mindestens 8 Zeichen, mit mindestens einem Buchstaben und einer Ziffer. Gib das Passwort niemandem weiter, auch nicht dem Polier.')}</span>
        {error && <div className="notice orange" role="alert">{error}</div>}
        <Spacer />
        <Button type="submit" disabled={!first || !second || busy}>{busy ? tx('Einen Moment …') : tx('Passwort speichern')}</Button>
      </form>
    </Page>
  );
}

function Done({ firstName }: { firstName: string }) {
  const navigate = useNavigate();
  return (
    <Page title={tx('Fertig')}>
      <h1 className="h1 large">{tx('Fertig, {0}!', firstName)}</h1>
      <p className="text">{tx('Du bist angemeldet. Dieses Handy bleibt angemeldet, du musst dich beim Öffnen der App nicht neu anmelden.')}</p>
      <p className="text">{tx('Dein Passwort brauchst du nur auf einem neuen Handy oder wenn du dich abgemeldet hast.')}</p>
      <Spacer />
      <Button color="green" onClick={() => navigate('/home', { replace: true })}>{tx('Gleich loslegen')}</Button>
    </Page>
  );
}
