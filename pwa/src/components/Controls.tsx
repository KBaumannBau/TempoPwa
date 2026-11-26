// Shared building blocks of the screens, taken over from TempoVibe (src/components/Teile.tsx, Rahmen.tsx, screens/Zugang.tsx).
import { useEffect, useId, useState, type ReactNode } from 'react';
import { tx } from '../i18n';

/** Large button. `type="submit"` for forms (enter key, password managers). */
export function Button({ color = 'blue', disabled, onClick, type = 'button', children }: {
  color?: 'blue' | 'green' | 'outline' | 'red' | 'dark'; disabled?: boolean; onClick?: () => void; type?: 'button' | 'submit'; children: ReactNode;
}) {
  return (
    <button
      type={type}
      className={`button ${color}`}
      aria-disabled={disabled ? 'true' : undefined}
      onClick={(e) => { if (disabled) { e.preventDefault(); return; } onClick?.(); }}
    >
      {children}
    </button>
  );
}

/** Pushes the following buttons to the bottom */
export const Spacer = () => <span className="spacer" />;

/** Password field with "show" */
export function PasswordField({ label, value, onChange, autoComplete }: {
  label: string; value: string; onChange: (s: string) => void; autoComplete: 'current-password' | 'new-password' | 'one-time-code';
}) {
  const id = useId();
  const [visible, setVisible] = useState(false);
  return (
    <div className="field">
      <label htmlFor={id}>{label}</label>
      <div className="field-row">
        <input id={id} className="input" type={visible ? 'text' : 'password'} autoComplete={autoComplete} autoCapitalize="off" autoCorrect="off" spellCheck={false} value={value} onChange={(e) => onChange(e.target.value)} />
        <button type="button" className="link" aria-pressed={visible} onClick={() => setVisible(!visible)}>{visible ? tx('Verbergen') : tx('Anzeigen')}</button>
      </div>
    </div>
  );
}

/** Content of a phone screen (column in phone width) with the document title */
export function Page({ title, children }: { title: string; children: ReactNode }) {
  useEffect(() => { document.title = `${title} · Tempo`; }, [title]);
  return <main className="content">{children}</main>;
}

/** "Working" hint, e.g. while the server answers or the vault computes */
export function Loading({ text }: { text: string }) {
  return <p className="text center" role="status" aria-live="polite">{text}</p>;
}
