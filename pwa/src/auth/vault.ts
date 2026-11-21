// Vault for offline sign-in (docs/pwa/authentication.md, section 4).
//
// After every successful sign-in at the server the app creates a vault:
//   password --PBKDF2-SHA-256 (600,000 iterations, own salt)--> AES-GCM key --> encrypted account.
// Offline the app checks a password by opening the vault. A wrong password fails the AES-GCM
// authentication; no password verifier is stored in plain.
//
// Limits (deliberate, see concept):
// - Whoever extracts the app data of an unlocked phone can try passwords offline. Hence many iterations,
//   the password rule and a maximum time without server contact.
// - Offline sign-in only opens what is already on the phone. Everything the app sends later is checked by the
//   server with the device cookie. A revoked device loses all local data on the next contact.

import { normalizeUsername, type Account } from './account';
import { read, remove, write } from './storage';

const KEY = 'vault';
/** OWASP recommendation for PBKDF2-HMAC-SHA-256 (2023) */
const ITERATIONS = 600_000;
/** Offline sign-in works this long without server contact (proposal, to be confirmed by the client) */
export const OFFLINE_MAX_DAYS = 14;
/** After this many wrong passwords offline sign-in pauses (same as online) */
export const MAX_FAILED_ATTEMPTS = 5;
const LOCK_MS = 15 * 60 * 1000;

interface VaultRecord {
  version: 1;
  username: string;
  salt: Uint8Array;
  iv: Uint8Array;
  iterations: number;
  ciphertext: ArrayBuffer;
  /** Last successful contact with the server (ISO, server time when known) */
  serverContactAt: string;
  failedAttempts: number;
  lockedUntil?: string;
}

export type OpenResult =
  | { kind: 'ok'; account: Account }
  | { kind: 'no_vault' }
  | { kind: 'wrong_password'; remainingAttempts: number }
  | { kind: 'locked'; until: Date }
  | { kind: 'offline_too_long'; since: Date };

const encoder = new TextEncoder();

async function deriveKey(password: string, salt: Uint8Array, iterations: number): Promise<CryptoKey> {
  const base = await crypto.subtle.importKey('raw', encoder.encode(password), 'PBKDF2', false, ['deriveKey']);
  return crypto.subtle.deriveKey(
    { name: 'PBKDF2', hash: 'SHA-256', salt: salt as BufferSource, iterations },
    base,
    { name: 'AES-GCM', length: 256 },
    false,
    ['encrypt', 'decrypt'],
  );
}

/** Additional data for AES-GCM: binds the ciphertext to the username and the format */
const additionalData = (username: string) => encoder.encode(`tempo-vault-v1:${username}`);

/** After a successful sign-in at the server: create a fresh vault (new salt, new IV) */
export async function createVault(account: Account, password: string, serverTime = new Date()): Promise<void> {
  const username = normalizeUsername(account.username);
  const salt = crypto.getRandomValues(new Uint8Array(16));
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const key = await deriveKey(password, salt, ITERATIONS);
  const ciphertext = await crypto.subtle.encrypt(
    { name: 'AES-GCM', iv: iv as BufferSource, additionalData: additionalData(username) },
    key,
    encoder.encode(JSON.stringify(account)),
  );
  const record: VaultRecord = { version: 1, username, salt, iv, iterations: ITERATIONS, ciphertext, serverContactAt: serverTime.toISOString(), failedAttempts: 0 };
  await write(KEY, record);
}

/** On every server contact: remember the time (extends the offline period) */
export async function recordServerContact(serverTime = new Date()): Promise<void> {
  const record = await read<VaultRecord>(KEY);
  if (record) await write(KEY, { ...record, serverContactAt: serverTime.toISOString() });
}

/** Username of the vault, e.g. to prefill the field */
export async function vaultUsername(): Promise<string | undefined> {
  return (await read<VaultRecord>(KEY))?.username;
}

/** Offline sign-in: open the vault with the password */
export async function openVault(usernameInput: string, password: string, now = new Date()): Promise<OpenResult> {
  const record = await read<VaultRecord>(KEY);
  const username = normalizeUsername(usernameInput);
  if (!record || record.username !== username) return { kind: 'no_vault' };
  if (record.lockedUntil && new Date(record.lockedUntil) > now) return { kind: 'locked', until: new Date(record.lockedUntil) };
  const since = new Date(record.serverContactAt);
  if (now.getTime() - since.getTime() > OFFLINE_MAX_DAYS * 86_400_000) return { kind: 'offline_too_long', since };

  try {
    const key = await deriveKey(password, record.salt, record.iterations);
    const plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: record.iv as BufferSource, additionalData: additionalData(username) }, key, record.ciphertext);
    await write(KEY, { ...record, failedAttempts: 0, lockedUntil: undefined });
    return { kind: 'ok', account: JSON.parse(new TextDecoder().decode(plain)) as Account };
  } catch {
    const failedAttempts = record.failedAttempts + 1;
    if (failedAttempts >= MAX_FAILED_ATTEMPTS) {
      const until = new Date(now.getTime() + LOCK_MS);
      await write(KEY, { ...record, failedAttempts: 0, lockedUntil: until.toISOString() });
      return { kind: 'locked', until };
    }
    await write(KEY, { ...record, failedAttempts });
    return { kind: 'wrong_password', remainingAttempts: MAX_FAILED_ATTEMPTS - failedAttempts };
  }
}

export const deleteVault = () => remove(KEY);
