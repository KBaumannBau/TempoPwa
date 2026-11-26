// First screen of the app: sign in with username and password.
// Model: TempoVibe src/screens/Zugang.tsx · Sperre. The person picker of TempoVibe src/screens/Anmeldung.tsx
// is demo only and not taken over (docs/porting-status.md).
//
// Online the server decides. Without network the app opens the vault on the phone if this person has signed in
// here online before (docs/pwa/authentication.md).

import { useEffect, useState, type FormEvent } from 'react';
import { Link, Navigate, useNavigate } from 'react-router';
import { Button, Loading, Page, PasswordField, Spacer } from '../components/Controls';
import { tx } from '../i18n';
import { useOnline } from '../offline/online';
import { useBusy } from '../offline/idle';
import { useSession } from '../auth/session';
import { vaultUsername } from '../auth/vault';

export function Login() {
  const { session, signIn } = useSession();
  const online = useOnline();
  const navigate = useNavigate();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [message, setMessage] = useState('');
  const [blocked, setBlocked] = useState(false);
  const [busy, setBusy] = useState(false);
  const [forgotten, setForgotten] = useState(false);
  useBusy(busy);

  // Whoever signed in here before does not need to type the username again
  useEffect(() => { void vaultUsername().then((u) => u && setUsername((current) => current || u)); }, []);

  if (session.state === 'checking') return <Page title={tx('Anmelden')}><Loading text={tx('Einen Moment …')} /></Page>;
  if (session.state === 'signed_in') return <Navigate to="/home" replace />;
  if (session.state === 'password_change_required') return <Navigate to="/registration" replace />;

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (!username.trim() || !password || blocked || busy) return;
    setBusy(true);
    setMessage('');
    try {
      const result = await signIn(username, password);
      if (result.kind === 'ok') navigate('/home', { replace: true });
      else if (result.kind === 'password_change_required') navigate('/registration', { replace: true });
      else { setMessage(result.text); setBlocked(!!result.blocked); setPassword(''); }
    } finally {
      setBusy(false);
    }
  };

  return (
    <Page title={tx('Anmelden')}>
      <form className="column" onSubmit={(e) => void submit(e)} noValidate>
        <h1 className="h1">{tx('Anmelden')}</h1>
        {session.reason === 'device_signed_out' && (
          <div className="notice orange" role="status"><strong>{tx('Dieses Handy ist abgemeldet')}</strong> {tx('Es wurde von dir oder von der Lohnbuchhaltung abgemeldet. Von hier kann nichts mehr gesendet werden.')}</div>
        )}
        {session.reason === 'account_locked' && (
          <div className="notice orange" role="status">{tx('Dein Zugang ist gesperrt. Bitte melde dich in der Lohnbuchhaltung.')}</div>
        )}
        <p className="text">{tx('Nur nötig auf einem neuen Handy oder nachdem du dich abgemeldet hast. Danach bleibt dieses Handy angemeldet.')}</p>
        {!online && <div className="notice blue" role="status">{tx('Kein Internet. Anmelden geht trotzdem, wenn du dich auf diesem Handy schon einmal angemeldet hast.')}</div>}
        <div className="field">
          <label htmlFor="login-username">{tx('Benutzername')}</label>
          <input id="login-username" className="input" autoComplete="username" autoCapitalize="characters" autoCorrect="off" spellCheck={false}
            value={username} onChange={(e) => { setUsername(e.target.value); setMessage(''); setBlocked(false); }} />
        </div>
        <PasswordField label={tx('Passwort')} value={password} onChange={(v) => { setPassword(v); setMessage(''); }} autoComplete="current-password" />
        {message && <div className="notice orange" role="alert">{message}</div>}
        <Button type="submit" disabled={!username.trim() || !password || blocked || busy}>{busy ? tx('Einen Moment …') : tx('Anmelden')}</Button>
        <button type="button" className="link center" aria-expanded={forgotten} onClick={() => setForgotten(!forgotten)}>{tx('Passwort vergessen?')}</button>
        {forgotten && <div className="notice blue">{tx('Melde dich bei der Lohnbuchhaltung oder sag deinem Polier Bescheid. Du bekommst einen neuen Brief mit Einmal-Passwort per Post. Dein altes Passwort gilt dann nicht mehr.')}</div>}
        <Spacer />
        <Link className="button outline" to="/registration">{tx('Erste Anmeldung mit Brief')}</Link>
      </form>
    </Page>
  );
}
