// Session of the app: who is signed in on this phone, online or offline only?
// Concept: docs/pwa/authentication.md. In short:
// - The first sign-in on a phone needs the server (it sets the HttpOnly device cookie).
// - After that the phone stays signed in (client requirement). The account is kept in the store `account`.
// - "Lock app" removes only the account; the vault stays. Then sign-in also works offline.
// - "Sign out this phone" revokes the device at the server and deletes everything. Online only.

import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { spracheAbgleichen, sprache, tx } from '../i18n';
import { useOnline } from '../offline/online';
import type { Account } from './account';
import { authApi, type Rejection } from './api';
import { clearAll, read, remove, write } from './storage';
import { createVault, openVault, recordServerContact } from './vault';

const ACCOUNT_KEY = 'account';

export type SignedOutReason = 'device_signed_out' | 'account_locked';

export type Session =
  | { state: 'checking' }
  | { state: 'signed_out'; reason?: SignedOutReason }
  /** Signed in with the one-time password: an own password must be set before anything else */
  | { state: 'password_change_required'; account: Account; oneTimePassword: string }
  | { state: 'signed_in'; account: Account; offlineOnly: boolean };

export type SignInResult =
  | { kind: 'ok'; offlineOnly: boolean }
  | { kind: 'password_change_required' }
  | { kind: 'message'; text: string; blocked?: boolean };

interface SessionApi {
  session: Session;
  signIn: (username: string, password: string) => Promise<SignInResult>;
  /** Returns an error text or null */
  setPassword: (newPassword: string) => Promise<string | null>;
  lock: () => Promise<void>;
  /** Returns an error text or null */
  signOut: () => Promise<string | null>;
}

const SessionContext = createContext<SessionApi | null>(null);

const clockTime = (d: Date) => d.toLocaleTimeString(sprache, { hour: '2-digit', minute: '2-digit' });
const calendarDate = (d: Date) => d.toLocaleDateString(sprache, { day: '2-digit', month: '2-digit', year: 'numeric' });

/** Business rejection of the server as text for the user (German key, translated with tx) */
export function rejectionText(rejection: Rejection): { text: string; blocked?: boolean } {
  switch (rejection.code) {
    case 'invalid_credentials': return { text: tx('Benutzername oder Passwort stimmt nicht. Noch {0} Versuche.', rejection.remainingAttempts) };
    case 'temporarily_locked': return { text: tx('Zu viele falsche Eingaben. Anmelden geht wieder ab {0} Uhr.', clockTime(new Date(rejection.lockedUntil))), blocked: true };
    case 'account_locked': return { text: tx('Dein Zugang ist gesperrt. Bitte melde dich in der Lohnbuchhaltung.') };
    case 'one_time_password_expired': return { text: tx('Das Einmal-Passwort ist abgelaufen. Die Lohnbuchhaltung schickt dir einen neuen Brief.') };
    case 'registration_expired': return { text: tx('Die Anmeldung mit dem Brief ist abgelaufen. Bitte melde dich noch einmal mit dem Einmal-Passwort an.') };
    case 'password_policy_violated': return { text: tx(rejection.detail) };
    case 'device_signed_out': return { text: tx('Es wurde von dir oder von der Lohnbuchhaltung abgemeldet. Von hier kann nichts mehr gesendet werden.') };
    default: return { text: tx('Die Anmeldung hat nicht geklappt. Bitte später noch einmal versuchen.') };
  }
}

