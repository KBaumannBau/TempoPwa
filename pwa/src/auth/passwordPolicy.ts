// Rules for the own password (from TempoVibe src/domain/zugang.ts). Preview in the form only;
// binding is the server (rejection password_policy_violated). Messages are German translation keys for tx.

const normalize = (s: string) => s.trim().toUpperCase().replace(/[\s-]/g, '');

/** Returns the German message key or '' */
export function passwordViolation(password: string, forbidden: string[] = []): string {
  if (password.length < 8) return 'Mindestens 8 Zeichen.';
  if (!/\p{L}/u.test(password) || !/\d/.test(password)) return 'Mindestens ein Buchstabe und eine Ziffer.';
  if (forbidden.some((v) => v && normalize(v) === normalize(password))) return 'Bitte ein neues Passwort wählen, nicht das aus dem Brief oder das bisherige.';
  return '';
}
