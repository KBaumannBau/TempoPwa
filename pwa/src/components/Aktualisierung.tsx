import { useEffect, useRef } from 'react';
import { useRegisterSW } from 'virtual:pwa-register/react';
import { tx } from '../i18n';
import { istRuhig } from '../offline/ruhe';

/** Wie oft die offene App nach einer neuen Version fragt */
const PRUEFEN_ALLE_MS = 30 * 60 * 1000;

/**
 * Updates der App (docs/pwa/offline.md, Abschnitt 7).
 *
 * - Nachfragen: beim Start, beim Wechsel in den Vordergrund, wenn wieder Netz da ist und alle 30 Minuten.
 *   Wichtig fürs iPhone: Eine installierte App wird dort oft nur aus dem Hintergrund geholt, nie neu gestartet.
 * - Übernehmen: Die neue Version wird im Hintergrund geladen, aber nicht mitten in der Arbeit aktiviert.
 *   Sie wird übernommen, sobald die App in den Hintergrund geht und ruhig ist (nichts in Arbeit, Ausgang leer),
 *   oder wenn der Mitarbeiter auf den Hinweis tippt. Spätestens beim nächsten Kaltstart ist sie aktiv.
 */
export function Aktualisierung() {
  const registrierung = useRef<ServiceWorkerRegistration | undefined>(undefined);

  const {
    needRefresh: [neueVersion],
    updateServiceWorker,
  } = useRegisterSW({
    onRegisteredSW(_url, r) {
      registrierung.current = r;
    },
    onRegisterError(fehler) {
      console.warn('Service Worker nicht registriert', fehler);
    },
  });

  // Nach neuer Version fragen
  useEffect(() => {
    const pruefen = () => {
      const r = registrierung.current;
      if (!r || !navigator.onLine || r.installing) return;
      r.update().catch(() => { /* kein Netz oder Server weg: beim nächsten Mal */ });
    };
    const sichtbar = () => { if (document.visibilityState === 'visible') pruefen(); };
    document.addEventListener('visibilitychange', sichtbar);
    window.addEventListener('online', pruefen);
    const takt = window.setInterval(pruefen, PRUEFEN_ALLE_MS);
    return () => {
      document.removeEventListener('visibilitychange', sichtbar);
      window.removeEventListener('online', pruefen);
      window.clearInterval(takt);
    };
  }, []);

  // Neue Version still übernehmen, wenn die App in den Hintergrund geht und nichts in Arbeit ist.
  // Beim Zurückholen ist sie dann schon aktiv, der Mitarbeiter merkt das Neuladen nicht.
  useEffect(() => {
    if (!neueVersion) return;
    const versteckt = () => {
      if (document.visibilityState === 'hidden' && istRuhig()) void updateServiceWorker(true);
    };
    document.addEventListener('visibilitychange', versteckt);
    return () => document.removeEventListener('visibilitychange', versteckt);
  }, [neueVersion, updateServiceWorker]);

  if (!neueVersion) return null;

  return (
    <div className="aktualisierung" role="status">
      <span>{tx('Neue Version der App verfügbar.')}</span>
      <button
        type="button"
        onClick={() => {
          if (istRuhig() || window.confirm(tx('Es ist noch etwas in Arbeit. Trotzdem jetzt aktualisieren?'))) {
            void updateServiceWorker(true);
          }
        }}
      >
        {tx('Jetzt aktualisieren')}
      </button>
    </div>
  );
}
