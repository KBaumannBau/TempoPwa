// Calls of the authentication API (contract: docs/pwa/authentication.md, section 5; live in /api/docs).
// Request and response bodies are typed from the generated OpenAPI schema (`npm run api:types`). The calls use
// fetch directly (not ../api/client) because sign-in needs its own handling of timeouts and "server unreachable".
//
// The device credential is an HttpOnly cookie. The app never sees it.

import type { components } from '../api/generated/schema';
import type { Language } from '../i18n';
import type { Account } from './account';

type Schemas = components['schemas'];

const baseUrl = (import.meta.env.VITE_API_URL ?? '').replace(/\/+$/, '');
const TIMEOUT_MS = 8000;

/** Business rejection of the API (application/problem+json with a machine-readable code) */
export type Rejection =
  | { code: 'invalid_credentials'; remainingAttempts: number }
  | { code: 'temporarily_locked'; lockedUntil: string }
  | { code: 'account_locked' }
  | { code: 'one_time_password_expired' }
  | { code: 'registration_expired' }
  | { code: 'password_policy_violated'; detail: string }
  | { code: 'device_signed_out' }
  | { code: 'unauthenticated' }
  | { code: 'unknown'; detail: string };

export type ApiResult<T> =
  | { kind: 'ok'; data: T; serverTime: Date }
  | { kind: 'rejected'; rejection: Rejection }
  /** Server not reachable (no network, timeout, 5xx): carry on offline */
  | { kind: 'unreachable' };

const KNOWN_CODES = new Set<Rejection['code']>([
  'invalid_credentials', 'temporarily_locked', 'account_locked', 'one_time_password_expired',
  'registration_expired', 'password_policy_violated', 'device_signed_out', 'unauthenticated',
]);

async function call<T>(method: 'GET' | 'POST', path: string, body?: unknown): Promise<ApiResult<T>> {
  let response: Response;
  try {
    response = await fetch(`${baseUrl}${path}`, {
      method,
      credentials: baseUrl ? 'include' : 'same-origin',
      headers: { Accept: 'application/json', ...(method === 'POST' ? { 'Content-Type': 'application/json' } : {}) },
      body: method === 'POST' ? JSON.stringify(body ?? {}) : undefined,
      signal: AbortSignal.timeout(TIMEOUT_MS),
      cache: 'no-store',
    });
  } catch {
    return { kind: 'unreachable' };
  }
  const date = response.headers.get('Date');
  const serverTime = date ? new Date(date) : new Date();
  if (response.status >= 500) return { kind: 'unreachable' };
  const json: unknown = response.status === 204 ? undefined : await response.json().catch(() => undefined);
  if (response.ok) return { kind: 'ok', data: json as T, serverTime };

  const problem = (json ?? {}) as { code?: string; detail?: string };
  if (problem.code && KNOWN_CODES.has(problem.code as Rejection['code'])) return { kind: 'rejected', rejection: problem as Rejection };
  return { kind: 'rejected', rejection: { code: 'unknown', detail: problem.detail ?? `HTTP ${response.status}` } };
}

/** passwordChangeRequired = true after signing in with the one-time password: set an own password first */
export type LoginResponse = Omit<Schemas['Login'], 'account'> & { account: Account };

/** Short name of the phone for the device list */
function deviceLabel(): string {
  const ua = navigator.userAgent;
  if (/iPhone/.test(ua)) return 'iPhone';
  if (/iPad/.test(ua)) return 'iPad';
  const android = /Android[^;]*;\s*([^;)]+)/.exec(ua)?.[1]?.trim();
  if (android) return android.slice(0, 80);
  return /Windows/.test(ua) ? 'Windows-PC' : /Mac OS/.test(ua) ? 'Mac' : 'Browser';
}

export const authApi = {
  /** Username with the own password or the one-time password from the letter */
  login: (username: string, password: string, language: Language) =>
    call<LoginResponse>('POST', '/api/auth/login', { username, password, language, deviceLabel: deviceLabel() } satisfies Schemas['Login.LoginRequest']),
  /** Set the own password (required after the letter) */
  setPassword: (newPassword: string) => call<undefined>('POST', '/api/auth/password', { newPassword } satisfies Schemas['Password']),
  /** Who is signed in on this phone? */
  me: () => call<Account>('GET', '/api/auth/me'),
  /** Sign out this phone (the device credential becomes invalid) */
  logout: () => call<undefined>('POST', '/api/auth/logout'),
};