export function SessionProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<Session>({ state: 'checking' });
  const online = useOnline();

  const enter = useCallback(async (account: Account, offlineOnly: boolean) => {
    await write(ACCOUNT_KEY, account);
    setSession({ state: 'signed_in', account, offlineOnly });
    spracheAbgleichen(account.language);
  }, []);

  /** Delete everything on the phone: account, vault (later also read model and outbox) */
  const wipe = useCallback(async (reason?: SignedOutReason) => {
    await clearAll();
    setSession({ state: 'signed_out', reason });
  }, []);

  /** Confirm with the server: is this device still valid? New data about the person? */
  const confirmWithServer = useCallback(async () => {
    const result = await authApi.me();
    if (result.kind === 'unreachable') return;
    if (result.kind === 'ok') {
      await recordServerContact(result.serverTime);
      await enter(result.data, false);
      return;
    }
    const code = result.rejection.code;
    if (code === 'device_signed_out' || code === 'unauthenticated') await wipe('device_signed_out');
    else if (code === 'account_locked') await wipe('account_locked');
  }, [enter, wipe]);

  // Start: is somebody signed in on this phone? Then open at once, also without network.
  useEffect(() => {
    let active = true;
    read<Account>(ACCOUNT_KEY)
      .then((account) => {
        if (active) setSession(account ? { state: 'signed_in', account, offlineOnly: true } : { state: 'signed_out' });
      })
      .catch(() => active && setSession({ state: 'signed_out' }));
    return () => { active = false; };
  }, []);

  // As soon as there is network and somebody is signed in: let the server confirm
  const signedIn = session.state === 'signed_in';
  useEffect(() => {
    if (signedIn && online) void confirmWithServer();
  }, [signedIn, online, confirmWithServer]);

  const signIn = useCallback(async (username: string, password: string): Promise<SignInResult> => {
    const result = await authApi.login(username, password, sprache);
    if (result.kind === 'ok') {
      if (result.data.passwordChangeRequired) {
        setSession({ state: 'password_change_required', account: result.data.account, oneTimePassword: password });
        return { kind: 'password_change_required' };
      }
      await createVault(result.data.account, password, result.serverTime);
      await enter(result.data.account, false);
      return { kind: 'ok', offlineOnly: false };
    }
    if (result.kind === 'rejected') return { kind: 'message', ...rejectionText(result.rejection) };

    // Server not reachable: sign in offline with the vault (never after a "wrong password" from the server)
    const opened = await openVault(username, password);
    switch (opened.kind) {
      case 'ok': await enter(opened.account, true); return { kind: 'ok', offlineOnly: true };
      case 'no_vault': return { kind: 'message', text: tx('Kein Internet. Die erste Anmeldung auf diesem Handy geht nur mit Internet.') };
      case 'wrong_password': return { kind: 'message', text: tx('Benutzername oder Passwort stimmt nicht. Noch {0} Versuche.', opened.remainingAttempts) };
      case 'locked': return { kind: 'message', text: tx('Zu viele falsche Eingaben. Anmelden geht wieder ab {0} Uhr.', clockTime(opened.until)), blocked: true };
      case 'offline_too_long': return { kind: 'message', text: tx('Dieses Handy war seit {0} nicht mehr mit dem Server verbunden. Bitte einmal mit Internet anmelden.', calendarDate(opened.since)) };
    }
  }, [enter]);

  const setPassword = useCallback(async (newPassword: string): Promise<string | null> => {
    if (session.state !== 'password_change_required') return tx('Die Anmeldung hat nicht geklappt. Bitte später noch einmal versuchen.');
    const result = await authApi.setPassword(newPassword);
    if (result.kind === 'unreachable') return tx('Kein Internet. Bitte versuche es gleich noch einmal.');
    if (result.kind === 'rejected') {
      if (result.rejection.code === 'registration_expired') setSession({ state: 'signed_out' });
      return rejectionText(result.rejection).text;
    }
    const account = { ...session.account, language: sprache };
    await createVault(account, newPassword, result.serverTime);
    await enter(account, false);
    return null;
  }, [session, enter]);

  const lock = useCallback(async () => {
    await remove(ACCOUNT_KEY);
    setSession({ state: 'signed_out' });
  }, []);

  const signOut = useCallback(async (): Promise<string | null> => {
    const result = await authApi.logout();
    if (result.kind === 'unreachable') return tx('Abmelden geht nur mit Internet. Sperren geht auch ohne.');
    await wipe();
    return null;
  }, [wipe]);

  const value = useMemo(() => ({ session, signIn, setPassword, lock, signOut }), [session, signIn, setPassword, lock, signOut]);
  return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}

export function useSession(): SessionApi {
  const context = useContext(SessionContext);
  if (!context) throw new Error('useSession outside of SessionProvider');
  return context;
}
